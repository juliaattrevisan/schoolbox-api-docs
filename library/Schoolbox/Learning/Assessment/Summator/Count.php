<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;

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
    protected function aggregate($val, $assessableWeight)
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
        return true;
    }
}
