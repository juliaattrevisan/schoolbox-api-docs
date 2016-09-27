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
use Schoolbox\Learning\MarkScheme\IMarkScheme;

class Model extends AbstractFeedback
{

    /**
     * Calculate the final mark for this IAssessable
     *
     * This object's existence indicates a Teacher has marked the task as 'Complete'.
     *
     * While logically this equates to IMarkScheme::NORMALISED_MARK_MAX, for now we
     * return null to simplify max/min aggregation logic.
     *
     * @return float
     */
    public function getNormalisedMark()
    {
        return null;
    }
}