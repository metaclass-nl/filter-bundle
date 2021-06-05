<?php


namespace Metaclass\FilterBundle\Tests\Filter;

use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Metaclass\FilterBundle\Entity\TestEntity;
use Metaclass\FilterBundle\Filter\FilterLogic;
use Metaclass\FilterBundle\Tests\Utils\Reflection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FilterLogicTest extends KernelTestCase
{
    /** @var ManagerRegistry doctrine */
    private $doctrine;
    /** @var  */
    private $repo;
    /** @var QueryNameGenerator  */
    private $queryNameGen;
    /** @var \Doctrine\ORM\QueryBuilder */
    private $qb;
    /** @var FilterInterface[] */
    private $filters;
    /** @var FilterLogic */
    private $filterLogic;

    public function setUp()
    {
        self::bootKernel();

        $this->doctrine =  self::$container->get('doctrine');
        $this->repo = $this->doctrine->getRepository(TestEntity::class);
        $this->qb = $this->repo->createQueryBuilder('o');
        $this->queryNameGen = new QueryNameGenerator();

        $metadataFactory = self::$container->get('api_platform.metadata.resource.metadata_factory');
        $filterLocator = self::$container->get('api_platform.filter_locator');
        $requestStack = null;
        $logger = null;
        $nameConverter = null;
        $iriConverter = self::$container->get('api_platform.iri_converter');

        $this->filterLogic = new FilterLogic($metadataFactory, $filterLocator, '//', $this->doctrine, $requestStack, $logger, []);
        $this->filters[] = new DateFilter($this->doctrine, $requestStack, $logger, ['dd' => null]);
        $this->filters[] = new NumericFilter($this->doctrine, $requestStack, $logger, ['numb' => null]);
        $this->filters[] = new RangeFilter($this->doctrine, $requestStack, $logger, ['numb' => null]);
        $this->filters[] = new SearchFilter($this->doctrine, $requestStack, $iriConverter, null, $logger, ['text' => null], null, $nameConverter);
        $this->filters[] = new ExistsFilter($this->doctrine, $requestStack, $logger, ['bool' => null, 'dd' => null]);
        $this->filters[] = new BooleanFilter($this->doctrine, $requestStack, $logger, ['bool' => null]);
        Reflection::setProperty($this->filterLogic, 'filters', $this->filters);
    }

    public function testWithDateFilterTwoAssoc()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.dd <= :dd_p1 OR o.dd >= :dd_p2"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
    }

    public function testWithTwoNumeric()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('page=1&or[][dd][before]=2021-01-01&or[][dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.dd <= :dd_p1 OR o.dd >= :dd_p2"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
    }

    public function testWithTwoMixed()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('page=1&or[][dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.dd <= :dd_p1 OR o.dd >= :dd_p2"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
    }

    public function testWithNumberFilterNestedOrAssoc()
    {
        $operator = 'and';
        $reqData = null;
        parse_str('and[numb]=7.2&and[or][dd][before]=2021-01-01&and[or][dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.numb = :numb_p1 AND (o.dd <= :dd_p2 OR o.dd >= :dd_p3)"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            7.2,
            $this->qb->getParameter('numb_p1')->getValue(),
            'Parameter numb_p1');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p3')->getValue()->format('Y-m-d'),
            'Parameter dd_p3');
    }

    public function testWithNestedOrNumeric()
    {
        $operator = 'and';
        $reqData = null;
        parse_str('and[numb]=7.2&and[0][or][dd][before]=2021-01-01&and[0][or][dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.numb = :numb_p1 AND (o.dd <= :dd_p2 OR o.dd >= :dd_p3)"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            7.2,
            $this->qb->getParameter('numb_p1')->getValue(),
            'Parameter numb_p1');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p3')->getValue()->format('Y-m-d'),
            'Parameter dd_p3');
    }

    public function testWithNestedOrMixed()
    {
        $operator = 'and';
        $reqData = null;
        parse_str('and[numb]=7.2&and[or][dd][before]=2021-01-01&and[0][or][dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        // Two seperate ors with each a single criterium are both ignoored
        $this->assertEquals(
            str_replace('
', '', "o.numb = :numb_p1 AND o.dd <= :dd_p2 AND o.dd >= :dd_p3"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            7.2,
            $this->qb->getParameter('numb_p1')->getValue(),
            'Parameter numb_p1');
        $this->assertEquals(
            '2021-01-01',
            $this->qb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
        $this->assertEquals(
            '2021-03-03',
            $this->qb->getParameter('dd_p3')->getValue()->format('Y-m-d'),
            'Parameter dd_p3');
    }

    public function testWithRangeFilter()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[numb][lte]=55&or[numb][gt]=2.7', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.numb <= :numb_p1 OR o.numb > :numb_p2"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            55,
            $this->qb->getParameter('numb_p1')->getValue(),
            'Parameter numb_p1');
        $this->assertEquals(
            2.7,
            $this->qb->getParameter('numb_p2')->getValue(),
            'Parameter numb_p2');
    }

    public function testWithSearchFilter()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[][numb]=55&or[][numb]=2.7', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            "o.numb = :numb_p1 OR o.numb = :numb_p2",
            $result[0],
            'expression 0');
        $this->assertEquals(
            55,
            $this->qb->getParameter('numb_p1')->getValue(),
            'Parameter numb_p1');
        $this->assertEquals(
            2.7,
            $this->qb->getParameter('numb_p2')->getValue(),
            'Parameter numb_p2');
    }

    public function testWithExistsFilter()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[exists][bool]=true&or[exists][dd]=false', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.bool IS NOT NULL OR o.dd IS NULL"),
            (string) $result[0],
            'DQL');
    }

    public function testWithBooleanFilter()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[][bool]=true&or[][bool]=false', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, 'get', $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "o.bool = :bool_p1 OR o.bool = :bool_p2"),
            (string) $result[0],
            'DQL');
        $this->assertEquals(
            true,
            $this->qb->getParameter('bool_p1')->getValue(),
            'Parameter bool_p1');
        $this->assertEquals(
            false,
            $this->qb->getParameter('bool_p2')->getValue(),
            'Parameter bool_p2');
    }


}