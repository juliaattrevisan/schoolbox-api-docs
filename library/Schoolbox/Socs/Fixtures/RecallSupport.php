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
     */
    protected function makeDateTime(string $date, string $time, LoggerInterface $logger): ?\Alaress_Date_Format
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
}
