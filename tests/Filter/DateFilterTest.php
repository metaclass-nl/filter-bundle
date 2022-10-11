<?php


namespace Metaclass\FilterBundle\Tests\Filter;


use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use Metaclass\FilterBundle\Filter\DateFilter as AdaptedDateFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Metaclass\FilterBundle\Entity\TestEntity;
use Metaclass\FilterBundle\Filter\FilterLogic;
use Metaclass\FilterBundle\Tests\Utils\Reflection;

class DateFilterTest extends KernelTestCase
{
    /** @var ManagerRegistry doctrine */
    private $doctrine;
    /** @var  */
    private $repo;
    /** @var QueryNameGenerator  */
    private $queryNameGen;
    /** @var \Doctrine\ORM\QueryBuilder */
    private $qb;
    /** @var FilterInterface */
    private $dateFilter;
    private $adaptedDateFilter;
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
        $requestStack = null;
        $logger = null;
        $nameConverter = null;

        $this->filterLogic = new FilterLogic($filterLocator, $this->doctrine, $logger, []);
        $this->dateFilter = new DateFilter($this->doctrine, $logger, ['dd' => DateFilterInterface::EXCLUDE_NULL]);
        $this->adaptedDateFilter = new AdaptedDateFilter($this->doctrine, $logger, ['dd' => DateFilterInterface::EXCLUDE_NULL]);
    }

    public function testExcludeNull()
    {
        $reqData = [];
        parse_str('dd[before]=2021-01-01&dd[after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $qb2 = $this->repo->createQueryBuilder('o');
        $qng2 = new QueryNameGenerator();

        $this->dateFilter->apply($this->qb, $this->queryNameGen, TestEntity::class, null, $context);
        $this->adaptedDateFilter->apply($qb2, $qng2, TestEntity::class, null, $context);

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o WHERE
 (o.dd IS NOT NULL AND o.dd <= :dd_p1) 
AND
 (o.dd IS NOT NULL AND o.dd >= :dd_p2)
"),
            $qb2->getDQL(),
            'DQL adaptedDateFilter produces 2 semantically complete expressions');

        $this->assertNotEquals(
            $this->qb->getDQL(),
            $qb2->getDQL(),
            'dql adapted against original'
        );

/*
        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o WHERE
 o.dd IS NOT NULL 
AND
 o.dd <= :dd_p1
AND
 o.dd >= :dd_p2
"),
            $this->qb->getDQL(),
            'Datefilter produces 3 expessions that depend on one another and therefore are not Semantically not complete');
 */
    }

    public function testNoNullManagement()
    {
        $reqData = null;
        parse_str('dd[strictly_before]=2021-01-01&dd[after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];

        $dateFilter = new DateFilter($this->doctrine, null, ['dd' => null]);
        $dateFilter->apply($this->qb, $this->queryNameGen, TestEntity::class, null, $context);

        $adaptedDateFilter = new AdaptedDateFilter($this->doctrine, null, ['dd' => null]);
        $qb2 = $this->repo->createQueryBuilder('o');
        $qng2 = new QueryNameGenerator();
        $adaptedDateFilter->apply($qb2, $qng2, TestEntity::class, null, $context);

        $this->assertEquals(
            $this->qb->getDQL(),
            $qb2->getDQL(),
            'dql'
        );
    }

    public function testIncludeNullAfter()
    {
        $reqData = null;
        parse_str('dd[before]=2021-01-01&dd[strictly_after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];

        $dateFilter = new DateFilter($this->doctrine, null, ['dd' => DateFilterInterface::INCLUDE_NULL_AFTER]);
        $dateFilter->apply($this->qb, $this->queryNameGen, TestEntity::class, null, $context);

        $adaptedDateFilter = new AdaptedDateFilter($this->doctrine, null, ['dd' => DateFilterInterface::INCLUDE_NULL_AFTER]);
        $qb2 = $this->repo->createQueryBuilder('o');
        $qng2 = new QueryNameGenerator();
        $adaptedDateFilter->apply($qb2, $qng2, TestEntity::class, null, $context);

        $this->assertEquals(
            $this->qb->getDQL(),
            $qb2->getDQL(),
            'dql'
        );
    }

    public function testAdaptedWithFilterLogic()
    {
        Reflection::setProperty($this->filterLogic, 'filters', [$this->adaptedDateFilter]);
        $operator = 'or';
        $reqData = null;
        parse_str('or[dd][before]=2021-01-01&or[dd][after]=2021-03-03', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        $args = [$this->qb, $this->queryNameGen, TestEntity::class, null, $context];
        $result = Reflection::callMethod($this->filterLogic, 'doGenerate', $args);

        $this->assertEquals(
            1,
            count($result),
            "number of expressions");
        $this->assertEquals(
            str_replace('
', '', "(o.dd IS NOT NULL AND o.dd <= :dd_p1)
 OR (o.dd IS NOT NULL AND o.dd >= :dd_p2)"),
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

}