<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ContextAwareFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
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
class AddFakeLeftJoin implements ContextAwareFilterInterface
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
        string $operationName = null,
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