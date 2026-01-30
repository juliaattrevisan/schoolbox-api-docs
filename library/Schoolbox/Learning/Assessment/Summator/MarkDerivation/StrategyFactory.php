<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Summator\MarkDerivation;

/**
 * Factory for building strategies.
 */
class StrategyFactory
{
    /**
     * Build a strategy:
     * * if `$requireAllActivities` is true, build the `ZeroBeforeFeedback`
     *   strategy
     * * otherwise, build the `Standard` strategy
     *
     * @param bool $requireAllActivities
     * @return IStrategy
     */
    public function build(bool $requireAllActivities): IStrategy
    {
        return $requireAllActivities
            ? new ZeroBeforeFeedback()
            : new Standard();
    }
}
