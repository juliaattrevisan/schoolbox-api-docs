<?php
/**
 * WeightedAverage
 *
 * Takes a Weighted Average of all the most recent normalisedMark Feedbacks for every
 * IResponse that was spawned. Tasks always return null for its getNormalisedMark()
 * value, and are excluded from the Average calculations.
 *
 * This might be helpful (yes, I know...)
 * https://support.microsoft.com/en-au/kb/214049
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\IAssessable;
use \Schoolbox\User;

class WeightedAverage extends AbstractSummator
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Weighted Average';

    /**
     * @var float
     */
    protected $weightedAverage;

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
        if (!is_null($val) && $totalWeight > 0) {
            $this->weightedAverage += ($val * ($assessableWeight / $totalWeight));

            /* A useful place to diagnose problems
             */
            //echo " $val*{$assessableWeight}/{$totalWeight} ";
        }
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->weightedAverage = null;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        if (is_null($this->weightedAverage)) {
            return null;
        }

        return (float)$this->weightedAverage;
    }
}
