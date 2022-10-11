<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;

/**
 * Filter to be added as the first ApiFilter of an ApiResource.
 * Adds a Fake Left Join to the QueryBuilder so that the filters that come
 * afterwards that use \ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryBuilderHelper::addJoinOnce
 * will use Left Joins instead of Inner Joins.
 * WARNING: This changes the behavior of ExistsFilter =false, consider
 * using ExistFilter included in this bundle instead.
 */
class AddFakeLeftJoin implements FilterInterface
{
    public static $FAKEJOIN = "Fake.kdoejfndnsklslwkweofdjhsd";

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
    ) {
       //  $queryBuilder->leftJoin(self::$FAKEJOIN, null);
        $rootAliases = $queryBuilder->getRootAliases();
        $join = new Join(
            Join::LEFT_JOIN,
            self::$FAKEJOIN
        );
        $queryBuilder->add('join', [$rootAliases[0] => $join], true);
    }
}