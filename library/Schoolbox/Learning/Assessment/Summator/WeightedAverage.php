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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;

class WeightedAverage extends AbstractSummator
{
    /**
     * @var array
     */
    protected $runningValues = [];

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
    protected function aggregate($val, $assessableWeight)
    {
        if (!is_null($val) && $assessableWeight > 0) {
            $this->runningValues[] = [
                'val'    => $val,
                'weight' => $assessableWeight,
            ];
        }
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->runningValues = [];
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        if (empty($this->runningValues)) {
            return null;
        }
        $totalWeight     = array_sum(array_column($this->runningValues, 'weight'));
        $weightedAverage = 0;
        foreach ($this->runningValues as $runningValue) {
            $weightedAverage += ($runningValue['val'] * ($runningValue['weight'] / $totalWeight));
        }
        return (float)$weightedAverage;
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Weighted Average';
    }

    /**
     * @inheritdoc
     */
    public function impliesOptionality()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function contribute(IAssessable $assessable): bool
    {
        return parent::contribute($assessable)
            && 0.0 < $assessable->getWeight();
    }
}
