<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Descriptor;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * A rubric grid.
 *
 * This is a representation of the rubric grid as assigned to an assessment.
 *
 * It contains:
 * * an assessment to which this is assigned (in practise, always a due work)
 * * a row set
 * * a set of group usages
 * * a set of levels
 *
 * The grid's descriptors are able to be inferred from the grid's level set and
 * group usage set.
 */
class Grid
{
    /**
     * The assessment for which the grid exists.
     *
     * @var IAssessable
     */
    private $assessment;


    /**
     * The row set for the grid.
     *
     * @var RowSet
     */
    private $rowSet;


    /**
     * The groups that are actually in use on the rubric grid.
     *
     * Each group's criterion usage is a row on the rubric grid.
     *
     * @var GroupUsage[]|GenericCollection
     */
    private $groupUsages;


    /**
     * The levels in use on the rubric grid.
     *
     * Each level is a column on the rubric grid.
     *
     * @var Level[]|GenericCollection
     */
    private $levels;


    /**
     * Constructor
     *
     * @param IAssessable $assessment
     * @param RowSet      $rowSet
     */
    public function __construct(IAssessable $assessment, RowSet $rowSet)
    {
        $this->assessment  = $assessment;
        $this->rowSet      = $rowSet;
        $this->groupUsages = new GenericCollection(GroupUsage::class);
        $this->levels      = new GenericCollection(Level::class);
    }


    /**
     * Return a set of descriptors for a row.
     *
     * @param Criterion $criterion
     * @return GenericCollection|Descriptor[]
     */
    public function getRow(Criterion $criterion): GenericCollection
    {
        // TODO implement
        return new GenericCollection(Descriptor::class);
    }


    /**
     * Return a set of descriptors for a column.
     *
     * @param Level $level
     * @return GenericCollection
     */
    public function getColumn(Level $level): GenericCollection
    {
        // TODO implement
        return new GenericCollection(Descriptor::class);
    }


    /**
     * Return a descriptor for a row and column.
     *
     * @param Criterion $criterion
     * @param Level     $level
     * @return null|Descriptor
     */
    public function getDescriptor(Criterion $criterion, Level $level): ?Descriptor
    {
        // TODO implement
        return null;
    }


    /**
     * @return IAssessable
     */
    public function getAssessment(): IAssessable
    {
        return $this->assessment;
    }


    /**
     * @return RowSet
     */
    public function getRowSet(): RowSet
    {
        return $this->rowSet;
    }


    /**
     * @return GroupUsage[]|GenericCollection
     */
    public function getGroupUsages(): GenericCollection
    {
        return $this->groupUsages;
    }


    /**
     * @param GroupUsage[]|GenericCollection $groupUsages
     * @return static
     */
    public function setGroupUsages(GenericCollection $groupUsages): self
    {
        $this->groupUsages = $groupUsages;
        return $this;
    }


    /**
     * @return Level[]|GenericCollection
     */
    public function getLevels(): GenericCollection
    {
        return $this->levels;
    }


    /**
     * @param Level[]|GenericCollection $levels
     * @return static
     */
    public function setLevels(GenericCollection $levels): self
    {
        $this->levels = $levels;
        return $this;
    }
}
