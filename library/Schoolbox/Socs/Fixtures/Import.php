<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as MatchupModel;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Coach\Mapper as TeamCoachMapper;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Socs\IStrategy;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Import fixtures from the SOCS standard fixtures feed into Schoolbox Matchups.
 */
class Import implements IStrategy
{
    private const string FIXTURES_PATH = 'fixturecalendar.ashx';

    private const string EXTERNAL_ID_PREFIX = 'socs:';

    /**
     * SOCS - Standard Fixture Feed
     * the api system defaults to, and this ensures we fetch data for the previous 30 days and next 100 days.
     */
    private const int FROM = -30;
    private const int TO = 100;

    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private LockInterface $lock,
        private HttpClient $httpClient,
        private Registry $registry,
        private MatchupMapper $matchupMapper,
        private UserMapper $userMapper,
        private TeamPlayerMapper $teamPlayerMapper,
        private TeamCoachMapper $teamCoachMapper,
        private CoachMapper $coachMapper,
        private bool $importHumans,
        private PersonLookup $personLookup
    ) {
    }

    /**
     * Import SOCS fixtures into Schoolbox matchups and synchronise team memberships.
     */
    public function import(): void
    {
        $season = $this->registry->recallSeason();

        $startDate = \Alaress_Date_Format::now()->addDays(self::FROM)->getUsingFormat('j M Y');
        $endDate = \Alaress_Date_Format::now()->addDays(self::TO)->getUsingFormat('j M Y');

        $this->log->info(sprintf('Downloading SOCS fixtures from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        // if we are importing humans, SOCS only gives us 7 days of data
        if ($this->importHumans) {
            $this->log->info('SOCS fixtures is importing humans attached to fixtures; only fetching 7 days');
            /**
             * From the SOCS docs:
             * * The standard fixture calendar can also include published team sheets by adding the “TS=1” querystring to the feed URL as follows:
             * * Note: if team sheets are included the feed will only show fixtures for the previous and next 7 days regardless of any date ranges defined.
             * * [feedurl]&TS=1
             */
            $query['TS'] = 1;
        }

        try {
            $response = $this->httpClient->get(self::FIXTURES_PATH, $query);
        } catch (\RuntimeException $e) {
            $this->log->warning(sprintf('Failed to contact SOCS fixtures endpoint: %s', $e->getMessage()));
            if ($e->getPrevious()) {
                $this->log->debug(sprintf('Previous: %s', $e->getPrevious()->getMessage()));
                $this->log->debug($e->getPrevious()->getTraceAsString());
            }
            return;
        }

        if (false === $response) {
            $this->log->info('SOCS fixtures feed returned an empty payload');
            return;
        }

        $imported = 0;

        foreach ($response->children() as $fixture) {
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

            $sport = $this->registry->recallSport($sportName);

            $divisionName = $teamName;
            $division = $this->registry->recallDivision($divisionName, $sport, $season);

            $team = $this->registry->recallTeam($teamName, $division);

            $this->syncParticipantsWithTeam($fixture, $team);

            $venueName = $locationName !== '' ? $locationName : 'Unspecified location';
            $venue = $this->registry->recallVenue($venueName);

            $awaySchool = null;
            if ($oppositionSchoolName !== '') {
                $awaySchool = $this->registry->recallSchool($oppositionSchoolName);
            }

            /**
             * use the helper to translate SOCS documented date/time fields to a standard Alaress_Date_Format
             * SOCS does have an undocumented `startdatefull`/`enddatefull`
             * which would be nice to use as it contains either empty or `2026-03-30T14:30:00` a nice standard format...
             * But this is undocumented, so I won't use it just yet... it may change or go away...
             */
            $startAt = $this->makeDateTime($date, $time, $this->log);
            if (null === $startAt) {
                $this->log->debug(sprintf('Skipping SOCS fixture %s due to invalid date/time', $eventId));
                continue;
            }

            $externalId = self::EXTERNAL_ID_PREFIX . $eventId;

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

    /**
     * Attach pupils and staff referenced in a SOCS fixture to the given team.
     */
    private function syncParticipantsWithTeam(\SimpleXMLElement $fixture, \Schoolbox\Fixtures\Team\Model $team): void
    {
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

    /**
     * Ensure each pupil external ID from SOCS is linked as a team player.
     */
    private function syncPupilsWithTeam(string $pupils, \Schoolbox\Fixtures\Team\Model $team): void
    {
        $externalIds = array_filter(array_map('trim', explode(',', $pupils)), 'strlen');

        if ([] === $externalIds) {
            return;
        }

        $sequence = 0;

        foreach ($externalIds as $externalId) {
            $this->lock->refresh();

            $userId = $this->personLookup->fetchUser($externalId);
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
            }

            $this->log->info(sprintf(
                'Assigned SOCS pupil %s to team %s using schoolbox user %d',
                $externalId,
                $team->getName(),
                $userId
            ));
        }
    }

    /**
     * Ensure each staff external ID from SOCS is linked as a coach for the team.
     */
    private function syncStaffWithTeam(string $staff, \Schoolbox\Fixtures\Team\Model $team): void
    {
        $externalIds = array_filter(array_map('trim', explode(',', $staff)), 'strlen');

        if ([] === $externalIds) {
            return;
        }

        foreach ($externalIds as $externalId) {
            $this->lock->refresh();

            $userId = $this->personLookup->fetchUser($externalId);
            if (null === $userId) {
                /**
                 * SOCS only gives us a CSV user ID list, no other identifing data for humans, so if we don't have that coach ID, in schoolbox, then we can't identify them...
                 */
                $this->log->debug(sprintf(
                    'SOCS staff external id %s does not have a corresponding schoolbox user record; manually creating',
                    $externalId
                ));

                // create a dummy user
                $user = $this->userMapper->createModel()
                    ->setPreferredName(sprintf("Coach: %s", $externalId))
                    ->setLastname('')
                    ->setExternalId($externalId);
            } else {
                try {
                    $user = $this->userMapper->findById($userId);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->log->debug(
                        sprintf(
                            'SOCS staff external id %s maps to user id %d, but user record could not be loaded; skipping',
                            $externalId,
                            $userId
                        )
                    );

                    continue;
                }
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
                $coach = $this->coachMapper->findOneByColumn('user_id', $user->getId());
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
            }

            $this->log->info(sprintf(
                'Assigned SOCS staff %s as coach to team %s using schoolbox user %d',
                $externalId,
                $team->getName(),
                $userId
            ));
        }
    }

    /**
     * Parse a SOCS date and time string into an Alaress_Date_Format instance.
     *
     * SOCS documents fixture/result date fields as DATE and time fields as
     * STRING, but does not publish an exact feed date format. The accepted
     * formats below document which formats come from SOCS examples, existing
     * Schoolbox fixture import conventions, and defensive internal fallbacks.
     *
     * @param string          $date
     * @param string          $time
     * @param LoggerInterface $logger
     * @return \Alaress_Date_Format|null
     */
    private function makeDateTime(string $date, string $time, LoggerInterface $logger): ?\Alaress_Date_Format
    {
        if ('' === $date) {
            return null;
        }

        $time = strtolower($time);
        // SOCS documents unset time values as "tbc" or "N\A"; empty and
        // "N/A" are accepted as common variants so the fixture date survives.
        $timeIsUnset = $time === '' || $time === 'tbc' || $time === 'n\\a' || $time === 'n/a';
        if ($timeIsUnset) {
            $time = '00:00';
        }

        $raw = sprintf('%s %s', $date, $time);

        $dateTimeFormats = [
            // SOCS date range examples use "1 Sep 2025":
            // https://help.socscms.com/Page.asp?CatID=13&ID=276
            'j M Y H:i',
            // Same SOCS example date shape when the time includes seconds.
            'j M Y H:i:s',
            // Compatibility with the existing Schoolbox fixture CSV import.
            // Also seen in the SOCS xml using our test creds
            'd/m/Y H:i',
            // Same Schoolbox CSV date shape when the time includes seconds.
            'd/m/Y H:i:s',
            // Schoolbox internal database/API datetime fallback.
            \Alaress_Date_Format::DATABASE_FORMAT,
        ];

        $dateOnlyFormats = [
            // Date-only Schoolbox CSV compatibility fallback; "|" resets time to 00:00:00.
            'd/m/Y|',
            // Schoolbox internal database/API date-only fallback; "|" resets time to 00:00:00.
            \Alaress_Date_Format::DATABASE_DATE_FORMAT . '|',
        ];

        $attempts = [[$raw, $dateTimeFormats]];
        if ($timeIsUnset) {
            $attempts[] = [$date, $dateOnlyFormats];
        }

        $lastException = null;
        foreach ($attempts as [$value, $formats]) {
            try {
                return new \Alaress_Date_Format($value, $formats);
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }

        $logger->debug(sprintf('Unable to parse SOCS date/time "%s": %s', $raw, $lastException->getMessage()));

        return null;
    }
}
