<?php

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Division\Model as DivisionModel;
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
use Schoolbox\Mapper\Exception;

/**
 * Shared helper methods for recalling or creating SOCS-related fixtures entities.
 */
trait RecallSupport
{
    /**
     * Whether import should persist changes.
     */
    protected bool $perform = false;

    private const int END_THRESHOLD = 2;

    /**
     * Find or create the shared "SOCS Fixtures" season spanning the current year and next.
     *
     * @param SeasonMapper    $seasonMapper
     * @param LoggerInterface $logger
     * @return SeasonModel
     * @throws Exception
     */
    protected function recallSeason(SeasonMapper $seasonMapper, LoggerInterface $logger): SeasonModel
    {
        $seasonName = 'SOCS Fixtures';

        /**
         * use getStartOfSchoolYear which uses the `start_of_school_year` from admin config, to assist in northern heamisphere schools who's school year may be july/june
         * getStartOfSchoolYear clones the current date, so rather than `::now()` for each date
         */
        $now = \Alaress_Date_Format::now();
        $start = $now->getStartOfSchoolYear()->startOfDay();
        $end = $now->getStartOfSchoolYear()->startOfDay()->addYears(self::END_THRESHOLD);

        $needsSave = false;

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
            $needsSave = true;
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
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $seasonMapper->save($season);
        }

        return $season;
    }

    /**
     * Find or create a SOCS sport by name.
     *
     * @param SportMapper     $sportMapper
     * @param LoggerInterface $logger
     * @param string          $sportName
     * @return SportModel
     * @throws Exception
     */
    protected function recallSport(SportMapper $sportMapper, LoggerInterface $logger, string $sportName): SportModel
    {
        $needsSave = false;

        try {
            /** @var SportModel $sport */
            $sport = $sportMapper->findOneByColumn('name', $sportName);
            $logger->debug(sprintf('Reusing existing SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SportModel $sport */
            $sport = $e->getDeletedObject();
            $sport->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS sport %s', $sport->getName()));
            $needsSave = true;
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SportModel $sport */
            $sport = $sportMapper->createModel()
                ->setName($sportName);
            $logger->debug(sprintf('Creating new SOCS sport %s', $sport->getName()));
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $sportMapper->save($sport);
        }

        return $sport;
    }

    /**
     * Find or create a SOCS division for the given sport, season and name.
     *
     * @param DivisionMapper  $divisionMapper
     * @param LoggerInterface $logger
     * @param string          $divisionName
     * @param SportModel      $sport
     * @param SeasonModel     $season
     * @return DivisionModel
     * @throws Exception
     */
    protected function recallDivision(
        DivisionMapper $divisionMapper,
        LoggerInterface $logger,
        string $divisionName,
        SportModel $sport,
        SeasonModel $season
    ): DivisionModel {
        $needsSave = false;

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
            $needsSave = true;
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var DivisionModel $division */
            $division = $divisionMapper->createModel()
                ->setName($divisionName)
                ->setSport($sport)
                ->setSeason($season);
            $logger->debug(sprintf('Creating new SOCS division %s', $division->getName()));
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $divisionMapper->save($division);
        }

        return $division;
    }

    /**
     * Find or create a SOCS team within the given division.
     *
     * @param TeamMapper      $teamMapper
     * @param LoggerInterface $logger
     * @param string          $teamName
     * @param DivisionModel   $division
     * @return TeamModel
     * @throws Exception
     */
    protected function recallTeam(TeamMapper $teamMapper, LoggerInterface $logger, string $teamName, DivisionModel $division): TeamModel
    {
        $needsSave = false;

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
            $needsSave = true;
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var TeamModel $team */
            $team = $teamMapper->createModel()
                ->setName($teamName)
                ->setDivision($division);
            $logger->debug(sprintf('Creating new SOCS team %s', $team->getName()));
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $teamMapper->save($team);
        }

        return $team;
    }

    /**
     * Find or create a SOCS venue by name.
     *
     * @param VenueMapper     $venueMapper
     * @param LoggerInterface $logger
     * @param string          $venueName
     * @return VenueModel
     * @throws Exception
     */
    protected function recallVenue(VenueMapper $venueMapper, LoggerInterface $logger, string $venueName): VenueModel
    {
        $needsSave = false;

        try {
            /** @var VenueModel $venue */
            $venue = $venueMapper->findOneByColumn('name', $venueName);
            $logger->debug(sprintf('Reusing existing SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var VenueModel $venue */
            $venue = $e->getDeletedObject();
            $venue->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS venue %s', $venue->getName()));
            $needsSave = true;
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var VenueModel $venue */
            $venue = $venueMapper->createModel()
                ->setName($venueName);
            $logger->debug(sprintf('Creating new SOCS venue %s', $venue->getName()));
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $venueMapper->save($venue);
        }

        return $venue;
    }

    /**
     * Find or create a SOCS opposition school by name.
     *
     * @param SchoolMapper    $schoolMapper
     * @param LoggerInterface $logger
     * @param string          $schoolName
     * @return SchoolModel
     * @throws Exception
     */
    protected function recallSchool(SchoolMapper $schoolMapper, LoggerInterface $logger, string $schoolName): SchoolModel
    {
        $needsSave = false;

        try {
            /** @var SchoolModel $school */
            $school = $schoolMapper->findOneByColumn('name', $schoolName);
            $logger->debug(sprintf('Reusing existing SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SchoolModel $school */
            $school = $e->getDeletedObject();
            $school->setDeletedAt(null);
            $logger->debug(sprintf('Resurrecting previously-deleted SOCS school %s', $school->getName()));
            $needsSave = true;
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SchoolModel $school */
            $school = $schoolMapper->createModel()
                ->setName($schoolName);
            $logger->debug(sprintf('Creating new SOCS school %s', $school->getName()));
            $needsSave = true;
        }

        if ($this->perform && $needsSave) {
            $schoolMapper->save($school);
        }

        return $school;
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
    protected function makeDateTime(string $date, string $time, LoggerInterface $logger): ?\Alaress_Date_Format
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

        if ($lastException instanceof \Exception) {
            $logger->debug(sprintf('Unable to parse SOCS date/time "%s": %s', $raw, $lastException->getMessage()));
        } else {
            $logger->debug(sprintf('Unable to parse SOCS date/time "%s"', $raw));
        }

        return null;
    }
}
