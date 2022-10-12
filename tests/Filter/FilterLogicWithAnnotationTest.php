<?php
namespace Metaclass\FilterBundle\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Operation;
use Doctrine\Persistence\ManagerRegistry;
use Metaclass\FilterBundle\Entity\TestEntity;
use Metaclass\FilterBundle\Filter\FilterLogic;
use Metaclass\FilterBundle\Tests\Utils\Reflection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FilterLogicWithAnnotationTest extends KernelTestCase
{
    /** @var ManagerRegistry doctrine */
    private $doctrine;
    /** @var  */
    private $testEntityRepo;
    /** @var QueryNameGenerator  */
    private $queryNameGen;
    /** @var \Doctrine\ORM\QueryBuilder */
    private $testEntityQb;
    /** @var FilterInterface[] */
    private $filters;
    /** @var FilterLogic */
    private $filterLogic;
    /** @var Operation */
    private $operation;

    public function setUp(): void
    {
        $kernel = static::bootKernel();
        $container = $kernel->getContainer();

        $this->doctrine =  $container->get('doctrine');
        $this->testEntityRepo = $this->doctrine->getRepository(TestEntity::class);
        $this->queryNameGen = new QueryNameGenerator();
        $this->testEntityQb = $this->testEntityRepo->createQueryBuilder('o');

        // Get FilterLocic service
        $metadataFactory = $container->get('test.api_platform.metadata.resource.metadata_collection_factory');
        $filterLocator = $container->get('test.api_platform.filter_locator');
        $resourceMetadata = $metadataFactory->create(TestEntity::class);
        $this->operation = $resourceMetadata->getOperation('', true, true);
        $resourceFilters = $this->operation->getFilters();

        foreach ($resourceFilters as $filterId) {
            $filter = $filterLocator->has($filterId)
                ? $filterLocator->get($filterId)
                : null;
            if (!($filter instanceof OrderFilter)) {
                $this->filters[$filterId] = $filter;
                if ($filter instanceof FilterLogic) {
                    $this->filterLogic = $filter;
                }
            }
        }
        self::assertNotNull($this->filterLogic, "this->filterLogic");
    }

    public function testNoLogic()
    {
        $reqData = null;
        parse_str('', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o"),
            $this->testEntityQb->getDQL(),
            'DQL');
    }

    public function testOrNoFilter()
    {
        $reqData = null;
        parse_str('or', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o"),
            $this->testEntityQb->getDQL(),
            'DQL');
    }

    public function testDdFilterAnd()
    {
        $reqData = null;
        parse_str('exists[bool]=true&and[or][dd][after]=2021-01-01', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o WHERE 
o.bool IS NOT NULL 
AND (o.dd >= :dd_p1 OR o.dd IS NULL)"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            '2021-01-01',
            $this->testEntityQb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');

    }

    public function testDdFilterOr()
    {
        $reqData = null;
        parse_str('exists[bool]=true&or[dd][after]=2021-01-01&or[dd][before]=2010-02-02', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o WHERE 
o.bool IS NOT NULL 
AND (
(o.dd <= :dd_p1 AND o.dd IS NOT NULL) 
OR (o.dd >= :dd_p2 OR o.dd IS NULL)
)"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            '2010-02-02',
            $this->testEntityQb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
        $this->assertEquals(
            '2021-01-01',
            $this->testEntityQb->getParameter('dd_p2')->getValue()->format('Y-m-d'),
            'Parameter dd_p2');
    }

    public function testRexExp()
    {
        $regExp = '/'. str_replace('\\', '\\\\', DateFilter::class). '/';
        Reflection::setProperty($this->filterLogic, 'classExp', $regExp);

        $reqData = null;
        parse_str('&and[or][dd][after]=2021-01-01&and[or][exists][bool]=true', $reqData);
        // var_dump($reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o WHERE 
o.dd >= :dd_p1 OR o.dd IS NULL"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            '2021-01-01',
            $this->testEntityQb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
    }

    public function testInnerJoinsLeftDdFilterOr()
    {
        $this->assertTrue(Reflection::getProperty($this->filterLogic, 'innerJoinsLeft'));
        $reqData = null;
        parse_str('exists[toMany.bool]=false&or[dd][before]=2010-02-02', $reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o 
LEFT JOIN o.toMany toMany_a1 
WHERE toMany_a1.bool IS NULL 
AND (o.dd <= :dd_p1 AND o.dd IS NOT NULL)"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            '2010-02-02',
            $this->testEntityQb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');
    }

    public function testInnerJoinsLeftNoLogic()
    {
        $this->assertTrue(Reflection::getProperty($this->filterLogic, 'innerJoinsLeft'));
        $reqData = null;
        parse_str('exists[toMany.bool]=false', $reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o 
INNER JOIN o.toMany toMany_a1 
WHERE toMany_a1.bool IS NULL"),
            $this->testEntityQb->getDQL(),
            'DQL');
    }


    public function testNoInnerJoinsLeftDdFilterOr()
    {
        Reflection::setProperty($this->filterLogic, 'innerJoinsLeft', false);
        $reqData = null;
        parse_str('exists[toMany.bool]=false&or[dd][before]=2010-02-02', $reqData);
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o 
INNER JOIN o.toMany toMany_a1 
WHERE toMany_a1.bool IS NULL 
AND (o.dd <= :dd_p1 AND o.dd IS NOT NULL)"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            '2010-02-02',
            $this->testEntityQb->getParameter('dd_p1')->getValue()->format('Y-m-d'),
            'Parameter dd_p1');

    }

    public function testWithFakeLeftJoin()
    {
        Reflection::setProperty($this->filterLogic, 'innerJoinsLeft', false);

        $reqData = ['toMany.text'=>'foo'];
        $context = ['filters' => $reqData];
        foreach ($this->filters as $filter) {
            $filter->apply($this->testEntityQb, $this->queryNameGen, TestEntity::class, $this->operation, $context);
        }

        $this->assertEquals(
            str_replace('
', '', "SELECT o FROM Metaclass\FilterBundle\Entity\TestEntity o 
LEFT JOIN o.toMany toMany_a1 
WHERE toMany_a1.text = :text_p1"),
            $this->testEntityQb->getDQL(),
            'DQL');
        $this->assertEquals(
            'foo',
            $this->testEntityQb->getParameter('text_p1')->getValue(),
            'Parameter text_p1');

    }
}