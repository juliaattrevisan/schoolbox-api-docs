<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IFeedback;

/**
 * A marked rubric grid.
 *
 * This is a representation of the rubric grid as assigned to an assessment.
 *
 * It contains:
 * * an feedback to which this is assigned (in practise, always a due work
 *   feedback as the grid may only exist on a due work)
 * * a grid
 * * a set of marked descriptors
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
     * @var GenericCollection|Mark[]
     */
    private $marks;


    /**
     * Constructor
     *
     * @param IFeedback $feedback
     * @param Grid      $grid
     */
    public function __construct(IFeedback $feedback, Grid $grid)
    {
        $this->feedback = $feedback;
        $this->grid     = $grid;
        $this->marks    = new GenericCollection(Mark::class);
    }


    /**
     * @return IFeedback
     */
    public function getFeedback(): IFeedback
    {
        return $this->feedback;
    }


    /**
     * @param IFeedback $feedback
     * @return static
     */
    public function setFeedback(IFeedback $feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }


    /**
     * @return Grid
     */
    public function getGrid(): Grid
    {
        return $this->grid;
    }


    /**
     * @param Grid $grid
     * @return static
     */
    public function setGrid(Grid $grid): self
    {
        $this->grid = $grid;
        return $this;
    }


    /**
     * @return Mark[]|GenericCollection
     */
    public function getMarks(): GenericCollection
    {
        return $this->marks;
    }


    /**
     * @param Mark[]|GenericCollection $marks
     * @return static
     */
    public function setMarks(GenericCollection $marks): self
    {
        $this->marks = $marks;
        return $this;
    }
}
