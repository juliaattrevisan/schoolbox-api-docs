<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;

/**
 * A marked rubric grid.
 *
 * This is a representation of the rubric grid as marked for a participant's
 * feedback.
 *
 * It contains:
 * * a feedback to which this is assigned (in practise, always a due work
 *   feedback as the grid may only exist on a due work)
 * * a grid
 * * a set of marked capabilities, which in turn contain marked indicators
 */
class MarkedGrid
{
    /**
     * @var IFeedback
     */
    private $feedback;


    /**
     * @var Grid
     */
    private $grid;


    /**
     * @var GenericCollection|CapabilityMark[]
     */
    private $capabilityMarks;


    /**
     * Constructor
     *
     * @param IFeedback $feedback
     * @param Grid      $grid
     */
    public function __construct(IFeedback $feedback, Grid $grid)
    {
        $this->feedback        = $feedback;
        $this->grid            = $grid;
        $this->capabilityMarks = new GenericCollection(CapabilityMark::class);
    }


    /**
     * @return IFeedback
     */
    public function getFeedback(): IFeedback
    {
        return $this->feedback;
    }


    /**
     * @return Grid
     */
    public function getGrid(): Grid
    {
        return $this->grid;
    }


    /**
     * @return CapabilityMark[]|GenericCollection
     */
    public function getCapabilityMarks(): GenericCollection
    {
        return $this->capabilityMarks;
    }


    /**
     * @param CapabilityMark[]|GenericCollection $capabilityMarks
     * @return static
     */
    public function setCapabilityMarks(GenericCollection $capabilityMarks): self
    {
        $this->capabilityMarks = $capabilityMarks;
        return $this;
    }
}
