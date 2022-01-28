<?php


namespace Metaclass\FilterBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Metaclass\FilterBundle\Filter\FilterLogic;
use Metaclass\FilterBundle\Filter\AddFakeLeftJoin;
use Metaclass\FilterBundle\Filter\RemoveFakeLeftJoin;

/**
 * Class TestEntity
 * @package Metaclass\FilterBundle\Entity
 * @ORM\Entity
 * @ApiResource
 * @ApiFilter(DateFilter::class, properties={"dd": DateFilterInterface::INCLUDE_NULL_AFTER})
 * @ApiFilter(ExistsFilter::class, properties={"dd", "bool", "toMany.bool"})
 * @ApiFilter(AddFakeLeftJoin::class)
 * @ApiFilter(SearchFilter::class, properties={"toMany.text"})
 * @ApiFilter(FilterLogic::class, arguments={"innerJoinsLeft"=true})
 * @ApiFilter(RemoveFakeLeftJoin::class)
 */
class TestEntity
{
    /**
     * @var int The entity Id
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id = 0;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    public $numb = 0.0;

    /**
     * @var string
     * @ORM\Column
     */
    public $text;

    /**
     * @var \DateTime|null
     * @ORM\Column(type="date", nullable=true)
    */
    public $dd;

    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true)
     */
    public $bool;

    /**
     * @var TestRelated
     * @ORM\ManyToOne(targetEntity="Metaclass\FilterBundle\Entity\TestRelated", inversedBy="toMany")
     */
    public $toOneNullable;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="Metaclass\FilterBundle\Entity\TestRelated", mappedBy="project")
     */
    public $toMany;
}