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
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * SOCS fixtures prepare step.
 *
 * Marks previously-imported fixtures and sports for potential removal by
 * setting their updated_at timestamps to a sentinel value. Records that are
 * seen and saved again during the current import will have their timestamps
 * refreshed, allowing the cleanup step to soft-delete only those that did not
 * appear in the latest SOCS feed.
 */
class Prepare implements IStrategy
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
        private SchoolMapper $schoolMapper,
        private TeamCoachMapper $teamCoachMapper,
        private TeamPlayerMapper $teamPlayerMapper,
    ) {
    }

    /**
     * Soft-delete preparation for SOCS fixtures.
     *
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \PDOException When the underlying database query fails
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();

            // Mark existing data as "stale" until they are
            // re-saved by the current import run.
            $this->matchupMapper->getDatabase()->query("UPDATE {$this->matchupMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->sportMapper->getDatabase()->query("UPDATE {$this->sportMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->seasonMapper->getDatabase()->query("UPDATE {$this->seasonMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->divisionMapper->getDatabase()->query("UPDATE {$this->divisionMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->teamMapper->getDatabase()->query("UPDATE {$this->teamMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->venueMapper->getDatabase()->query("UPDATE {$this->venueMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->schoolMapper->getDatabase()->query("UPDATE {$this->schoolMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

            /* Hard deletions for relationships that do not use updated_at. */
            $db = $this->teamMapper->getDatabase();
            $db->query("DELETE FROM {$this->teamCoachMapper->getTable()}");
            $db->query("DELETE FROM {$this->teamPlayerMapper->getTable()}");
        }

        $this->log->info('Preparing previously-imported SOCS matchups for create/update.');
    }
}
