<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS fixtures cleanup step.
 *
 * Soft-deletes fixtures and sports that were marked as stale in the prepare
 * step and not refreshed during the current import run. This mirrors the
 * Clipboard Sessions cleanup behaviour but is scoped to SOCS-driven data
 * only.
 */
class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private MatchupMapper $matchupMapper
    ) {
    }

    /**
     * Remove SOCS matchups that did not appear in the latest import.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            $db = $this->matchupMapper->getDatabase();
            $table = $this->matchupMapper->getTable();

            // Soft-delete SOCS matchups that were not touched during this
            // import run. Their related results are removed via mapper
            // callbacks when the matchup is soft-deleted.
            $db->query("UPDATE {$table} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            // Soft-delete sports that were not touched during this import run.
            $db->query("UPDATE sportsfixtures_sport SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Soft-deleting SOCS matchups that did not appear in the import.');
    }
}
