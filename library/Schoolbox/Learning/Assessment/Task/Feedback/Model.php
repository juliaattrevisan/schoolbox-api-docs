<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;

/**
 * @method TaskResponse getResponse()
 */
class Model extends AbstractFeedback
{
    /**
     * Intentionally no setter(). The value is derive from the IResponse object.
     *
     * Create a new TaskResponse if you wish to change a student's completion status
     *
     * @return bool
     */
    public function getMarkedComplete()
    {
        return $this->getResponse()->getMarkedComplete();
    }


    /**
     * Calculate the final mark for this IAssessable
     *
     * This object's existence indicates a Teacher has marked the task as 'Complete'.
     *
     * While logically this equates to IMarkScheme::NORMALISED_MARK_MAX, for now we
     * return null to simplify mark summation logic.
     *
     * @return float
     */
    public function getNormalisedMark()
    {
        return null;
    }

    /**
     * Convenience method.
     *
     * Intentionally no setter(). That would potentially require implicit mutation of the submittedBy and submittedFor values.
     *
     * @return int
     */
    public function getCompletionStatus()
    {
        return $this->getResponse()->getCompletionStatus();
    }
}
