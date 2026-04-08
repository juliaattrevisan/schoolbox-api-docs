<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Teams;

use Psr\Log\LoggerInterface;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS teams cleanup step.
 *
 * Currently only refreshes the lock and logs completion of the teams import,
 * existing primarily to mirror the Clipboard importer structure and keep the
 * pipeline extensible.
 */
class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock
    ) {
    }

    /**
     * Finalise the SOCS teams import stage.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();
        }

        $this->log->info('SOCS teams cleanup complete.');
    }
}
