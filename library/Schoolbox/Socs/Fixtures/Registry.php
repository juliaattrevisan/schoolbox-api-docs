<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

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
 * Recall or create SOCS-related fixtures entities and cache them for the import run.
 */
class Registry
{
    private const int END_THRESHOLD = 2;

    private ?SeasonModel $season = null;

    /**
     * @var array<string,SportModel>
     */
    private array $sports = [];

    /**
     * @var array<string,DivisionModel>
     */
    private array $divisions = [];

    /**
     * @var array<string,TeamModel>
     */
    private array $teams = [];

    /**
     * @var array<string,VenueModel>
     */
    private array $venues = [];

    /**
     * @var array<string,SchoolModel>
     */
    private array $schools = [];

    private const string EXTERNAL_ID_PREFIX = 'socs:';

    public function __construct(
        private LoggerInterface $logger,
        private bool $perform,
        private SeasonMapper $seasonMapper,
        private SportMapper $sportMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper,
        private VenueMapper $venueMapper,
        private SchoolMapper $schoolMapper
    ) {
    }

    /**
     * Find or create the shared "SOCS Fixtures" season spanning the current year and next.
     *
     * @return SeasonModel
     * @throws Exception
     */
    public function recallSeason(): SeasonModel
    {
        if ($this->season instanceof SeasonModel) {
            return $this->season;
        }

        $seasonName = 'SOCS Fixtures';

        /**
         * use getStartOfSchoolYear which uses the `start_of_school_year` from admin config, to assist in northern
         * hemisphere schools whose school year may be july/june.
         * getStartOfSchoolYear clones the current date, so rather than `::now()` for each date.
         */
        $now = \Alaress_Date_Format::now();
        $start = $now->getStartOfSchoolYear()->startOfDay();
        $end = $now->getStartOfSchoolYear()->startOfDay()->addYears(self::END_THRESHOLD);

        try {
            /** @var SeasonModel $season */
            $season = $this->seasonMapper->findOneByColumn('name', $seasonName);
            $season->setUpdatedAt(\Alaress_Date_Format::now());
            $this->logger->debug(sprintf(
                'Reusing existing SOCS season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SeasonModel $season */
            $season = $e->getDeletedObject();
            $this->seasonMapper->restoreSoftDeleted($season);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS season %s', $season->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SeasonModel $season */
            $season = $this->seasonMapper->createModel()
                ->setName($seasonName)
                ->setStart($start)
                ->setEnd($end);

            $this->logger->debug(sprintf(
                'Creating new SOCS season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        }

        if ($this->perform) {
            $this->seasonMapper->save($season);
        }

        $this->season = $season;

        return $season;
    }

    /**
     * Find or create a SOCS sport by name.
     *
     * @param string $sportName
     * @return SportModel
     * @throws Exception
     */
    public function recallSport(string $sportName): SportModel
    {
        if (isset($this->sports[$sportName])) {
            return $this->sports[$sportName];
        }

        try {
            /** @var SportModel $sport */
            $sport = $this->sportMapper->findOneByColumn('name', $sportName);
            $sport->setUpdatedAt(\Alaress_Date_Format::now());
            $this->logger->debug(sprintf('Reusing existing SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SportModel $sport */
            $sport = $e->getDeletedObject();
            $this->sportMapper->restoreSoftDeleted($sport);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SportModel $sport */
            $sport = $this->sportMapper->createModel()
                ->setName($sportName)
                ->setExternalId(self::EXTERNAL_ID_PREFIX . $sportName);
            $this->logger->debug(sprintf('Creating new SOCS sport %s', $sport->getName()));
        }

        if ($this->perform) {
            $this->sportMapper->save($sport);
        }

        $this->sports[$sportName] = $sport;

        return $sport;
    }

    /**
     * Find or create a SOCS division for the given sport, season and name.
     *
     * @param string      $divisionName
     * @param SportModel  $sport
     * @param SeasonModel $season
     * @return DivisionModel
     * @throws Exception
     */
    public function recallDivision(string $divisionName, SportModel $sport, SeasonModel $season): DivisionModel
    {
        $cacheKey = sprintf('%s:%s:%s', $season->getName(), $sport->getName(), $divisionName);
        if (isset($this->divisions[$cacheKey])) {
            return $this->divisions[$cacheKey];
        }

        try {
            /** @var DivisionModel $division */
            $division = $this->divisionMapper->findOneByColumnList([
                'name' => $divisionName,
                'sport_id' => $sport->getId(),
                'season_id' => $season->getId(),
            ]);
            $this->logger->debug(sprintf('Reusing existing SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var DivisionModel $division */
            $division = $e->getDeletedObject();
            $this->divisionMapper->restoreSoftDeleted($division);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var DivisionModel $division */
            $division = $this->divisionMapper->createModel()
                ->setName($divisionName)
                ->setSport($sport)
                ->setSeason($season)
                ->setExternalId(self::EXTERNAL_ID_PREFIX . $divisionName);
            $this->logger->debug(sprintf('Creating new SOCS division %s', $division->getName()));
        }

        if ($this->perform) {
            $this->divisionMapper->save($division);
        }

        $this->divisions[$cacheKey] = $division;

        return $division;
    }

    /**
     * Find or create a SOCS team within the given division.
     *
     * @param string        $teamName
     * @param DivisionModel $division
     * @return TeamModel
     * @throws Exception
     */
    public function recallTeam(string $teamName, DivisionModel $division): TeamModel
    {
        $cacheKey = sprintf(
            '%s:%s:%s:%s',
            $division->getSeason()->getName(),
            $division->getSport()->getName(),
            $division->getName(),
            $teamName
        );
        if (isset($this->teams[$cacheKey])) {
            return $this->teams[$cacheKey];
        }

        try {
            /** @var TeamModel $team */
            $team = $this->teamMapper->findOneByColumnList([
                'name' => $teamName,
                'division_id' => $division->getId(),
            ]);
            $this->logger->debug(sprintf('Reusing existing SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var TeamModel $team */
            $team = $e->getDeletedObject();
            $this->teamMapper->restoreSoftDeleted($team);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var TeamModel $team */
            $team = $this->teamMapper->createModel()
                ->setName($teamName)
                ->setDivision($division)
                ->setExternalId(self::EXTERNAL_ID_PREFIX . $teamName);
            $this->logger->debug(sprintf('Creating new SOCS team %s', $team->getName()));
        }

        if ($this->perform) {
            $this->teamMapper->save($team);
        }

        $this->teams[$cacheKey] = $team;

        return $team;
    }

    /**
     * Find or create a SOCS venue by name.
     *
     * @param string $venueName
     * @return VenueModel
     * @throws Exception
     */
    public function recallVenue(string $venueName): VenueModel
    {
        if (isset($this->venues[$venueName])) {
            return $this->venues[$venueName];
        }

        try {
            /** @var VenueModel $venue */
            $venue = $this->venueMapper->findOneByColumn('name', $venueName);
            $venue->setUpdatedAt(\Alaress_Date_Format::now());
            $this->logger->debug(sprintf('Reusing existing SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var VenueModel $venue */
            $venue = $e->getDeletedObject();
            $this->venueMapper->restoreSoftDeleted($venue);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var VenueModel $venue */
            $venue = $this->venueMapper->createModel()
                ->setName($venueName)
                ->setExternalId(self::EXTERNAL_ID_PREFIX . $venueName);
            $this->logger->debug(sprintf('Creating new SOCS venue %s', $venue->getName()));
        }

        if ($this->perform) {
            $this->venueMapper->save($venue);
        }

        $this->venues[$venueName] = $venue;

        return $venue;
    }

    /**
     * Find or create a SOCS opposition school by name.
     *
     * @param string $schoolName
     * @return SchoolModel
     * @throws Exception
     */
    public function recallSchool(string $schoolName): SchoolModel
    {
        if (isset($this->schools[$schoolName])) {
            return $this->schools[$schoolName];
        }

        try {
            /** @var SchoolModel $school */
            $school = $this->schoolMapper->findOneByColumn('name', $schoolName);
            $school->setUpdatedAt(\Alaress_Date_Format::now());
            $this->logger->debug(sprintf('Reusing existing SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SchoolModel $school */
            $school = $e->getDeletedObject();
            $this->schoolMapper->restoreSoftDeleted($school);
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SchoolModel $school */
            $school = $this->schoolMapper->createModel()
                ->setName($schoolName);
            $this->logger->debug(sprintf('Creating new SOCS school %s', $school->getName()));
        }

        if ($this->perform) {
            $this->schoolMapper->save($school);
        }

        $this->schools[$schoolName] = $school;

        return $school;
    }
}
