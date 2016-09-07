<?php
/**
 * Average
 *
 * Takes an Average of all the most recent normalisedMark Feedbacks for every
 * IResponse that was spawned. Tasks always return null for its getNormalisedMark()
 * value, and are excluded from the Average calculations.
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

class Average extends AbstractSummator
{
    /** @var float */
    protected $sum;

    /** @var int */
    protected $numItems;

    /**
     * @inheritDoc
     */
    protected function aggregate($val)
    {
        $this->sum += $val;
        $this->numItems++;
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->sum = null;
        $this->numItems = 0;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        return (float)$this->sum / (float)$this->numItems;
    }

}
