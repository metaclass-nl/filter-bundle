<?php

namespace Metaclass\FilterBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter as SuperClass;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;


class DateFilter extends SuperClass
{

    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        $nullManagement = $this->properties[$property] ?? null;
        if (self::EXCLUDE_NULL !== $nullManagement) {
            parent::filterProperty($property, $values, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName);
            return;
        }

        $oldWhere = $queryBuilder->getDQLPart('where');
        $queryBuilder->add('where', null);

        parent::filterProperty($property, $values, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName);

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