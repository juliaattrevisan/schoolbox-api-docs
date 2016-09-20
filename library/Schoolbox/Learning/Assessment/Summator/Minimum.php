<?php
/**
 * Minimum
 *
 * Calculate the lowest normalisedMark Feedback for the selected User
 *
 * Tasks do not participate in the Minimum calculation and
 * must be handled specially, as null is always less than any
 * numeric value. The minimum value of a set of values that
 * contain a mix of nulls and non-nulls should never be null.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use \Schoolbox\Learning\Assessment\AbstractResponse;
use \Schoolbox\Learning\Assessment\Participant;

class Minimum extends AbstractSummator
{
    /** @var float */
    protected $min;

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
    protected function aggregate($val, $assessableWeight, $totalWeight)
    {
        /* $min is being set for the first time to a non-null value
         * If you don't do this check for $min == null, summate() will
         * always return null (any actual value is never less than null)
         */
        if ($val < $this->min || $this->min == null) {
            $this->min = $val;
        }
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->min = null;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        return $this->min;
    }

}