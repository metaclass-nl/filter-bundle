<?php


namespace Metaclass\FilterBundle\Filter;


use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\ExistsFilterTrait;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class EmptyOrNullFilter extends AbstractContextAwareFilter implements SearchFilterInterface
{
    private $searchFilter;
    private $existsFilter;

    use ExistsFilterTrait;

    public function __construct(ManagerRegistry $managerRegistry, ?RequestStack $requestStack, IriConverterInterface $iriConverter, PropertyAccessorInterface $propertyAccessor = null, LoggerInterface $logger = null, array $properties = null, IdentifiersExtractorInterface $identifiersExtractor = null, NameConverterInterface $nameConverter = null)
    {
        $this->existsParameterName = 'emptyOrNull';

        parent::__construct($managerRegistry, $requestStack, $logger, $properties, $nameConverter);

        if ($properties===null) {
            $searchFilterProps = null;
        } else {
            $searchFilterProps = array_map(function ($value) {
                return 'exact';
            },
                $properties
            );
        }
        $this->searchFilter = new SearchFilter($managerRegistry, $requestStack, $iriConverter, $propertyAccessor, $logger, $searchFilterProps, $identifiersExtractor, $nameConverter);
        $this->existsFilter = new ExistsFilter($managerRegistry, $requestStack, $logger, $properties, ExistsFilter::QUERY_PARAMETER_KEY, $nameConverter);
    }

    /** {@inheritdoc} */
    public function generateExpressions(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!\is_array($context['filters'][$this->existsParameterName] ?? null)) {
            return [];
        }

        $result = [];
        foreach ($context['filters'][$this->existsParameterName] as $property => $value) {
            $expressions = $this->filterProperty($this->denormalizePropertyName($property), $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            if ($expressions !== null) {
                $result = array_merge($result, $expressions);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        $value = $this->normalizeValue($value, $property);

        $searchContext = $context;
        $searchContext['filters'] = [$property => ''];
        $searchFilterExpressions = $this->searchFilter->generateExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $searchContext);

        $existsContext = $context;
        $existsContext['filters'] = [ExistsFilter::QUERY_PARAMETER_KEY => [$property => !$value]];
        $existsFilterExpressions = $this->existsFilter->generateExpressions($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $existsContext);

        if (empty($searchFilterExpressions)) {
            return $existsFilterExpressions;
        }

        if (empty($existsFilterExpressions)) {
            return $value
                ? $searchFilterExpressions
                : [$queryBuilder->expr()->not(...$searchFilterExpressions)];
        }

        return  [$value
            ? $queryBuilder->expr()->orX(
                ...$searchFilterExpressions,
                ...$existsFilterExpressions
            )
            // require not empty and not null
            : $queryBuilder->expr()->andX(
                $queryBuilder->expr()->not(...$searchFilterExpressions),
                ...$existsFilterExpressions
            )
        ];
    }

    protected function isNullableField(string $property, string $resourceClass): bool
    {
        // If not nullable it can still be empty so for now include all fields in the description
        // It would be better only to include nullable and string fields, but that would require
        // copying or inheriting ::isNullableField from ExistsFiler and to recognize string fields.
        // This shows that composition is still not applicable to the description.

        return true;
    }

}