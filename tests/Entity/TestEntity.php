<?php


namespace Metaclass\FilterBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Metaclass\FilterBundle\Filter\FilterLogic;
use Metaclass\FilterBundle\Filter\AddFakeLeftJoin;
use Metaclass\FilterBundle\Filter\RemoveFakeLeftJoin;

/**
 * Class TestEntity
 * @package Metaclass\FilterBundle\Entity
 */
#[ORM\Entity]
#[ApiResource]
#[ApiFilter(DateFilter::class, properties: ["dd" => DateFilterInterface::INCLUDE_NULL_AFTER])]
#[ApiFilter(ExistsFilter::class, properties: ["dd", "bool", "toMany.bool"])]
#[ApiFilter(AddFakeLeftJoin::class)]
#[ApiFilter(SearchFilter::class, properties: ["toMany.text"])]
#[ApiFilter(RemoveFakeLeftJoin::class)]
#[ApiFilter(FilterLogic::class, arguments: ["innerJoinsLeft" => true])]
class TestEntity
{
    /**
     * @var int The entity Id
     *
     * ORM\Id
     * ORM\GeneratedValue
     * ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    public $id = 0;

    /**
     * @var float
     * ORM\Column(type="float")
     */
    #[ORM\Column(type:"float")]
    public $numb = 0.0;

    /**
     * @var string
     * ORM\Column
     */
    #[ORM\Column]
    public $text;

    /**
     * @var \DateTime|null
     * ORM\Column(type="date", nullable=true)
    */
    #[ORM\Column(type:"date", nullable:true)]
    public $dd;

    /**
     * @var bool|null
     * ORM\Column(type="boolean", nullable=true)
     */
    #[ORM\Column(type:"boolean", nullable:true)]
    public $bool;

    /**
     * @var TestRelated
     * ORM\ManyToOne(targetEntity="Metaclass\FilterBundle\Entity\TestRelated", inversedBy="toMany")
     */
    #[ORM\ManyToOne(targetEntity:"Metaclass\FilterBundle\Entity\TestRelated", inversedBy:"toMany")]
    public $toOneNullable;

    /**
     * @var Collection
     * ORM\OneToMany(targetEntity="Metaclass\FilterBundle\Entity\TestRelated", mappedBy="project")
     */
    #[ORM\OneToMany(targetEntity:"Metaclass\FilterBundle\Entity\TestRelated", mappedBy:"project")]
    public $toMany;
}