<?php


namespace Metaclass\FilterBundle\Tests\Filter;

use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
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

    public function setUp(): void
    {
        $kernel = static::bootKernel();
        $container = $kernel->getContainer();

        $this->doctrine =  $container->get('doctrine');
        $this->repo = $this->doctrine->getRepository(TestEntity::class);
        $this->qb = $this->repo->createQueryBuilder('o');
        $this->queryNameGen = new QueryNameGenerator();

        $filterLocator = $container->get('test.api_platform.filter_locator');
         $logger = null;
        $nameConverter = null;
        $iriConverter = $container->get('test.api_platform.iri_converter');

        $this->filterLogic = new FilterLogic($filterLocator, $this->doctrine, $logger, []);
        $this->filters[] = new DateFilter($this->doctrine, $logger, ['dd' => null]);
        $this->filters[] = new NumericFilter($this->doctrine, $logger, ['numb' => null]);
        $this->filters[] = new RangeFilter($this->doctrine, $logger, ['numb' => null]);
        $this->filters[] = new SearchFilter($this->doctrine, $iriConverter, null, $logger, ['text' => null, 'toOneNullable.text'=>'start', 'toMany.text'=>'exact'], $nameConverter);
        $this->filters[] = new ExistsFilter($this->doctrine, $logger, ['bool' => null, 'dd' => null, 'toOneNullable.dd'=>null, 'toMany.bool'=>null]);
        $this->filters[] = new BooleanFilter($this->doctrine, $logger, ['bool' => null]);
        $this->filters[] = new FilterToTestAssumptions();
        Reflection::setProperty($this->filterLogic, 'filters', $this->filters);
    }

    public function testWithDateFilterTwoAssoc()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];

        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        parse_str('or[][text]=Foo&or[][text]=Bar', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            "o.text = :text_p1 OR o.text = :text_p2",
            $result[0],
            'expression 0');
        $this->assertEquals(
            'Foo',
            $this->qb->getParameter('text_p1')->getValue(),
            'Parameter text_p1');
        $this->assertEquals(
            'Bar',
            $this->qb->getParameter('text_p2')->getValue(),
            'Parameter text_p2');
    }

    public function testNotWithSearchFilter()
    {
        $reqData = null;
        parse_str('not[][text]=Foo&not[][text]=Bar', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(2, count($result), 'number of expressions');
        $this->assertEquals(
            "NOT(o.text = :text_p1)",
            (string) $result[0],
            'expression 0');
        $this->assertEquals(
            "NOT(o.text = :text_p2)",
            (string) $result[1],
            'expression 1');
        $this->assertEquals(
            'Foo',
            $this->qb->getParameter('text_p1')->getValue(),
            'Parameter text_p1');
        $this->assertEquals(
            'Bar',
            $this->qb->getParameter('text_p2')->getValue(),
            'Parameter text_p2');
    }

    public function testOrNotWithSearchFilter()
    {
        $reqData = null;
        parse_str('or[not][][numb]=55&or[not][][numb]=2.7', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            "NOT(o.numb = :numb_p1) OR NOT(o.numb = :numb_p2)",
            (string) $result[0],
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

    public function testNotOrWithSearchFilter()
    {
        $reqData = null;
        parse_str('not[or][][numb]=55&not[or][][numb]=2.7', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            "NOT(o.numb = :numb_p1 OR o.numb = :numb_p2)",
            (string) $result[0],
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
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

    public function testAssumptionWhereNotSet()
    {
        $this->expectExceptionMessage("Assumpion failure, unexpected Expression: text = :text_p3");
        $this->expectException(\LogicException::class );
        $operator = 'or';
        $reqData = null;
        parse_str('or[setWhere][text]=foo&or[dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);
    }

    public function testAssumptionMarkerFirst()
    {
        $this->expectExceptionMessage("Assumpion failure, unexpected Expression: foo = :foo_p3");
        $this->expectException(\LogicException::class );
        $operator = 'or';
        $reqData = null;
        parse_str('or[dd][before]=2021-01-01&or[dd][after]=2021-03-03&or[insertBeforeMarker][foo]=bar', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);
    }

    public function testAssumptionWhereEmptyOrx()
    {
        $this->expectExceptionMessage("Assumpion failure, marker not found");
        $this->expectException(\LogicException::class );
        $operator = 'or';
        $reqData = null;
        parse_str('or[setWhereEmptyOrx]=foo&or[dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);
    }

    public function testInnerJoinsLeftWithSearchFilter()
    {
        $reqData = null;
        parse_str('and[][toMany.text]=Foo&and[][toOneNullable.text]=Bar', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            "toMany_a1.text = :text_p1 AND toOneNullable_a2.text LIKE CONCAT(:text_p2_0, '%')",
            (string) $result[0],
            'expression 0');
        $this->assertEquals(
            'Foo',
            $this->qb->getParameter('text_p1')->getValue(),
            'Parameter text_p1');
        $this->assertEquals(
            'Bar',
            $this->qb->getParameter('text_p2_0')->getValue(),
            'Parameter text_p2_0');

        $this->assertEquals(
            'SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o INNER JOIN o.toMany toMany_a1 INNER JOIN o.toOneNullable toOneNullable_a2',
            $this->qb->getDQL(),
            'DQL before replaceInnerJoinsByLeftJoins'
        );
        Reflection::callMethod(
            $this->filterLogic,
            'replaceInnerJoinsByLeftJoins',
            [$this->qb]);
        $this->assertEquals(
            'SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o LEFT JOIN o.toMany toMany_a1 LEFT JOIN o.toOneNullable toOneNullable_a2',
            $this->qb->getDQL(),
            'DQL after replaceInnerJoinsByLeftJoins'
        );
    }

    public function testInnerJoinsLeftWithExistsFilter()
    {
        $operator = 'or';
        $reqData = null;
        parse_str('or[exists][toMany.bool]=true&or[exists][toOneNullable.dd]=false', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(1, count($result), 'number of expressions');
        $this->assertEquals(
            str_replace('
', '', "toMany_a1.bool IS NOT NULL OR toOneNullable_a2.dd IS NULL"),
            (string) $result[0],
            'DQL');
    }


    #TODO: add tests for Exists filter with nested prop
}