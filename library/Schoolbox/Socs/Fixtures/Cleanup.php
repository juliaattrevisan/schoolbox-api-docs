<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS fixtures cleanup step.
 *
 * Currently a no-op placeholder to align with the Clipboard importer
 * pattern. Future work may implement soft-delete or orphan cleanup.
 */
class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock
    ) {
    }

    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();
        }

        $this->log->info('Cleaning up after SOCS fixtures import (no-op).');
    }
}
