<?php

/**
 * SOCS Fixtures CLI Importer
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Cli;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Socs\HttpClient;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Result\Model as ResultModel;
use Schoolbox\Fixtures\Division\Model as DivisionModel;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as MatchupModel;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\Fixtures\School\Model as SchoolModel;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Season\Model as SeasonModel;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Sport\Model as SportModel;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Model as TeamModel;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Fixtures\Venue\Model as VenueModel;
use Symfony\Component\Lock\LockInterface;

class ImporterTool extends CliTool\App
{
    private const SOCS_IMPORTER_LOCK_NAME = 'socs_importer';
    public const LOCK_NAME = self::SOCS_IMPORTER_LOCK_NAME;
    private const SOCS_SCHOOL_ID = '2460';

    private const FIXTURES_PATH = 'fixturecalendar.ashx';
    private const MSO_SPORT_PATH = 'mso-sport.ashx';
    private const RESULTS_PATH = 'results.ashx';
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_INSTANCE = 'instance:';
    private const OPT_LONG_FIXTURES = 'fixtures';
    private const OPT_LONG_RESULTS = 'results';
    private const OPT_LONG_TEAMS = 'teams';
    private const LOCK_TTL = 120;
    private const DEFAULT_TIMEOUT = 3600;

    private ?HttpClient $httpClient = null;

    /**
     * Declare long CLI options accepted by the SOCS importer.
     *
     * These values are consumed by the generic CLI option parser to
     * determine which flags this tool understands.
     *
     * @return string[] List of long option definitions for getopt
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_FIXTURES,
            self::OPT_LONG_RESULTS,
            self::OPT_LONG_TEAMS,
        ];
    }

    /**
     * Entry point for the SOCS importer CLI.
     *
     * Acquires the importer lock, determines which imports to run, and
     * executes fixtures, results and/or teams synchronisations.
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            return;
        }

        $logger = $this->getLogger();

        if (!$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENABLED)) {
            $logger->info('SOCS integration is not enabled; exiting');
            return;
        }

        try {
            $lock = $this->acquireLock(self::SOCS_IMPORTER_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $logger->warning('Cannot acquire SOCS importer lock. Likely previous execution still in progress. Exiting');
            return;
        }

        $this->setMaxExecutionTime(self::DEFAULT_TIMEOUT);

        $runFixtures = (bool)$this->getOpt(self::OPT_LONG_FIXTURES);
        $runResults = (bool)$this->getOpt(self::OPT_LONG_RESULTS);
        $runTeams = (bool)$this->getOpt(self::OPT_LONG_TEAMS);

        if (!$runFixtures && !$runResults && !$runTeams) {
            $runFixtures = $runResults = $runTeams = true;
        }

        try {
            // spawn the http client in the background so that it can be used
            $host = (string)$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST);

            if ('' === $host) {
                $logger->warning('Missing SOCS endpoint host; exiting results import');
                return;
            }

            $token = (string)$this->getConfig()->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN);
            if ('' === $token) {
                $logger->warning('Missing SOCS endpoint token; exiting results import');
                return;
            }

            $this->httpClient = new httpClient($host, self::SOCS_SCHOOL_ID, $token);
            if ($runFixtures) {
                $this->importFixtures($logger, $lock);
            }

            if ($runResults) {
                $this->importResults($logger);
            }

            if ($runTeams) {
                $this->importTeams($logger);
            }
        } finally {
            if ($this->httpClient instanceof HttpClient) {
                $this->httpClient->logUrlUsage($logger);
            }
            $lock->release();
        }
    }

    /**
     * Print usage information for the SOCS importer CLI.
     */
    private function printHelp(): void
    {
        echo <<<HELP

Import SOCS sport fixtures into the Schoolbox Fixtures module using the
SOCS XML fixtures feed, synchronise SOCS results via the results feed,
and synchronise SOCS teams via the mso-sport feed.

Usage: php importer.php [--perform] [--fixtures] [--results] [--teams]

When --perform is omitted, the importer will run in planning mode and
log what would happen without persisting any changes.

When none of --fixtures, --results or --teams are provided, all
synchronisations will run. When one or more are provided, only the
selected synchronisations will run.

HELP;
    }

    /**
     * Import fixtures from the SOCS standard fixtures feed into Schoolbox Matchups.
     *
     * Creates or updates sports, divisions, teams, venues and fixtures within the
     * dedicated SOCS season.
     *
     * @throws \Schoolbox\Mapper\Exception When database lookups or saves fail
     * @throws \PDOException                 When the underlying database connection fails
     */
    private function importFixtures(LoggerInterface $logger, LockInterface $lock): void
    {
        $seasonMapper = SeasonMapper::getInstance();
        $sportMapper = SportMapper::getInstance();
        $divisionMapper = DivisionMapper::getInstance();
        $teamMapper = TeamMapper::getInstance();
        $venueMapper = VenueMapper::getInstance();
        $schoolMapper = SchoolMapper::getInstance();
        $matchupMapper = MatchupMapper::getInstance();

        $season = $this->recallSeason($seasonMapper, $logger);

        $now = \Alaress_Date_Format::now();
        $startDate = (clone $now)->addDays(-30)->getUsingFormat('j M Y');
        $endDate = (clone $now)->addDays(100)->getUsingFormat('j M Y');

        $logger->info(sprintf('Downloading SOCS fixtures from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        try {
            $response = $this->httpClient->get(self::FIXTURES_PATH, $query, $logger);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS endpoint: %s', $e->getMessage()));
            return;
        }

        if (null === $response) {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $logger->warning(sprintf(
                'Bad SOCS response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $logger->debug(sprintf(
            'SOCS fixtures raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $logger->info('SOCS fixtures feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $logger->warning(sprintf(
                'Unable to parse SOCS XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        $imported = 0;

        foreach ($xml->children() as $fixture) {
            $lock->refresh();

            $eventId = trim((string)$fixture->eventid);
            $sportName = trim((string)$fixture->sport);
            $teamName = trim((string)$fixture->team);
            $oppositionSchoolName = trim((string)$fixture->opposition);
            $locationName = trim((string)$fixture->location);
            $details = trim((string)$fixture->details);
            $date = trim((string)$fixture->date);
            $time = trim((string)$fixture->time);

            if ('' === $eventId || '' === $sportName || '' === $teamName || '' === $date) {
                $logger->debug('Skipping SOCS fixture with missing mandatory fields');
                continue;
            }

            $sport = $this->recallSport($sportMapper, $logger, $sportName);

            $divisionName = $teamName;
            $division = $this->recallDivision($divisionMapper, $logger, $divisionName, $sport, $season);

            $team = $this->recallTeam($teamMapper, $logger, $teamName, $division);

            $venueName = $locationName !== '' ? $locationName : 'Unspecified location';
            $venue = $this->recallVenue($venueMapper, $logger, $venueName);

            $awaySchool = null;
            if ($oppositionSchoolName !== '') {
                $awaySchool = $this->recallSchool($schoolMapper, $logger, $oppositionSchoolName);
            }

            $startAt = $this->makeDateTime($date, $time, $logger);
            if (null === $startAt) {
                $logger->debug(sprintf('Skipping SOCS fixture %s due to invalid date/time', $eventId));
                continue;
            }

            $externalId = 'socs:' . $eventId;

            try {
                /** @var MatchupModel $matchup */
                $matchup = $matchupMapper->findOneByColumn('external_id', $externalId);
                $logger->debug(sprintf('Updating SOCS fixture %s', $externalId));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $matchup = $matchupMapper->createModel();
                $logger->debug(sprintf('Inserting SOCS fixture %s', $externalId));
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

            if ($this->perform()) {
                $matchupMapper->save($matchup);
            }

            $imported++;
        }

        if ($this->perform()) {
            $logger->info(sprintf('SOCS fixtures import complete; processed %d fixtures', $imported));
        } else {
            $logger->info(sprintf('SOCS fixtures planning complete; would process %d fixtures', $imported));
        }
    }

    /**
     * Import SOCS results from the standard results feed and attach them to existing fixtures.
     *
     * Looks up Matchups by their SOCS external_id and stores a single Result for the home team.
     *
     * @throws \Schoolbox\Mapper\Exception When database lookups or saves fail
     * @throws \PDOException                 When the underlying database connection fails
     */
    private function importResults(LoggerInterface $logger): void
    {
        $now = \Alaress_Date_Format::now();
        $startDate = (clone $now)->addDays(-30)->getUsingFormat('j M Y');
        $endDate = (clone $now)->addDays(100)->getUsingFormat('j M Y');

        $logger->info(sprintf('Downloading SOCS results from %s to %s', $startDate, $endDate));

        $query = [
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        try {
            $response = $this->httpClient->get(self::RESULTS_PATH, $query, $logger);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS results endpoint: %s', $e->getMessage()));
            return;
        }

        if (null === $response) {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $logger->warning(sprintf(
                'Bad SOCS results response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $logger->debug(sprintf(
            'SOCS results raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $logger->info('SOCS results feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $logger->warning(sprintf(
                'Unable to parse SOCS results XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        $matchupMapper = MatchupMapper::getInstance();
        $resultMapper = ResultMapper::getInstance();

        $imported = 0;

        foreach ($xml->children() as $resultNode) {
            $eventId = trim((string)$resultNode->eventid);
            $resultText = trim((string)$resultNode->result);

            if ('' === $eventId || '' === $resultText) {
                $logger->debug('Skipping SOCS result with missing mandatory fields');
                continue;
            }

            $externalId = 'socs:' . $eventId;

            try {
                /** @var MatchupModel $matchup */
                $matchup = $matchupMapper->findOneByColumn('external_id', $externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $logger->debug(sprintf('Skipping SOCS result %s; no matching SOCS fixture found', $externalId));
                continue;
            }

            $homeTeam = $matchup->getHomeTeam();
            if (!$homeTeam instanceof TeamModel || !$homeTeam->getId()) {
                $logger->debug(sprintf('Skipping SOCS result %s; matchup has no home team', $externalId));
                continue;
            }

            if ($this->perform()) {
                $existingResults = $resultMapper->findByColumn('match_id', $matchup->getId());
                foreach ($existingResults as $existingResult) {
                    $resultMapper->delete($existingResult);
                }

                /** @var ResultModel $result */
                $result = $resultMapper->createModel();
                $result
                    ->setMatchup($matchup)
                    ->setTeam($homeTeam)
                    ->setDescription($resultText);

                $resultMapper->save($result);
            }

            $imported++;
        }

        if ($this->perform()) {
            $logger->info(sprintf('SOCS results import complete; processed %d results', $imported));
        } else {
            $logger->info(sprintf('SOCS results planning complete; would process %d results', $imported));
        }
    }

    /**
     * Import SOCS teams from the detailed mso-sport feed.
     *
     * Ensures that sports, divisions and teams referenced by SOCS exist in the
     * dedicated SOCS season, without creating any fixtures.
     *
     * @throws \Schoolbox\Mapper\Exception When database lookups or saves fail
     * @throws \PDOException                 When the underlying database connection fails
     */
    private function importTeams(LoggerInterface $logger): void
    {
        $seasonMapper = SeasonMapper::getInstance();
        $sportMapper = SportMapper::getInstance();
        $divisionMapper = DivisionMapper::getInstance();
        $teamMapper = TeamMapper::getInstance();

        $season = $this->recallSeason($seasonMapper, $logger);

        $logger->info('Downloading SOCS teams from mso-sport feed');

        $query = [];

        try {
            $response = $this->httpClient->get(self::MSO_SPORT_PATH, $query, $logger);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS teams endpoint: %s', $e->getMessage()));
            return;
        }

        if (null === $response) {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            $logger->warning(sprintf(
                'Bad SOCS teams response: %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));
            return;
        }

        $body = (string)$response->getBody();
        $logger->debug(sprintf(
            'SOCS teams raw response: Content-Type %s; length %d',
            $response->getHeaderLine('Content-Type') ?: 'unknown',
            strlen($body)
        ));
        if ('' === $body) {
            $logger->info('SOCS teams feed returned an empty payload');
            return;
        }

        $xml = @simplexml_load_string($body);
        if (false === $xml) {
            $logger->warning(sprintf(
                'Unable to parse SOCS teams XML payload; aborting. First 200 chars: %s',
                substr($body, 0, 200)
            ));
            return;
        }

        /** @var \SimpleXMLElement[]|false $teams */
        $teams = $xml->xpath('//team');
        if (false === $teams || [] === $teams) {
            $logger->info('SOCS teams feed contained no team records');
            return;
        }

        $imported = 0;

        foreach ($teams as $teamNode) {
            $sportName = trim((string)$teamNode->sport);
            $teamName = trim((string)$teamNode->teamname);

            if ('' === $sportName || '' === $teamName) {
                $logger->debug('Skipping SOCS team with missing mandatory fields');
                continue;
            }

            $sport = $this->recallSport($sportMapper, $logger, $sportName);

            $divisionName = $teamName;
            $division = $this->recallDivision($divisionMapper, $logger, $divisionName, $sport, $season);

            $this->recallTeam($teamMapper, $logger, $teamName, $division);

            $imported++;
        }

        if ($this->perform()) {
            $logger->info(sprintf('SOCS teams import complete; processed %d teams', $imported));
        } else {
            $logger->info(sprintf('SOCS teams planning complete; would process %d teams', $imported));
        }
    }


    /**
     * Find or create the SOCS Fixtures season covering the current year window.
     *
     * @throws \Schoolbox\Mapper\Exception When saving the Season fails
     * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallSeason(SeasonMapper $seasonMapper, LoggerInterface $logger): SeasonModel
    {
        $seasonName = 'SOCS Fixtures';

        $start = \Alaress_Date_Format::now()->startOfYear()->startOfDay();
        $end = \Alaress_Date_Format::now()->startOfYear()->startOfDay()->addYears(2);

        try {
            /** @var SeasonModel $season */
            $season = $seasonMapper->findOneByColumn('name', $seasonName);
            $logger->debug(sprintf(
                'Reusing existing SOCS season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SeasonModel $season */
            $season = $e->getDeletedObject();
            $season->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS season %s', $season->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SeasonModel $season */
            $season = $seasonMapper->createModel()
                ->setName($seasonName)
                ->setStart($start)
                ->setEnd($end);

            $logger->debug(sprintf(
                'Creating new SOCS season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        }

        if ($this->perform()) {
            $seasonMapper->save($season);
        }

        return $season;
    }

    /**
     * Find, resurrect or create a Sport by name for SOCS imports.
      *
      * @throws \Schoolbox\Mapper\Exception When saving the Sport fails
      * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallSport(SportMapper $sportMapper, LoggerInterface $logger, string $sportName): SportModel
    {
        try {
            /** @var SportModel $sport */
            $sport = $sportMapper->findOneByColumn('name', $sportName);
            $logger->debug(sprintf('Reusing existing SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SportModel $sport */
            $sport = $e->getDeletedObject();
            $sport->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SportModel $sport */
            $sport = $sportMapper->createModel()
                ->setName($sportName);
            $logger->debug(sprintf('Creating new SOCS sport %s', $sport->getName()));
        }

        if ($this->perform()) {
            $sportMapper->save($sport);
        }

        return $sport;
    }

    /**
     * Find, resurrect or create a Division for a given sport/season combination.
      *
      * @throws \Schoolbox\Mapper\Exception When saving the Division fails
      * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallDivision(
        DivisionMapper $divisionMapper,
        LoggerInterface $logger,
        string $divisionName,
        SportModel $sport,
        SeasonModel $season
    ): DivisionModel {
        try {
            /** @var DivisionModel $division */
            $division = $divisionMapper->findOneByColumnList([
                'name' => $divisionName,
                'sport_id' => $sport->getId(),
                'season_id' => $season->getId(),
            ]);
            $logger->debug(sprintf('Reusing existing SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var DivisionModel $division */
            $division = $e->getDeletedObject();
            $division->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var DivisionModel $division */
            $division = $divisionMapper->createModel()
                ->setName($divisionName)
                ->setSport($sport)
                ->setSeason($season);
            $logger->debug(sprintf('Creating new SOCS division %s', $division->getName()));
        }

        if ($this->perform()) {
            $divisionMapper->save($division);
        }

        return $division;
    }

    /**
     * Find, resurrect or create a Team within the given Division.
      *
      * @throws \Schoolbox\Mapper\Exception When saving the Team fails
      * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallTeam(TeamMapper $teamMapper, LoggerInterface $logger, string $teamName, DivisionModel $division): TeamModel
    {
        try {
            /** @var TeamModel $team */
            $team = $teamMapper->findOneByColumnList([
                'name' => $teamName,
                'division_id' => $division->getId(),
            ]);
            $logger->debug(sprintf('Reusing existing SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var TeamModel $team */
            $team = $e->getDeletedObject();
            $team->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var TeamModel $team */
            $team = $teamMapper->createModel()
                ->setName($teamName)
                ->setDivision($division);
            $logger->debug(sprintf('Creating new SOCS team %s', $team->getName()));
        }

        if ($this->perform()) {
            $teamMapper->save($team);
        }

        return $team;
    }

    /**
     * Find, resurrect or create a Venue by name.
      *
      * @throws \Schoolbox\Mapper\Exception When saving the Venue fails
      * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallVenue(VenueMapper $venueMapper, LoggerInterface $logger, string $venueName): VenueModel
    {
        try {
            /** @var VenueModel $venue */
            $venue = $venueMapper->findOneByColumn('name', $venueName);
            $logger->debug(sprintf('Reusing existing SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var VenueModel $venue */
            $venue = $e->getDeletedObject();
            $venue->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var VenueModel $venue */
            $venue = $venueMapper->createModel()
                ->setName($venueName);
            $logger->debug(sprintf('Creating new SOCS venue %s', $venue->getName()));
        }

        if ($this->perform()) {
            $venueMapper->save($venue);
        }

        return $venue;
    }

    /**
     * Find, resurrect or create a School by name for opposition teams.
      *
      * @throws \Schoolbox\Mapper\Exception When saving the School fails
      * @throws \PDOException                 When the underlying database connection fails
     */
    private function recallSchool(SchoolMapper $schoolMapper, LoggerInterface $logger, string $schoolName): SchoolModel
    {
        try {
            /** @var SchoolModel $school */
            $school = $schoolMapper->findOneByColumn('name', $schoolName);
            $logger->debug(sprintf('Reusing existing SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SchoolModel $school */
            $school = $e->getDeletedObject();
            $school->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SchoolModel $school */
            $school = $schoolMapper->createModel()
                ->setName($schoolName);
            $logger->debug(sprintf('Creating new SOCS school %s', $school->getName()));
        }

        if ($this->perform()) {
            $schoolMapper->save($school);
        }

        return $school;
    }

    /**
     * Parse SOCS date and time strings into an Alaress_Date_Format instance.
     *
     * Accepts multiple supported input formats and normalises common "tbc" time
     * markers; returns null and logs when parsing fails.
     */
    private function makeDateTime(string $date, string $time, LoggerInterface $logger): ?\Alaress_Date_Format
    {
        if ('' === $date) {
            return null;
        }

        $time = strtolower($time);
        if ($time === '' || $time === 'tbc' || $time === 'n\\a' || $time === 'n/a') {
            $time = '00:00';
        }

        $raw = sprintf('%s %s', $date, $time);

        try {
            return new \Alaress_Date_Format($raw, [
                'j M Y H:i',
                'j M Y H:i:s',
                'd/m/Y H:i',
                'd/m/Y H:i:s',
                'd/m/Y',
                'Y-m-d H:i:s',
                'Y-m-d',
            ]);
        } catch (\Exception $e) {
            $logger->debug(sprintf('Unable to parse SOCS date/time "%s": %s', $raw, $e->getMessage()));
            return null;
        }
    }

    /**
     * Lazily create and cache the SOCS HttpClient for the current importer run.
     *
     * @param string $host Base SOCS host URL from configuration
     */
    private function getHttpClient(string $host, string $schoolId = '', #[SensitiveParameter] string $token = ''): HttpClient
    {
        if (!$this->httpClient instanceof HttpClient) {
            $this->httpClient = new HttpClient($host, $schoolId, $token);
        }

        return $this->httpClient;
    }

    /**
     * Indicate whether this CLI tool is permitted to run as root.
     *
     * Delegates to the parent implementation, which emits a warning when
     * executed as root but does not prevent execution outright.
     *
     * @return bool|null True to allow, false to block, null for default behaviour
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
