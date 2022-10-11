<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;


/**
 * Filter to be added as the last ApiFilter of an ApiResource, even after FilterLogic
 * Removes the Fake Left Join from the QueryBuilder that was placed by AddFakeLeftJoin.
 */
class RemoveFakeLeftJoin implements FilterInterface
{
    /** {@inheritdoc } */
    public function getDescription(string $resourceClass): array
    {
        // No description
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
       self::removeItFrom($queryBuilder);
    }

    /**
     * Removes the Fake Left Join from the $queryBuilder that was placed by AddFakeLeftJoin.
     * @param QueryBuilder $queryBuilder
     */
    public static function removeItFrom(QueryBuilder $queryBuilder)
    {
        $joinPart = $queryBuilder->getDQLPart('join');
        $result = [];
        foreach ($joinPart as $rootAlias => $joins) {
            foreach ($joins as $i => $joinExp) {
                if (AddFakeLeftJoin::$FAKEJOIN !== $joinExp->getJoin()) {
                    $result[$rootAlias][$i] = $joinExp;
                }
            }
        }
        $queryBuilder->add('join', $result);
    }
}