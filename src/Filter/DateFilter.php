<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter as ApipDateFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;


class DateFilter extends AbstractFilter implements DateFilterInterface
{
    public const DOCTRINE_DATE_TYPES = ApipDateFilter::DOCTRINE_DATE_TYPES;

    private ApipDateFilter $inner;

    public function __construct(ManagerRegistry $managerRegistry, LoggerInterface $logger = null, array $properties = null, NameConverterInterface $nameConverter = null)
    {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
        $this->inner = new ApipDateFilter($managerRegistry, $logger, $properties, $nameConverter);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        return $this->inner->getDescription($resourceClass);
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        // Expect $values to be an array having the period as keys and the date value as values
        if (!\is_array($values)) {
            return;
        }

        $contextCopy = $context;
        $contextCopy['filters'] = [$property => $values];
        $nullManagement = $this->properties[$property] ?? null;
        if (self::EXCLUDE_NULL !== $nullManagement) {
            $this->inner->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $contextCopy);
            return;
        }

        $oldWhere = $queryBuilder->getDQLPart('where');
        $queryBuilder->add('where', null);

        $this->inner->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $contextCopy);

        $expressions = $queryBuilder->getDQLPart('where')->getParts();
        $queryBuilder->add('where', $oldWhere);

        $isNotNull = array_shift($expressions);
        foreach ($expressions as $expr) {
            $queryBuilder->andWhere($queryBuilder->expr()->andX(
                $isNotNull,
                $expr
            ));
        }
    }
}