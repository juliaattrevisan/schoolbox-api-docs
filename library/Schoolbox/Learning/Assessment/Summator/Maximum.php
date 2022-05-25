<?php

/**
 * Maximum
 *
 * Calculate the highest normalisedMark Feedback for the selected User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Summator;

use Schoolbox\Collection;

class Maximum extends AbstractSummator
{

    /** @var float|null */
    protected ?float $max = null;

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
        if (null === $this->max) {
            $this->max = $val;
        } else {
            $this->max = max($this->max, $val);
        }
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
    public function impliesOptionality()
    {
        // Eg, project has two sub-assessments, and student completes first and gets 100%. They would not be expected to attempt second.
        return true;
    }
}
