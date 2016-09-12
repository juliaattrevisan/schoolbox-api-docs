<?php
/**
 * WeightedAverage
 *
 * Takes a Weighted Average of all the most recent normalisedMark Feedbacks for every
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
use Schoolbox\Learning\Assessment\IAssessable;
use \Schoolbox\User;

class WeightedAverage
{
    /**
     * @var float
     */
    protected $sum;

    /**
     * @var int
     */
    protected $numItems;

    /**
     * Calculate the total weighting of all IAssessables
     *
     * @param Collection $allAssessables
     * @return int
     */
    protected function calculateTotalWeight(Collection $allAssessables)
    {
        $totalWeight = 0;

        /** @var IAssessable $currentAssessable */
        foreach ($allAssessables as $currentAssessable) {
            $totalWeight += $currentAssessable->getWeight();
        }

        return $totalWeight;
    }

    /**
     * @inheritDoc
     */
    protected function aggregate($val, $assessableWeight, $totalWeight)
    {
        /* TODO: Do weighted stuff here
         */
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
}
