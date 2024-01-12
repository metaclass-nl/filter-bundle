<?php


namespace Metaclass\FilterBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class TestRelated
 * @package Metaclass\FilterBundle\Entity
 * @ORM\Entity
 */
#[ORM\Entity]
class TestRelated
{
    /**
     * @var int The entity Id
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    public $id = 0;

    /**
     * @var float
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
     * @ORM\Column(type="boolean", nullable=true)
     */
    #[ORM\Column(type:"boolean", nullable:true)]
    public $bool;

    /**
     * @var TestEntity
     * @ORM\ManyToOne(targetEntity="Metaclass\FilterBundle\Entity\TestEntity", inversedBy="toMany")
     * @ORM\JoinColumn(referencedColumnName="id", nullable=false)
     */
    #[ORM\ManyToOne(targetEntity:"Metaclass\FilterBundle\Entity\TestEntity", inversedBy:"toMany")]
    #[ORM\JoinColumn(referencedColumnName:"id", nullable:false)]
    public $testEntity;
}