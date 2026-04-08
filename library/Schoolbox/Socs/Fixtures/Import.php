<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as MatchupModel;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person as PersonLookup;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Import fixtures from the SOCS standard fixtures feed into Schoolbox Matchups.
 */
class Import implements IStrategy
{
    use RecallSupport;

    private const FIXTURES_PATH = 'fixturecalendar.ashx';

    /** @var array<string,int> */
    private array $userExternalIdLookup = [];

    public function __construct(
        private LoggerInterface $log,
        bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private SeasonMapper $seasonMapper,
        private SportMapper $sportMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper,
        private VenueMapper $venueMapper,
        private SchoolMapper $schoolMapper,
        private MatchupMapper $matchupMapper,
        private UserMapper $userMapper,
        private TeamPlayerMapper $teamPlayerMapper,
        private TeamCoachMapper $teamCoachMapper,
        private CoachMapper $coachMapper
    ) {
        $this->perform = $perform;
    }

    public function import(): void
    {
        $season = $this->recallSeason($this->seasonMapper, $this->log);

        $now = \Alaress_Date_Format::now();
        $startDate = (clone $now)->addDays(-30)->getUsingFormat('j M Y');
        $endDate = (clone $now)->addDays(100)->getUsingFormat('j M Y');

        $this->log->info(sprintf('Downloading SOCS fixtures from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
            'TS'        => 1,
        ];

        $response = $this->httpClient->get(self::FIXTURES_PATH, $query, $this->log);

        if (null === $response) {
            $this->log->info('SOCS fixtures no response; skipping');
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $this->log->warning(sprintf(
                'Bad SOCS response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $this->log->debug(sprintf(
            'SOCS fixtures raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $this->log->info('SOCS fixtures feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $this->log->warning(sprintf(
                'Unable to parse SOCS XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        $imported = 0;

        foreach ($xml->children() as $fixture) {
            $this->lock->refresh();

            $eventId = trim((string)$fixture->eventid);
            $sportName = trim((string)$fixture->sport);
            $teamName = trim((string)$fixture->team);
            $oppositionSchoolName = trim((string)$fixture->opposition);
            $locationName = trim((string)$fixture->location);
            $details = trim((string)$fixture->details);
            $date = trim((string)$fixture->date);
            $time = trim((string)$fixture->time);

            if ('' === $eventId || '' === $sportName || '' === $teamName || '' === $date) {
                $this->log->debug('Skipping SOCS fixture with missing mandatory fields');
                continue;
            }

            $sport = $this->recallSport($this->sportMapper, $this->log, $sportName);

            $divisionName = $teamName;
            $division = $this->recallDivision($this->divisionMapper, $this->log, $divisionName, $sport, $season);

            $team = $this->recallTeam($this->teamMapper, $this->log, $teamName, $division);

            $this->syncParticipantsWithTeam($fixture, $team);

            $venueName = $locationName !== '' ? $locationName : 'Unspecified location';
            $venue = $this->recallVenue($this->venueMapper, $this->log, $venueName);

            $awaySchool = null;
            if ($oppositionSchoolName !== '') {
                $awaySchool = $this->recallSchool($this->schoolMapper, $this->log, $oppositionSchoolName);
            }

            $startAt = $this->makeDateTime($date, $time, $this->log);
            if (null === $startAt) {
                $this->log->debug(sprintf('Skipping SOCS fixture %s due to invalid date/time', $eventId));
                continue;
            }

            $externalId = 'socs:' . $eventId;

            try {
                /** @var MatchupModel $matchup */
                $matchup = $this->matchupMapper->findOneByColumn('external_id', $externalId);
                $this->log->debug(sprintf('Updating SOCS fixture %s', $externalId));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $matchup = $this->matchupMapper->createModel();
                $this->log->debug(sprintf('Inserting SOCS fixture %s', $externalId));
            }

            $matchup
                ->setDivision($division)
                ->setSeason($season)
                ->setVenue($venue)
                ->setName($teamName)
                ->setStartAt($startAt)
                ->setNotes($details)
                ->setHomeTeam($team)
                ->setAwayTeam(null)
                ->setHomeSchool(null)
                ->setAwaySchool($awaySchool)
                ->setExternalId($externalId);

            if ($this->perform) {
                $this->matchupMapper->save($matchup);
            }

            $imported++;
        }

        if ($this->perform) {
            $this->log->info(sprintf('SOCS fixtures import complete; processed %d fixtures', $imported));
        } else {
            $this->log->info(sprintf('SOCS fixtures planning complete; would process %d fixtures', $imported));
        }
    }

    private function ensureUserLookupLoaded(): void
    {
        if ([] !== $this->userExternalIdLookup) {
            return;
        }

        $lookup = new PersonLookup($this->userMapper);
        $this->userExternalIdLookup = $lookup->generate();
    }

    private function syncParticipantsWithTeam(\SimpleXMLElement $fixture, \Schoolbox\Fixtures\Team\Model $team): void
    {
        $this->ensureUserLookupLoaded();

        $pupils = trim((string)($fixture->pupils ?? ''));
        $staff = trim((string)($fixture->staff ?? ''));

        if ('' === $pupils && '' === $staff) {
            return;
        }

        if ('' !== $pupils) {
            $this->syncPupilsWithTeam($pupils, $team);
        }

        if ('' !== $staff) {
            $this->syncStaffWithTeam($staff, $team);
        }
    }

    private function syncPupilsWithTeam(string $pupils, \Schoolbox\Fixtures\Team\Model $team): void
    {
        $externalIds = array_filter(array_map('trim', explode(',', $pupils)), 'strlen');

        if ([] === $externalIds) {
            return;
        }

        $sequence = 0;

        foreach ($externalIds as $externalId) {
            $this->lock->refresh();

            $userId = $this->userExternalIdLookup[$externalId] ?? null;
            if (null === $userId) {
                $this->log->debug(sprintf(
                    'SOCS pupil external id %s does not have a corresponding schoolbox user record; skipping',
                    $externalId
                ));

                continue;
            }

            $sequence++;
            $user = $this->userMapper->createModel()->setId((int)$userId);

            try {
                $this->teamPlayerMapper->findOneByColumnList([
                    'user_id' => $user->getId(),
                    'team_id' => $team->getId(),
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $teamPlayer = $this->teamPlayerMapper->createModel()
                    ->setUser($user)
                    ->setTeam($team)
                    ->setSequence($sequence);

                if ($this->perform) {
                    $this->teamPlayerMapper->save($teamPlayer);
                }

                $this->log->info(sprintf(
                    'Assigned SOCS pupil %s to team %s using schoolbox user %d',
                    $externalId,
                    $team->getName(),
                    $userId
                ));
            }
        }
    }

    private function syncStaffWithTeam(string $staff, \Schoolbox\Fixtures\Team\Model $team): void
    {
        $externalIds = array_filter(array_map('trim', explode(',', $staff)), 'strlen');

        if ([] === $externalIds) {
            return;
        }

        foreach ($externalIds as $externalId) {
            $this->lock->refresh();

            $userId = $this->userExternalIdLookup[$externalId] ?? null;
            if (null === $userId) {
                $this->log->debug(sprintf(
                    'SOCS staff external id %s does not have a corresponding schoolbox user record; skipping',
                    $externalId
                ));

                continue;
            }

            try {
                $user = $this->userMapper->findById($userId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log->debug(sprintf(
                    'SOCS staff external id %s maps to user id %d, but user record could not be loaded; skipping',
                    $externalId,
                    $userId
                ));

                continue;
            }

            $coachName = trim(sprintf(
                '%s %s',
                $user->getPreferredName() ?: $user->getFirstname(),
                $user->getLastname()
            ));

            if ('' === $coachName) {
                $coachName = (string)$externalId;
            }

            try {
                $coach = $this->coachMapper->findOneByColumnList([
                    'user_id' => $user->getId(),
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $coach = $this->coachMapper->createModel()
                    ->setName($coachName)
                    ->setUser($user);

                if ($this->perform) {
                    $this->coachMapper->save($coach);
                }

                $this->log->info(sprintf(
                    'Created SOCS coach %s (%s) for schoolbox user %d',
                    $coachName,
                    $externalId,
                    $userId
                ));
            }

            try {
                $this->teamCoachMapper->findOneByColumnList([
                    'team_id' => $team->getId(),
                    'coach_id' => $coach->getId(),
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $teamCoach = $this->teamCoachMapper->createModel()
                    ->setTeam($team)
                    ->setCoach($coach);

                if ($this->perform) {
                    $this->teamCoachMapper->save($teamCoach);
                }

                $this->log->info(sprintf(
                    'Assigned SOCS staff %s as coach to team %s using schoolbox user %d',
                    $externalId,
                    $team->getName(),
                    $userId
                ));
            }
        }
    }
}
