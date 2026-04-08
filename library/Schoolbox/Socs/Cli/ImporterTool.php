<?php

/**
 * SOCS Fixtures CLI Importer
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Cli;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
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
    private const MSO_SPORT_PATH = 'mso-sport.ashx';
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_INSTANCE = 'instance:';
    private const LOCK_TTL = 120;
    private const DEFAULT_TIMEOUT = 3600;

    /** @var array<string,int> */
    private array $urlUsage = [];

    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
        ];
    }

    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            return;
        }

        $logger = $this->getLogger();

        if (!$this->getConfig()->get('socs_enabled')) {
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

        try {
            $this->importFixtures($logger, $lock);
            $this->importTeams($logger);
        } finally {
            $this->logUrlUsage($logger);
            $lock->release();
        }
    }

    private function printHelp(): void
    {
        echo <<<HELP

Import SOCS sport fixtures into the Schoolbox Fixtures module using the
SOCS XML fixtures feed, and synchronise SOCS teams via the mso-sport feed.

Usage: php importer.php [--perform]

When --perform is omitted, the importer will run in planning mode and
log what would happen without persisting any changes.

HELP;
    }

    private function importFixtures(LoggerInterface $logger, LockInterface $lock): void
    {
        $host = (string)$this->getConfig()->get('socs_endpoint_host');

        if ('' === $host) {
            $logger->warning('Missing SOCS endpoint host; exiting');
            return;
        }

        $logger->info('SOCS to Schoolbox Fixtures synchronisation starting');

        $token = (string)$this->getConfig()->get('socs_endpoint_token');
        if ('' === $token) {
            $logger->warning('Missing SOCS endpoint token; exiting');
            return;
        }

        $baseUrl = rtrim($host, '/');
        $fixturesUrl = $baseUrl . '/fixturecalendar.ashx';

        $client = new Client([
            'timeout' => 300,
            'http_errors' => false,
        ]);

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
            'ID'        => self::SOCS_SCHOOL_ID,
            'key'       => $token,
            'startdate' => $startDate,
            'enddate'   => $endDate,
        ];

        $this->noteUrlUsed($fixturesUrl, $query);

        try {
            $response = $client->request('GET', $fixturesUrl, [
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS endpoint: %s', $e->getMessage()));
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

    private function importTeams(LoggerInterface $logger): void
    {
        $host = (string)$this->getConfig()->get('socs_endpoint_host');

        if ('' === $host) {
            $logger->warning('Missing SOCS endpoint host; exiting teams import');
            return;
        }

        $token = (string)$this->getConfig()->get('socs_endpoint_token');
        if ('' === $token) {
            $logger->warning('Missing SOCS endpoint token; exiting teams import');
            return;
        }

        $baseUrl = rtrim($host, '/');
        $teamsUrl = $baseUrl . '/' . self::MSO_SPORT_PATH;

        $client = new Client([
            'timeout' => 300,
            'http_errors' => false,
        ]);

        $seasonMapper = SeasonMapper::getInstance();
        $sportMapper = SportMapper::getInstance();
        $divisionMapper = DivisionMapper::getInstance();
        $teamMapper = TeamMapper::getInstance();

        $season = $this->recallSeason($seasonMapper, $logger);

        $logger->info('Downloading SOCS teams from mso-sport feed');

        $query = [
            'ID'  => self::SOCS_SCHOOL_ID,
            'key' => $token,
        ];

        $this->noteUrlUsed($teamsUrl, $query);

        try {
            $response = $client->request('GET', $teamsUrl, [
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS teams endpoint: %s', $e->getMessage()));
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
            $teamId = trim((string)$teamNode->teamid);
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

    private function logUrlUsage(LoggerInterface $logger): void
    {
        foreach ($this->urlUsage as $url => $count) {
            $logger->info(sprintf('SOCS URL used %d time(s): %s', $count, $url));
        }
    }

    private function noteUrlUsed(string $baseUrl, array $query): void
    {
        $separator = false === strpos($baseUrl, '?') ? '?' : '&';
        $url = $baseUrl . $separator . http_build_query($query);

        if (!isset($this->urlUsage[$url])) {
            $this->urlUsage[$url] = 0;
        }

        $this->urlUsage[$url]++;
    }

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

    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
