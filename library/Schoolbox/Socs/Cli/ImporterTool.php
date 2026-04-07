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
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_INSTANCE = 'instance:';
    private const LOCK_TTL = 120;
    private const DEFAULT_TIMEOUT = 3600;

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
        } finally {
            $lock->release();
        }
    }

    private function printHelp(): void
    {
        echo <<<HELP

Import SOCS sport fixtures into the Schoolbox Fixtures module using the
SOCS XML fixtures feed.

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

        $client = new Client([
            'base_uri' => $host,
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

        try {
            $response = $client->request('GET', '', [
                'query' => [
                    'startdate' => $startDate,
                    'enddate' => $endDate,
                ],
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
            return new \Alaress_Date_Format($raw, ['j M Y H:i', 'j M Y H:i:s', 'Y-m-d H:i:s', 'Y-m-d']);
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
