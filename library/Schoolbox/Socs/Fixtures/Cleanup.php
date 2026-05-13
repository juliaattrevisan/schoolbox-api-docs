<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
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
        private MatchupMapper $matchupMapper,
        private SportMapper $sportMapper,
        private SeasonMapper $seasonMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper,
        private VenueMapper $venueMapper,
        private SchoolMapper $schoolMapper
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

            // Soft-delete SOCS matchups that were not touched during this
            // import run. Their related results are removed via mapper
            // callbacks when the matchup is soft-deleted.
            $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            // Soft-delete sports that were not touched during this import run.
            $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->venueMapper->getDatabase()->query("UPDATE {$this->venueMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
            $this->schoolMapper->getDatabase()->query("UPDATE {$this->schoolMapper->getTable()} SET deleted_at = NOW() WHERE updated_at = '0000-00-00 00:00:00'");
        }

        $this->log->info('Soft-deleting SOCS matchups that did not appear in the import.');
    }
}
