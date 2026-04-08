<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Results;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS results cleanup step.
 *
 * Soft-deletes fixtures results that were marked as stale in the prepare step
 * and not refreshed during the current import run. This mirrors the
 * Clipboard Sessions cleanup behaviour, but is applied when SOCS results are
 * imported.
 */
class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private ResultMapper $resultMapper
    ) {
    }

    /**
     * Remove fixtures results that did not appear in the latest SOCS results feed.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            $db = $this->resultMapper->getDatabase();
            $table = $this->resultMapper->getTable();

            // Soft-delete results that were not touched during this import
            // run. Any results that were re-imported will have an updated
            // timestamp and are preserved.
            $db->query("UPDATE {$table} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Soft-deleting fixtures results that did not appear in the SOCS results import.');
    }
}
