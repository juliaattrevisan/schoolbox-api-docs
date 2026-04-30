<?php

/**
 * Eportfolio - retain soft deleted data for a certain time
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Strategy;

use Schoolbox\Admin\DataRetention\ValidStrats;
use Schoolbox\Config\ConfigMapper;

class Eportfolio extends Base
{
    protected string $retentionConfigKey = ConfigMapper::NAME_DATA_RETENTION_EPORTFOLIO;

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'Eportfolio Retention';
    }

    /**
     * @inheritDoc
     */
    public function getStrategyName(): string
    {
        return ValidStrats::EPORTFOLIO;
    }

    /**
     * @inheritDoc
     */
    protected function hasDataToDelete(): bool
    {
    }

    /**
     * @inheritDoc
     */
    protected function getIdsToDelete(): array
    {
    }

    /**
     *
     */
    protected function deleteChunk(array $chunk): void
    {
    }
}
