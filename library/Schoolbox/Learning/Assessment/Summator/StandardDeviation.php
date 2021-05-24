<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;

/**
 * Standard Deviation Summator
 *
 * Calculates the standard deviation of all things it is to summate.
 *
 * Standard Deviation only makes sense for quantitative data: therefore,
 * summate() will return null for an assessable whose mark scheme is not
 * quantitative.
 */
class StandardDeviation extends AbstractSummator
{
    /** @var float[] */
    protected $allValues;

    /**
     * Standard Deviation doesn't use weighting.
     *
     * @inheritDoc
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
        $this->allValues[] = $val;
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->allValues = [];
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        $count = count($this->allValues);

        // There's no standard deviation between less than two values
        if ($count < 2) {
            return null;
        }

        $totalVariance = 0;
        $mean          = array_sum($this->allValues) / $count;
        foreach ($this->allValues as $mark) {
            $totalVariance += pow($mark - $mean, 2);
        }
        // Old markbook used population standard deviation, rather than sample
        // To use sample SD: subtract 1 from denominator
        return sqrt($totalVariance / $count);
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Standard Deviation';
    }

    /**
     * @inheritdoc
     */
    public function impliesOptionality()
    {
        return false;
    }
}
