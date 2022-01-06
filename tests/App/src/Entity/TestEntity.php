<?php

namespace Metaclass\FilterBundle\Tests\App\src\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class TestEntity
 * @package Metaclass\FilterBundle\Entity
 * @ORM\Entity
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