<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Results;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS results prepare step.
 *
 * Marks all existing fixtures results as stale by setting their updated_at
 * timestamps to a sentinel value. Results written during the current run will
 * have their timestamps refreshed, allowing the cleanup step to soft-delete
 * only those that did not appear in the latest SOCS feed.
 */
class Prepare implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private ResultMapper $resultMapper
    ) {
    }

    /**
     * Soft-delete preparation for SOCS results.
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

            // Mark all existing results as "stale" until they are re-saved
            // by the current import run.
            $db->query("UPDATE sportsfixtures_result SET updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Preparing previously-imported fixtures results for SOCS results import.');
    }
}
