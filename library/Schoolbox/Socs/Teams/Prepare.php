<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Teams;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS teams prepare step.
 *
 * Marks existing teams attached to the SOCS Fixtures season as stale by
 * setting their updated_at timestamps to a sentinel value. Teams that are
 * re-saved during the current import will have their timestamps refreshed,
 * allowing the cleanup step to remove only those that did not appear in the
 * latest SOCS teams feed.
 */
class Prepare implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private SeasonMapper $seasonMapper,
        private TeamMapper $teamMapper
    ) {
    }

    /**
     * Mark teams for the SOCS Fixtures season as stale before re-importing.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if (!$this->perform) {
            $this->log->info('Planning SOCS teams prepare; no data will be modified.');
            return;
        }

        $this->lock->refresh();

        try {
            $season = $this->seasonMapper->findOneByColumn('name', 'SOCS Fixtures');
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->info('No SOCS Fixtures season found; skipping teams prepare.');
            return;
        }

        $seasonId = (int)$season->getId();

        // Mark teams whose divisions belong to the SOCS Fixtures season as
        // "stale" until they are re-saved by the current import run.
        $sql = "UPDATE {$this->teamMapper->getTable()} t
            INNER JOIN sportsfixtures_division d ON t.division_id = d.id
            SET t.updated_at = '0000-00-00 00:00:00'
            WHERE d.season_id = {$seasonId}";
        $this->teamMapper->getDatabase()->query($sql);

        $this->log->info('Marked existing SOCS teams attached to the SOCS Fixtures season as stale.');
    }
}
