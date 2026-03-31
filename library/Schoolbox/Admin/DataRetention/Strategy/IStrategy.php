<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Schoolbox\Admin\DataRetention\RetentionPeriod;

/**
 * Interface IStrategy
 *
 * Every data retention class must provide this method
 *
 * @package Schoolbox\Admin\DataRetention
 */
interface IStrategy
{
    /**
     * find and remove all soft deleted data
     */
    public function process(): void;

    /**
     * a human readable label for this strategy, used in the admin interface
     * @return string
     */
    public function getRetentionLabel(): string;

    /**
     * Get the strategy name, this is used to identify the strategy in the command line tool, e.g. 'cmslog'
     * @return string
     */
    public function getStrategyName(): string;

    /**
     * Override the retention period for this strategy, do NOT use the date range from the config
     * @param ?RetentionPeriod $retentionPeriod
     * @return self
     */
    public function setRetentionPeriod(?RetentionPeriod $retentionPeriod): self;
}
