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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;

class Minimum extends AbstractSummator
{

    /**
     * @var float|null
     */
    protected ?float $min = null;


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
        /* $min is being set for the first time to a non-null value
         * If you don't do this check for $min == null, summate() will
         * always return null (any actual value is never less than null)
         */
        if (null === $this->min || $val < $this->min) {
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
        return isset($this->min) ? floatval($this->min) : null;
    }


    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Minimum';
    }


    /**
     * @inheritdoc
     */
    public function impliesOptionality()
    {
        /* eg, project has 2 sub-assessments. Students completes first and gets 80%.
         * Student would still need to complete second to avoid getting 0%.
         */
        return false;
    }
}
