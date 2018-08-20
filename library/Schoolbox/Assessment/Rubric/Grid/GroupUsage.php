<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

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
     * Given a set of levels, retrieves an iterator for the rows in this group
     * usage.
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
