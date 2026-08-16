<?php

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
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
class Import implements IStrategy
{
    private const string PATH = '/socs/xml/cocurricular.ashx';
    /** Number of days before today included in the import window. */
    private const int FROM = -30;
    /** Number of days after today included in the import window. */
    private const int TO = 100;
    public const string EXTERNAL_ID_PREFIX = 'socs-co-curricular:';
    public const string SEASON_NAME = 'SOCS Co-Curricular';
    private const string CLUB_EXTERNAL_ID_PREFIX = 'club:';
    private const string EVENT_EXTERNAL_ID_PREFIX = 'event:';

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private Parser $parser,
        private Registry $registry,
        private MatchupMapper $matchupMapper,
        private TeamPlayerMapper $teamPlayerMapper,
        private TeamCoachMapper $teamCoachMapper,
        private CoachMapper $coachMapper,
        private UserMapper $userMapper,
        private Person $personLookup,
    ) {
    }

    /**
     * Download and validate every feed before importing any records.
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
        $events = $this->parser->parseEvents($eventsXml);
        $eventDates = [];
        foreach ($events as $event) {
            $eventDates[$event->start->getUsingFormat(Registry::SOCS_DATE_FORMAT)] = true;
        }
        $registersXml = [];
        foreach (array_keys($eventDates) as $eventDate) {
            $this->lock->refresh();
            $registersXml[] = $this->httpClient->get(
                self::PATH,
                ['data' => 'registers', 'startdate' => $eventDate],
            );
        }
        [$clubs, $events, $pupilIdsByClubId, $staffIdsByClubId] = $this->parser->parse(
            $clubsXml,
            $events,
            ...$registersXml,
        );

        $season = $this->registry->recallSeason();
        $teams = [];
        foreach ($clubs as $club) {
            $this->lock->refresh();
            $identity = self::CLUB_EXTERNAL_ID_PREFIX . $club->id;
            $sport = $this->registry->recallSport($club->getDisplayName(), $identity);
            $division = $this->registry->recallDivision($club->getDisplayName(), $sport, $season, $identity);
            $team = $this->registry->recallTeam($club->getDisplayName(), $division, $identity);
            $teams[$club->id] = $team;
        }

        foreach ($events as $event) {
            $this->lock->refresh();
            if (!isset($clubs[$event->clubId], $teams[$event->clubId])) {
                $this->log->info(sprintf(
                    'Skipping SOCS co-curricular event "%s" (%s) with unmatched ClubID %s',
                    $event->title,
                    $event->id,
                    $event->clubId,
                ));
                continue;
            }
            $this->importEvent($event, $teams[$event->clubId], $season);
        }

        foreach ($clubs as $club) {
            $this->lock->refresh();
            $this->replaceMembership(
                $teams[$club->id],
                $pupilIdsByClubId[$club->id] ?? [],
                $staffIdsByClubId[$club->id] ?? [],
            );
        }

        $this->log->info(sprintf(
            'SOCS co-curricular import complete; processed %d clubs and %d events',
            count($clubs),
            count($events),
        ));
    }

    /**
     * Import one event occurrence as a matchup.
     */
    private function importEvent(
        Event $event,
        \Schoolbox\Fixtures\Team\Model $team,
        \Schoolbox\Fixtures\Season\Model $season,
    ): void {
        $externalId = self::EXTERNAL_ID_PREFIX . self::EVENT_EXTERNAL_ID_PREFIX . $event->id;
        try {
            $matchup = $this->matchupMapper->findOneByColumn('external_id', $externalId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $matchup = $this->matchupMapper->createModel();
        }
        $venue = $this->registry->recallVenue($event->location !== '' ? $event->location : 'Unspecified location');
        $matchup->setDivision($team->getDivision())->setSeason($season)->setVenue($venue)
            ->setName($event->title)->setStartAt($event->start)->setFinishAt($event->finish)
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
            $this->lock->refresh();
            $user = $this->personLookup->fetchUser($pupilId);
            if ($user === null) {
                $this->log->info(sprintf(
                    'SOCS co-curricular pupil %s has no matching Schoolbox user; skipping',
                    $pupilId,
                ));
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
            $this->lock->refresh();
            $user = $this->personLookup->fetchUser($staffId);
            if ($user === null) {
                $this->log->info(sprintf(
                    'SOCS co-curricular staff %s has no matching Schoolbox user; skipping',
                    $staffId,
                ));
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
}
