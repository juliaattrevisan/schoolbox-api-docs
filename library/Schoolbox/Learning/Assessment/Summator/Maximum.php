<?php
/**
 * Maximum
 *
 * Calculate the highest normalisedMark Feedback for the selected User
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

class Maximum extends AbstractSummator
{

    /** @var float */
    protected $max;

    /**
     * Weights are not used in this strategy
     *
     * @param Collection $allAssessables
     * @return void
     */
    protected function calculateTotalWeight(Collection $allAssessables)
    {
        // noop
    }

    /**
     * @inheritDoc
     */
    protected function aggregate($val, $assessableWeight)
    {
        $this->max = max($this->max, $val);
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->max = null;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        return isset($this->max) ? floatval($this->max) : null;
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Maximum';
    }

    /**
     * @inheritdoc
     */
    public function isAggregator()
    {
        return false;
    }
}
