<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment\Summator;

use \Schoolbox\Collection;
use \Schoolbox\User;

/**
 * Count Summator
 *
 * Calculates the number of things that can actually be summated.
 */
class Count extends AbstractSummator
{
    /** @var int */
    protected $count = 0;

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
    protected function aggregate($val, $assessableWeight, $totalWeight)
    {
        $this->count++;
    }

    /**
     * @inheritDoc
     */
    protected function reset()
    {
        $this->count = 0;
    }

    /**
     * @inheritDoc
     */
    protected function finalise()
    {
        return $this->count;
    }

    /**
     * @return string
     */
    public static function getTitle()
    {
        return 'Standard Deviation';
    }
}
