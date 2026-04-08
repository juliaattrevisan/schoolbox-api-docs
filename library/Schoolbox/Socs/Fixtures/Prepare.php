<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
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
        private MatchupMapper $matchupMapper
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

            $db = $this->matchupMapper->getDatabase();

            // Mark existing data as "stale" until they are
            // re-saved by the current import run.
            $db->query("UPDATE sportsfixtures_match SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_sport SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_season SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_division SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_team SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_venue SET updated_at = '0000-00-00 00:00:00'");
            $db->query("UPDATE sportsfixtures_school SET updated_at = '0000-00-00 00:00:00'");

            /* Hard deletions for relationships that do not use updated_at. */
            $teamCoachMapper = TeamCoachMapper::getInstance();
            $teamPlayerMapper = TeamPlayerMapper::getInstance();
            $db->query("DELETE FROM {$teamCoachMapper->getTable()}");
            $db->query("DELETE FROM {$teamPlayerMapper->getTable()}");
        }

        $this->log->info('Preparing previously-imported SOCS matchups for create/update.');
    }
}
