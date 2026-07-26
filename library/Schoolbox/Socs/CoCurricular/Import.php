<?php

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Socs\Fixtures\Lookup\Person;
use Schoolbox\Socs\Fixtures\Registry;
use Schoolbox\Socs\Fixtures\StaleSql;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Import SOCS co-curricular clubs, events, and registrations as Fixtures.
 */
final class Import implements IStrategy
{
    private const string PATH = '/socs/xml/cocurricular.ashx';
    private const int FROM = -30;
    private const int TO = 100;
    public const string EXTERNAL_PREFIX = 'socs-co-curricular:';
    public const string SEASON = 'SOCS Co-Curricular';

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private Parser $parser,
        private Registry $registry,
        private MatchupMapper $matchupMapper,
        private SportMapper $sportMapper,
        private SeasonMapper $seasonMapper,
        private DivisionMapper $divisionMapper,
        private VenueMapper $venueMapper,
        private TeamMapper $teamMapper,
        private TeamPlayerMapper $teamPlayerMapper,
        private TeamCoachMapper $teamCoachMapper,
        private CoachMapper $coachMapper,
        private UserMapper $userMapper,
        private Person $personLookup,
    ) {
    }

    /**
     * Download and validate every feed before stale marking or cleanup.
     *
     * @throws \Throwable When a request or feed parse fails.
     */
    public function import(): void
    {
        $start = \Alaress_Date_Format::now()->addDays(self::FROM)->getUsingFormat(Registry::SOCS_DATE_FORMAT);
        $end = \Alaress_Date_Format::now()->addDays(self::TO)->getUsingFormat(Registry::SOCS_DATE_FORMAT);
        $this->log->info(sprintf('Downloading SOCS co-curricular feeds from %s to %s', $start, $end));

        $clubsXml = $this->httpClient->get(self::PATH, ['data' => 'clubs']);
        $eventsXml = $this->httpClient->get(self::PATH, ['data' => 'events', 'startdate' => $start, 'enddate' => $end, 'staff' => 1]);
        $registersXml = $this->httpClient->get(self::PATH, ['data' => 'registers', 'startdate' => $start, 'enddate' => $end]);
        $data = $this->parser->parse($clubsXml, $eventsXml, $registersXml);

        $season = $this->registry->recallSeason();
        if ($this->perform) {
            $this->lock->refresh();
            foreach (StaleSql::buildPrepareQueries($this->tables(), self::EXTERNAL_PREFIX, self::SEASON) as $query) {
                $this->matchupMapper->getDatabase()->query($query);
            }
        }

        $teams = [];
        foreach ($data->clubsById as $club) {
            $identity = 'club:' . $club->id;
            $sport = $this->registry->recallSport($club->getDisplayName(), $identity);
            $division = $this->registry->recallDivision($club->getDisplayName(), $sport, $season, $identity);
            $team = $this->registry->recallTeam($club->getDisplayName(), $division, $identity);
            $teams[$club->id] = $team;
        }

        foreach ($data->eventsById as $event) {
            if (!isset($data->clubsById[$event->clubId], $teams[$event->clubId])) {
                $this->log->warning(sprintf('Skipping SOCS co-curricular event %s with unmatched ClubID %s', $event->id, $event->clubId));
                continue;
            }
            $this->importEvent($event, $data->clubsById[$event->clubId], $teams[$event->clubId], $season);
        }

        foreach ($data->clubsById as $club) {
            $this->replaceMembership($teams[$club->id], $data->pupilIdsByClubId[$club->id] ?? [], $data->staffIdsByClubId[$club->id] ?? []);
        }

        if ($this->perform) {
            $this->lock->refresh();
            foreach (StaleSql::buildCleanupQueries($this->tables(), self::EXTERNAL_PREFIX, self::SEASON) as $query) {
                $this->matchupMapper->getDatabase()->query($query);
            }
        }
        $this->log->info(sprintf('SOCS co-curricular import complete; processed %d clubs and %d events', count($data->clubsById), count($data->eventsById)));
    }

    /**
     * Import one event occurrence as a matchup.
     */
    private function importEvent(Event $event, Club $club, \Schoolbox\Fixtures\Team\Model $team, \Schoolbox\Fixtures\Season\Model $season): void
    {
        $externalId = self::EXTERNAL_PREFIX . 'event:' . $event->id;
        try {
            $matchup = $this->matchupMapper->findOneByColumn('external_id', $externalId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $matchup = $this->matchupMapper->createModel();
        }
        $venue = $this->registry->recallVenue($event->location !== '' ? $event->location : 'Unspecified location');
        $matchup->setDivision($team->getDivision())->setSeason($season)->setVenue($venue)
            ->setName($event->title)->setStartAt(new \Alaress_Date_Format($event->start->format('Y-m-d H:i:s'), \Alaress_Date_Format::DATABASE_FORMAT))
            ->setFinishAt(new \Alaress_Date_Format($event->finish->format('Y-m-d H:i:s'), \Alaress_Date_Format::DATABASE_FORMAT))
            ->setNotes($event->recurringId !== '' ? 'SOCS recurring ID: ' . $event->recurringId : '')
            ->setHomeTeam($team)->setAwayTeam(null)->setHomeSchool(null)->setAwaySchool(null)->setExternalId($externalId);
        if ($this->perform) {
            $this->matchupMapper->save($matchup);
        }
    }

    /**
     * Replace membership from the source registration union.
     *
     * Registrations are event-specific, but Fixtures visibility is team-based. We
     * intentionally assign every matched pupil registered for an event of this
     * ClubID to the supporting team so all club events are visible to that pupil.
     *
     * @param string[] $pupilIds
     */
    private function replaceMembership(\Schoolbox\Fixtures\Team\Model $team, array $pupilIds, array $staffIds): void
    {
        if ($this->perform) {
            foreach (StaleSql::buildTeamMembershipDeleteQueries($this->teamPlayerMapper->getTable(), $this->teamCoachMapper->getTable(), (int)$team->getId()) as $query) {
                $this->teamPlayerMapper->getDatabase()->query($query);
            }
        }
        $sequence = 0;
        foreach ($pupilIds as $pupilId) {
            $user = $this->personLookup->fetchUser($pupilId);
            if ($user === null) {
                $this->log->warning(sprintf('SOCS co-curricular pupil %s has no matching Schoolbox user; skipping', $pupilId));
                continue;
            }
            $sequence++;
            $userModel = $this->userMapper->createModel()->setId((int)$user['id'])->setExternalId($user['external_id'])
                ->setFirstname($user['firstname'])->setLastname($user['lastname'])->setPreferredName($user['preferred_name']);
            if ($this->perform) {
                $this->teamPlayerMapper->save($this->teamPlayerMapper->createModel()->setTeam($team)->setUser($userModel)->setSequence($sequence));
            }
        }

        foreach ($staffIds as $staffId) {
            $user = $this->personLookup->fetchUser($staffId);
            if ($user === null) {
                $this->log->warning(sprintf('SOCS co-curricular staff %s has no matching Schoolbox user; skipping', $staffId));
                continue;
            }
            $coachName = trim(sprintf('%s %s', $user['preferred_name'] ?: $user['firstname'], $user['lastname']));
            try {
                $coach = $this->coachMapper->findOneByColumn('user_id', $user['id']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $userModel = $this->userMapper->createModel()->setId((int)$user['id'])
                    ->setExternalId($user['external_id'])->setFirstname($user['firstname'])
                    ->setLastname($user['lastname'])->setPreferredName($user['preferred_name']);
                $coach = $this->coachMapper->createModel()->setUser($userModel)->setName($coachName);
                if ($this->perform) {
                    $this->coachMapper->save($coach);
                }
            }
            try {
                $this->teamCoachMapper->findOneByColumnList(['team_id' => $team->getId(), 'coach_id' => $coach->getId()]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                if ($this->perform) {
                    $this->teamCoachMapper->save($this->teamCoachMapper->createModel()->setTeam($team)->setCoach($coach));
                }
            }
        }
    }

    /** @return array{matchup:string,sport:string,season:string,division:string,team:string,venue:string} */
    private function tables(): array
    {
        return ['matchup' => $this->matchupMapper->getTable(), 'sport' => $this->sportMapper->getTable(), 'season' => $this->seasonMapper->getTable(), 'division' => $this->divisionMapper->getTable(), 'team' => $this->teamMapper->getTable(), 'venue' => $this->venueMapper->getTable()];
    }
}
