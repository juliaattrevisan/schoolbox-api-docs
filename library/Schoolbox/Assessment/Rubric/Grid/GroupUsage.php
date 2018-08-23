<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\RowSet\Criterion;
use Schoolbox\Assessment\Rubric\RowSet\Group;
use Schoolbox\GenericCollection;

/**
 * Usage of a group within a rubric grid.
 *
 * Represented on the rubric grid as a collection of rows.
 */
class GroupUsage
{
    /**
     * @var Group
     */
    private $group;


    /**
     * @var GenericCollection|CriterionUsage[]
     */
    private $criterionUsages;


    /**
     * The max value allowed to be set for this group in the grid.
     *
     * @var float|null
     */
    private $maxValue;


    /**
     * Constructor
     *
     * @param Group $group
     */
    public function __construct(Group $group)
    {
        $this->group           = $group;
        $this->criterionUsages = new GenericCollection(CriterionUsage::class);
    }


    /**
     * Return the criterion usage for a given criterion, or null if this group
     * doesn't use it.
     *
     * @param Criterion $criterion
     * @return null|CriterionUsage
     */
    public function retrieveCriterionUsage(Criterion $criterion): ?CriterionUsage
    {
        foreach ($this->criterionUsages as $criterionUsage) {
            if ($criterionUsage->getCriterion()->getId() === $criterion->getId()) {
                return $criterionUsage;
            }
        }
        return null;
    }


    /**
     * Given a set of levels, retrieves an iterator for the rows in this group
     * usage.
     *
     * @todo consider removing, as manual iteration is probably easier
     *
     * @param GenericCollection|Level[] $levels
     * @return iterable|?Descriptor[][] key: CriterionUsage
     */
    public function iterateCriteria(GenericCollection $levels): iterable
    {
        foreach ($this->criterionUsages as $criterionUsage) {
            yield $criterionUsage => $criterionUsage->iterateDescriptors($levels);
        }
    }


    /**
     * What's the inferred max value for this group?
     *
     * Differs from `getMaxValue` in that this is the inferred value from the
     * descriptors, instead of the actually set value.
     *
     * @return float
     */
    public function calculateMaxValue(): float
    {
        $maxValues = [];
        foreach ($this->criterionUsages as $criterionUsage) {
            $groupUsageMaxValue = $criterionUsage->getMaxValue();
            if (null !== $groupUsageMaxValue) {
                $maxValues[] = $groupUsageMaxValue;
            }
        }
        return !empty($maxValues) ? array_sum($maxValues) : 0;
    }


    /**
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }


    /**
     * @return GenericCollection|CriterionUsage[]
     */
    public function getCriterionUsages(): GenericCollection
    {
        return $this->criterionUsages;
    }


    /**
     * @param GenericCollection|CriterionUsage[] $criterionUsages
     * @return static
     */
    public function setCriterionUsages(GenericCollection $criterionUsages): self
    {
        $this->criterionUsages = $criterionUsages;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }


    /**
     * @param float|null $maxValue
     * @return static
     */
    public function setMaxValue(?float $maxValue): self
    {
        $this->maxValue = $maxValue;
        return $this;
    }
}
