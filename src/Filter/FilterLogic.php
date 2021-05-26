<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\QueryExpressionGeneratorInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Combines existing API Platform ORM Filters with AND and OR.
 * For usage and limitations see https://gist.github.com/metaclass-nl/790a5c8e9064f031db7d3379cc47c794
 * Copyright (c) MetaClass, Groningen, 2021. MIT License
 */
class FilterLogic extends AbstractContextAwareFilter implements QueryExpressionGeneratorInterface
{
    /** @var ResourceMetadataFactoryInterface  */
    private $resourceMetadataFactory;
    /** @var ContainerInterface|FilterCollection  */
    private $filterLocator;
    /** @var string Filter classes must match this to be applied with logic */
    private $classExp;

    /**
     * @param ResourceMetadataFactoryInterface $resourceMetadataFactory
     * @param ContainerInterface|FilterCollection $filterLocator
     * @param $regExp string Filter classes must match this to be applied with logic
     * {@inheritdoc}
     */
    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, $filterLocator, string $classExp='//', ManagerRegistry $managerRegistry, RequestStack $requestStack=null, LoggerInterface $logger = null, array $properties = null, NameConverterInterface $nameConverter = null)
    {
        parent::__construct($managerRegistry, $requestStack, $logger, $properties, $nameConverter);
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->filterLocator = $filterLocator;
        $this->classExp = $classExp;
    }

    /** {@inheritdoc } */
    public function getDescription(string $resourceClass): array
    {
        // No description
        return [];
    }

    /**
     * {@inheritdoc}
     * @throws ResourceClassNotFoundException
     * @throws \LogicException if assumption proves wrong
     */
    protected function filterProperty(string $parameter, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        $filters = $this->getFilters($resourceClass, $operationName);

        if ($parameter == 'and') {
            return [$this->applyLogic($filters, 'and', $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)];

        }
        if ($parameter == 'or') {
            return [$this->applyLogic($filters, 'or', $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)];
        }
        return null;
    }

    /**
     * Applies filters in logic context
     * @param FilterInterface[] $filters to apply in context of $operator
     * @param string $operator 'and' or 'or
     * @return mixed Valid argument for Expr\Andx::add and Expr\Orx::add
     * @throws \LogicException if assumption proves wrong
     */
    private function applyLogic($filters, $operator, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)
    {
        $subFilters = $context['filters'][$operator];
        // print json_encode($subFilters, JSON_PRETTY_PRINT);
        $assoc = [];
        $logic = [];
        $expressions = [];
        foreach ($subFilters as $key => $value) {
            if (ctype_digit((string) $key)) {
                // allows the same filter to be applied several times, usually with different arguments
                $subcontext = $context; //copies
                $subcontext['filters'] = $value;
                $expressions = array_merge(
                    $expressions,
                    $this->collectExpressions($filters, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext)
                );

                // apply logic seperately
                if (isset($value['and'])) {
                    $logic[]['and'] =  $value['and'];
                }if (isset($value['or'])) {
                    $logic[]['or'] =  $value['or'];
                }
            } elseif (in_array($key, ['and', 'or'])) {
                $logic[][$key] = $value;
            } else {
                $assoc[$key] = $value;
            }
        }

        // Process $assoc
        $subcontext = $context; //copies
        $subcontext['filters'] = $assoc;
        $expressions = array_merge(
            $expressions,
            $this->collectExpressions($filters, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext)
        );

        $result = $operator == 'and'
            ? new Expr\Andx($expressions)
            : new Expr\Orx($expressions);

        // Process logic
        foreach ($logic as $eachLogic) {
            $subcontext = $context; //copies
            $subcontext['filters'] = $eachLogic;
            $newWhere = $this->applyLogic($filters, key($eachLogic), $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext);
            $result->add($newWhere); // empty expressions are ignored by ::add
        }

        return $result; // may be empty
    }

    /** Calls ::generateExpressions on each filter in $filters */
    private function collectExpressions($filters, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)
    {
        $expressions = [];
        foreach ($filters as $filter) {
            $expressions = array_merge(
                $expressions,
                $filter->generateExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)
            );
        }
        return $expressions;
    }

    /**
     * @param string $resourceClass
     * @param string $operationName
     * @return FilterInterface[] From resource except $this and OrderFilters
     * @throws ResourceClassNotFoundException
     */
    protected function getFilters($resourceClass, $operationName)
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        $resourceFilters = $resourceMetadata->getCollectionOperationAttribute($operationName, 'filters', [], true);

        $result = [];
        foreach ($resourceFilters as $filterId) {
            $filter = $this->filterLocator->has($filterId)
                ? $this->filterLocator->get($filterId)
                :  null;
            if ($filter instanceof FilterInterface
                && !($filter instanceof OrderFilter)
                && $filter !== $this
                && preg_match($this->classExp, get_class($filter))
            ) {
                $result[$filterId] = $filter;
            }
        }
        return $result;
    }
}