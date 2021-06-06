<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ContextAwareFilterInterface;
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
    /** @var ContextAwareFilterInterface[] */
    private $filters;

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
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            throw new \InvalidArgumentException('::apply without $context[filters] not supported');
        }
        $this->filters = $this->getFilters($resourceClass, $operationName);

        if (isset($context['filters']['and']) ) {
            $expressions = $this->filterProperty('and', $context['filters']['and'], $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            foreach($expressions as $exp) {
                $queryBuilder->andWhere($exp);
            };
        }
        if (isset($context['filters']['not']) ) {
            // NOT expressions are combined by parent logic, here defaulted to AND
            $expressions = $this->filterProperty('not', $context['filters']['not'], $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            foreach($expressions as $exp) {
                $queryBuilder->andWhere(new Expr\Func('NOT', [$exp]));
            };
        }
        if (isset($context['filters']['or'])) {
            $expressions = $this->filterProperty('or', $context['filters']['or'], $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            foreach($expressions as $exp) {
                $queryBuilder->orWhere($exp);
            };
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateExpressions(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            throw new \InvalidArgumentException('::generateExpressions without $context[filters] not supported');
        }
        $this->filters = $this->getFilters($resourceClass, $operationName);
        return $this->doGenerate($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    protected function doGenerate($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)
    {
        $assoc = [];
        $logic = [];
        $expressions = [];
        foreach ($context['filters'] as $key => $value) {
            if (ctype_digit((string) $key)) {
                // allows the same filter to be applied several times, usually with different arguments
                $subcontext = $context; //copies
                $subcontext['filters'] = $value;
                $expressions = array_merge(
                    $expressions,
                    $this->collectExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext)
                );

                // apply logic seperately
                if (isset($value['and'])) {
                    $logic[]['and'] =  $value['and'];
                }if (isset($value['or'])) {
                    $logic[]['or'] =  $value['or'];
                }if (isset($value['not'])) {
                    $logic[]['not'] =  $value['not'];
                }
            } elseif (in_array($key, ['and', 'or', 'not'])) {
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
            $this->collectExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext)
        );

        // Process logic
        foreach ($logic as $eachLogic) {
            $subExpressions = $this->filterProperty(key($eachLogic), current($eachLogic), $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            if (key($eachLogic) == 'not') {
                // NOT expressions are combined by parent logic
                foreach ($subExpressions as $subExp) {
                    $expressions[] = new Expr\Func('NOT', [$subExp]);
                }
            } else {
                $expressions[] = key($eachLogic) == 'or'
                    ? new Expr\Orx($subExpressions)
                    : new Expr\Andx($subExpressions);
            }
        }

        return $expressions; // may be empty
    }

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, $context=[])
    {
        $subcontext = $context; //copies
        $subcontext['filters'] = $value;
        return $this->doGenerate($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $subcontext);
    }

    /** Calls ::generateExpressions on each filter in $filters */
    private function collectExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context)
    {
        $expressions = [];
        foreach ($this->filters as $filter) {
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
            if ($filter instanceof QueryExpressionGeneratorInterface
                && $filter instanceof ContextAwareFilterInterface
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