<?php
/**
 * Sum
 *
 * Calculate the Sum of the normalisedMarks for every Feedback
 * for a particular User.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use \Schoolbox\Learning\Assessment\AbstractResponse;
use \Schoolbox\Learning\Assessment\Participant;

class Sum extends AbstractSummator
{
    /** @var float */
    protected $sum;

    /**
     * @inheritDoc
     */
    protected function aggregate($val)
    {
        $this->sum += $val;
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->sum = null;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        return $this->sum;
    }

}