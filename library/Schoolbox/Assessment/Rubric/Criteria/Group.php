<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric\Criteria;


use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\Calculable;
use Schoolbox\Assessment\Rubric\CalculableAggregator;
use Schoolbox\Assessment\Rubric\Twiggable;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

class Group implements Twiggable, Calculable
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /**
     * The maximum allowable mark for this group.
     *
     * Usually this is determined from the group's criteria, but it's now
     * possible to override it.
     *
     * @var float
     */
    protected $maxValue;

    /** @var Criterion[]|CalculableAggregator */
    protected $criteria;

    /** @var bool */
    protected $readOnly;

    /**
     * Insert convenience here
     *
     * @param int|null    $id
     * @param string|null $name
     * @param float|null  $maxValue
     * @param bool|null   $readOnly
     */
    public function __construct($id = null, $name = '', $maxValue = null, $readOnly = false)
    {
        $this->setName($name);
        $this->setId($id);
        $this->setReadOnly($readOnly);
        $this->maxValue = $maxValue;
        $this->criteria = new CalculableAggregator();
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }


    /**
     * @deprecated method for Mapper-use
     * @return bool
     */
    public function getReadOnly()
    {
        return $this->isReadOnly();
    }

    /**
     * @param boolean $readOnly
     * @return static
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;
        return $this;
    }


    /**
     * @return Criterion[]
     */
    protected function getCriteria()
    {
        return $this->criteria;
    }


    /**
     * @param Criterion $criterion
     * @return $this
     */
    public function putCriterion(Criterion $criterion)
    {
        if (!$this->contains($criterion)) {
            $this->criteria->attach($criterion);
        }

        return $this;
    }


    /**
     * @param Criterion $criterion
     * @return bool
     */
    public function contains(Criterion $criterion)
    {
        return $this->criteria->contains($criterion);
    }


    /**
     * The sum of all the highest descriptor values in this group
     *
     * @return float
     */
    private function calculateRowMaxSum() : float
    {
        $total = 0;

        foreach ($this->criteria as $crit) {
            $total += $crit->calculateMax();
        }

        return $total;
    }


    /**
     * Either:
     * - the custom maximum value specified by the user at assessment creation time, if present
     * - or otherwise, the sum of criteria in this group
     *
     * @return float
     */
    public function calculateMax() : float
    {
        if (null !== $this->maxValue) {
            return $this->maxValue;
        }
        return $this->calculateRowMaxSum();
    }


    /**
     * The sum of the descriptor values selected at marking time
     *
     * @return float
     */
    public function calculateMark() : float
    {
        $sum = 0;

        foreach ($this->criteria as $crit) {
            $sum += $crit->calculateMark();
        }

        return $sum;
    }

    public function calculateScaledMark() : float
    {
        return $this->calculateMark() * ($this->calculateMax() / $this->calculateRowMaxSum());
    }


    /**
     * @return float
     */
    public function getPercentage() : float
    {
        if ($total = $this->calculateMax()) {
            return $this->calculateMark() / $total;
        }

        return 0;
    }

    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        return sprintf("%0.2d / %0.2d (%0.2d%%)", $this->calculateScaledMark(), $this->calculateMax(), round($this->getPercentage() * 100));
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext()
    {
        $context = [
            'id'         => $this->getId(),
            'name'       => $this->getName(),
            'mark'        => $this->calculateScaledMark(),
            'total'      => $this->calculateMax(),
            'percentage' => $this->getPercentage(),
            'humanReadableMark' => $this->getHumanReadableMark(),
            'used'       => $this->isReadOnly(),
            'criteria'   => [],
        ];

        foreach ($this->criteria as $criterion) {
            $context['criteria'][$criterion->getId()] = $criterion->toContext();
        }

        return $context;
    }


    /**
     * Returns a collection containing all the criteria in this grid, for conversion to ORMs for subsequent DB persistence.
     *
     * @return Collection|Criterion[]
     */
    public function getAllCriteria()
    {
        $c = new GenericCollection(Criterion::class);

        foreach ($this->criteria as $crit) {
            $c->add($crit);
        }

        return $c;
    }

}
