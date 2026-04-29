<?php

/**
 * Resources CMS retention strategy stub.
 *
 * This stub exists to support focused CMS strategy tests while the full
 * implementation is built in a later task.
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class Cms extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_CMS;

    public function getRetentionLabel(): string
    {
        return 'Resources (CMS) Retention';
    }

    public function getStrategyName(): string
    {
        return ValidStrats::CMS;
    }

    protected function hasDataToDelete(): bool
    {
        return false;
    }

    protected function getIdsToDelete(): array
    {
        return [];
    }

    protected function deleteChunk(array $chunk): void
    {
        // Task 3 will implement CMS deletion logic.
    }
}
