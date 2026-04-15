<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Teams;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS teams cleanup step.
 *
 * Removes teams attached to the SOCS Fixtures season that were marked as
 * stale in the prepare step and not refreshed during the current import run.
 * This mirrors the Clipboard prepare/cleanup behaviour but is scoped to
 * SOCS-managed teams only.
 */
class Cleanup implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private TeamMapper $teamMapper
    ) {
    }

    /**
     * Remove SOCS-managed teams that did not appear in the latest import.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            // Soft-delete teams that were not touched during this import run by
            // marking those which still have the sentinel updated_at value.
            $sql = "UPDATE {$this->teamMapper->getTable()} t
                INNER JOIN sportsfixtures_division d ON t.division_id = d.id
                SET t.deleted_at = NOW()
                WHERE t.updated_at = '0000-00-00 00:00:00'";
            $this->teamMapper->getDatabase()->query($sql);
        }

        $this->log->info('Soft-deleted SOCS-managed teams that did not appear in the import.');
    }
}
