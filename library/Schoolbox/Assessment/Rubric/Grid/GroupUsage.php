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
     * Iterates each criterion in this usage object's group.
     *
     * First iterates all criteria in use, then all criteria not in use.
     *
     * @return iterable|Criterion[]
     */
    public function retrieveCriteria(): iterable
    {
        $seenCriteria = [];
        foreach ($this->getCriterionUsages() as $criterionUsage) {
            $seenCriteria[$criterionUsage->getCriterion()->getId()] = true;
            yield $criterionUsage->getCriterion();
        }

        foreach ($this->getGroup()->getCriteria() as $criterion) {
            if (!isset($seenCriteria[$criterion->getId()])) {
                yield $criterion;
            }
        }
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
            $groupUsageMaxValue = $criterionUsage->calculateMaxValue();
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
