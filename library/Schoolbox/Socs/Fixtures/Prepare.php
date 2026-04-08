<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS fixtures prepare step.
 *
 * Currently a no-op that exists to mirror the Clipboard importer structure
 * and keep the pipeline extensible for future soft-delete or mark-and-sweep
 * behaviour.
 */
class Prepare implements IStrategy
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

        $this->log->info('Preparing SOCS fixtures import (no-op).');
    }
}
