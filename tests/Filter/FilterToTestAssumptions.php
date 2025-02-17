<?php


namespace Metaclass\FilterBundle\Tests\Filter;


use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;

class FilterToTestAssumptions implements FilterInterface
{
    public function getDescription(string $resourceClass): array
    {
        return [];
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (isset($context['filters']['setWhere'])) {
            $field = key($context['filters']['setWhere']);
            $param = $queryNameGenerator->generateParameterName($field);
            $exp = $queryBuilder->expr()->eq(
                $field,
                ":$param");
            $queryBuilder->where($exp);
        }
        if (isset($context['filters']['setWhereEmptyOrx'])) {
            $exp = new Orx();
            $queryBuilder->where($exp);
        }
        if (isset($context['filters']['insertBeforeMarker'])) {
            $field = key($context['filters']['insertBeforeMarker']);
            $param = $queryNameGenerator->generateParameterName($field);
            $exp = $queryBuilder->expr()->eq(
                $field,
                ":$param");
            $oldWhere = $queryBuilder->getDQLPart('where');
            $parts = $oldWhere->getParts();
            array_unshift($parts, $exp);
            $expClass = get_class($oldWhere);
            $newWhere = new $expClass($parts);
            $queryBuilder->where($newWhere);
        }
    }
}