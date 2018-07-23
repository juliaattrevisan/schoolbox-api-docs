<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Criteria;

use Schoolbox\Assessment\Rubric\Calculable;
use Schoolbox\Assessment\Rubric\CalculableAggregator;
use Schoolbox\Assessment\Rubric\Twiggable;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Object implementation of the usage of a rubric group on a rubric grid, as
 * opposed to the group as available on a folder.
 */
class GroupUsage implements Twiggable, Calculable
{
    /**
     * @var Group
     */
    private $group;


    /**
     * The maximum allowable mark for this group.
     *
     * Usually this is determined from the group's criteria, but it's now
     * possible to override it.
     *
     * @var float
     */
    private $maxValue;


    /**
     * @var CriterionUsage[]|CalculableAggregator
     */
    private $criteria;

    
    /**
     * Constructor
     *
     * @param Group $group
     * @param null  $maxValue
     */
    public function __construct(Group $group, $maxValue = null)
    {
        $this->group    = $group;
        $this->maxValue = $maxValue;
        $this->criteria = new CalculableAggregator();
    }


    /**
     * @return CriterionUsage[]|CalculableAggregator
     */
    protected function getCriteria(): CalculableAggregator
    {
        return $this->criteria;
    }


    /**
     * @param CriterionUsage $criterion
     * @return $this
     */
    public function putCriterion(CriterionUsage $criterion): self
    {
        if (!$this->contains($criterion)) {
            $this->criteria->attach($criterion);
        }

        return $this;
    }


    /**
     * @param CriterionUsage $criterion
     * @return bool
     */
    public function contains(CriterionUsage $criterion): bool
    {
        return $this->criteria->contains($criterion);
    }


    /**
     * Return the calculated sum value of this Calculable (NOT the total possible value)
     *
     * @return float
     */
    public function getSum(): float
    {
        $sum = 0;

        foreach ($this->criteria as $crit) {
            $sum += $crit->getSum();
        }

        return $sum;
    }


    /**
     * Returns the total possible value of this Calculable
     *
     * @return float
     */
    public function getTotal(): float
    {
        $total = 0;

        foreach ($this->criteria as $crit) {
            $total += $crit->getTotal();
        }

        return $total;
    }


    /**
     * Get the maximum allowable mark value for this group.
     *
     * If this group has a max value, use that; otherwise, use the total from
     * its criteria.
     *
     * @return float
     */
    public function getMaxValue(): float
    {
        if (null !== $this->maxValue) {
            return $this->maxValue;
        }
        return $this->getTotal();
    }


    /**
     * @return float
     */
    public function getPercentage(): float
    {
        $total = $this->getTotal();
        if ($total) {
            return $this->getSum() / $total;
        }

        return 0;
    }

    /**
     * @return string
     */
    public function getHumanReadableMark(): string
    {
        return sprintf("%0.2d / %0.2d (%0.2d%%)", $this->getSum(), $this->getTotal(),
            round($this->getPercentage() * 100));
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext(): array
    {
        $context = [
            'id'                => $this->group->getId(),
            'name'              => $this->group->getName(),
            'sum'               => $this->getSum(),
            'total'             => $this->getTotal(),
            'maxValue'          => $this->getMaxValue(),
            'percentage'        => $this->getPercentage(),
            'humanReadableMark' => $this->getHumanReadableMark(),
            'used'              => $this->group->isReadOnly(),
            'criteria'          => [],
        ];

        foreach ($this->criteria as $criterion) {
            $context['criteria'][$criterion->getId()] = $criterion->toContext();
        }

        return $context;
    }


    /**
     * Returns a collection containing all the criteria in this grid, for conversion to ORMs for subsequent DB
     * persistence.
     *
     * @return Collection|CriterionUsage[]|GenericCollection
     */
    public function getAllCriteria(): GenericCollection
    {
        $c = new GenericCollection(CriterionUsage::class);

        foreach ($this->criteria as $crit) {
            $c->add($crit);
        }

        return $c;
    }

}
