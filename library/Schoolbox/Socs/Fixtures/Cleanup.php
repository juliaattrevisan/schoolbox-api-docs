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
        private VenueMapper $venueMapper,
        private TeamMapper $teamsMapper,
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
            foreach (StaleSql::buildCleanupQueries($this->getStaleTables()) as $query) {
                $db->query($query);
            }
        }

        $this->log->info('Soft-deleting SOCS matchups that did not appear in the import.');
    }

    /**
     * @return array{matchup:string,sport:string,season:string,division:string,team:string,venue:string}
     */
    private function getStaleTables(): array
    {
        return [
            'matchup' => $this->matchupMapper->getTable(),
            'sport' => $this->sportMapper->getTable(),
            'season' => $this->seasonMapper->getTable(),
            'division' => $this->divisionMapper->getTable(),
            'venue' => $this->venueMapper->getTable(),
            'team' => $this->teamsMapper->getTable(),
        ];
    }
}
