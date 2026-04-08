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
 * Removes existing teams attached to the SOCS Fixtures season so that the
 * teams import can recreate the current set from the SOCS mso-sport feed.
 */
class Prepare implements IStrategy
{
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private SeasonMapper $seasonMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper
    ) {
    }

    /**
     * Delete teams for the SOCS Fixtures season before re-importing.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if (!$this->perform) {
            $this->log->info('Planning SOCS teams prepare; no data will be deleted.');
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
        $db = $this->teamMapper->getDatabase();
        $teamTable = $this->teamMapper->getTable();
        $divisionTable = $this->divisionMapper->getTable();

        // Delete teams whose divisions belong to the SOCS Fixtures season.
        $sql = "DELETE t FROM {$teamTable} t INNER JOIN {$divisionTable} d ON t.division_id = d.id WHERE d.season_id = {$seasonId}";
        $db->query($sql);

        $this->log->info('Deleted existing SOCS teams attached to the SOCS Fixtures season.');
    }
}
