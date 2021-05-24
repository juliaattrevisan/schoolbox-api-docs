<?php

/**
 * Average
 *
 * Takes an Average of all the most recent normalisedMark Feedbacks for every
 * IResponse that was spawned. Tasks always return null for its getNormalisedMark()
 * value, and are excluded from the Average calculations.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;

class Average extends AbstractSummator
{
    /** @var float */
    protected $sum;

    /** @var int */
    protected $numItems;

    /**
     * @inheritDoc
     *
     * Not used in plain ol' Average
     */
    protected function calculateTotalWeight(Collection $allAssessables)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    protected function aggregate($val, $assessableWeight)
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
        /* If there are no items, return null
         */
        if (!$this->numItems) {
            return null;
        }

        return (float)$this->sum / (float)$this->numItems;
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Average';
    }

    /**
     * @inheritdoc
     */
    public function impliesOptionality()
    {
        return false;
    }
}
