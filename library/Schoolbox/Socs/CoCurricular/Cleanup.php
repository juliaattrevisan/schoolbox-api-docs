<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Socs\Fixtures\StaleSql;
use Schoolbox\Socs\IStrategy;
use Symfony\Component\Lock\LockInterface;

class Cleanup implements IStrategy
{
    private const string EXTERNAL_ID_PREFIX = 'socs-co-curricular:';
    private const string SEASON_NAME = 'SOCS Co-Curricular';

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private MatchupMapper $matchupMapper,
        private SportMapper $sportMapper,
        private SeasonMapper $seasonMapper,
        private DivisionMapper $divisionMapper,
        private VenueMapper $venueMapper,
        private TeamMapper $teamMapper,
    ) {
    }

    public function import(): void
    {
        if ($this->perform) {
            $this->lock->refresh();
            $queries = StaleSql::buildCleanupQueries(
                [
                    'matchup' => $this->matchupMapper->getTable(),
                    'sport' => $this->sportMapper->getTable(),
                    'season' => $this->seasonMapper->getTable(),
                    'division' => $this->divisionMapper->getTable(),
                    'venue' => $this->venueMapper->getTable(),
                    'team' => $this->teamMapper->getTable(),
                ],
                self::EXTERNAL_ID_PREFIX,
                self::SEASON_NAME,
            );
            foreach ($queries as $query) {
                $this->matchupMapper->getDatabase()->query($query);
            }
        }

        $this->log->info('Soft-deleting SOCS co-curricular records that did not appear in the import.');
    }
}
