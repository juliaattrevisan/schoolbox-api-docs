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

    public const string EXTERNAL_ID_PREFIX = 'socs:';

    // Standard Results Feed date format (https://help.socscms.com/Page.asp?ID=276&CatID=13)
    public const string SOCS_DATE_FORMAT = 'j M Y';

    public const SOCS_FIXTURES = 'SOCS Fixtures';

    public function __construct(
        private LoggerInterface $logger,
        private bool $perform,
        private SeasonMapper $seasonMapper,
        private SportMapper $sportMapper,
        private DivisionMapper $divisionMapper,
        private TeamMapper $teamMapper,
        private VenueMapper $venueMapper,
        private SchoolMapper $schoolMapper,
        private string $externalIdPrefix = self::EXTERNAL_ID_PREFIX,
        private string $seasonName = self::SOCS_FIXTURES,
    ) {
    }

    /**
     * Find or create the shared "SOCS Fixtures" season spanning the current year and next.
     *
     * SOCS imports deliberately use one rolling season rather than creating one Schoolbox season per SOCS season.
     * This keeps imported fixtures grouped in a single integration-owned season across yearly boundaries.
     *
     * @return SeasonModel
     * @throws Exception
     */
    public function recallSeason(): SeasonModel
    {
        if ($this->season instanceof SeasonModel) {
            return $this->season;
        }

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
            $season = $this->seasonMapper->findOneByColumn('name', $this->seasonName);

            $this->logger->debug(sprintf(
                'Reusing existing SOCS season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SeasonModel $season */
            $season = $e->getDeletedObject();
            if ($this->perform) {
                $this->seasonMapper->restoreSoftDeleted($season);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS season %s', $season->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SeasonModel $season */
            $season = $this->seasonMapper->createModel();
            $this->logger->debug(sprintf(
                'Creating new SOCS season %s',
                $this->seasonName,
            ));
        }

        // update updated at, we have seen this, we need to record it
        // the season name and dates in case they have changed since the last import run
        $season->setUpdatedAt($now)
            ->setName($this->seasonName)
            ->setStart($start)
            ->setEnd($end);

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
    public function recallSport(string $sportName, ?string $identity = null): SportModel
    {
        $externalId = $this->externalIdPrefix . ($identity ?? $sportName);
        if (isset($this->sports[$externalId])) {
            return $this->sports[$externalId];
        }

        try {
            /** @var SportModel $sport */
            $sport = $this->sportMapper->findOneByColumn($identity === null ? 'name' : 'external_id', $identity === null ? $sportName : $externalId);
            $this->logger->debug(sprintf('Reusing existing SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SportModel $sport */
            $sport = $e->getDeletedObject();
            if ($this->perform) {
                $this->sportMapper->restoreSoftDeleted($sport);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS sport %s', $sport->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SportModel $sport */
            $sport = $this->sportMapper->createModel();
            $this->logger->debug(sprintf('Creating new SOCS sport %s', $sportName));
        }

        // update updated at, we have seen this, we need to record it
        // the sport name and externalID in case they have changed since the last import run
        $sport->setUpdatedAt(\Alaress_Date_Format::now())
              ->setName($sportName)
              ->setExternalId($externalId);

        if ($this->perform) {
            $this->sportMapper->save($sport);
        }

        $this->sports[$externalId] = $sport;

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
    public function recallDivision(string $divisionName, SportModel $sport, SeasonModel $season, ?string $identity = null): DivisionModel
    {
        $externalId = $this->externalIdPrefix . ($identity ?? $divisionName);
        // Co-curricular divisions use their ClubID identity; standard fixtures need sport/season context to prevent same-named divisions crossing sports.
        $cacheKey = $identity ?? sprintf('%s:%s:%s', spl_object_id($sport), spl_object_id($season), $divisionName);
        if (isset($this->divisions[$cacheKey])) {
            return $this->divisions[$cacheKey];
        }

        try {
            /** @var DivisionModel $division */
            $division = $identity === null ? $this->divisionMapper->findOneByColumnList([
                'name' => $divisionName,
                'sport_id' => $sport->getId(),
                'season_id' => $season->getId(),
            ]) : $this->divisionMapper->findOneByColumn('external_id', $externalId);
            $this->logger->debug(sprintf('Reusing existing SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var DivisionModel $division */
            $division = $e->getDeletedObject();
            if ($this->perform) {
                $this->divisionMapper->restoreSoftDeleted($division);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS division %s', $division->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var DivisionModel $division */
            $division = $this->divisionMapper->createModel();
            $this->logger->debug(sprintf('Creating new SOCS division %s', $divisionName));
        }

        // update updated at, we have seen this, we need to record it
        // division name, sport, season and externalID in case they have changed since the last import run
        $division->setUpdatedAt(\Alaress_Date_Format::now())
            ->setName($divisionName)
            ->setSport($sport)
            ->setSeason($season)
            ->setExternalId($externalId);

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
    public function recallTeam(string $teamName, DivisionModel $division, ?string $identity = null): TeamModel
    {
        $externalId = $this->externalIdPrefix . ($identity ?? $teamName);
        $cacheKey = sprintf(
            '%s:%s:%s:%s',
            $division->getSeason()->getName(),
            $division->getSport()->getName(),
            $division->getName(),
            $externalId
        );
        if (isset($this->teams[$cacheKey])) {
            return $this->teams[$cacheKey];
        }

        try {
            /** @var TeamModel $team */
            $team = $identity === null ? $this->teamMapper->findOneByColumnList([
                'name' => $teamName,
                'division_id' => $division->getId(),
            ]) : $this->teamMapper->findOneByColumn('external_id', $externalId);
            $this->logger->debug(sprintf('Reusing existing SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var TeamModel $team */
            $team = $e->getDeletedObject();
            if ($this->perform) {
                $this->teamMapper->restoreSoftDeleted($team);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS team %s', $team->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var TeamModel $team */
            $team = $this->teamMapper->createModel();
            $this->logger->debug(sprintf('Creating new SOCS team %s', $teamName));
        }

        // update updated at, we have seen this, we need to record it
        // team name division and externalID in case they have changed since the last import run
        $team->setUpdatedAt(\Alaress_Date_Format::now())
             ->setName($teamName)
             ->setDivision($division)
             ->setExternalId($externalId);

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
        $venueKey = $this->externalIdPrefix . $venueName;
        if (isset($this->venues[$venueKey])) {
            return $this->venues[$venueKey];
        }

        try {
            /** @var VenueModel $venue */
            $venue = $this->venueMapper->findOneByColumn(
                $this->externalIdPrefix === self::EXTERNAL_ID_PREFIX ? 'name' : 'external_id',
                $this->externalIdPrefix === self::EXTERNAL_ID_PREFIX ? $venueName : $this->externalIdPrefix . $venueName,
            );
            $this->logger->debug(sprintf('Reusing existing SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var VenueModel $venue */
            $venue = $e->getDeletedObject();
            if ($this->perform) {
                $this->venueMapper->restoreSoftDeleted($venue);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS venue %s', $venue->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var VenueModel $venue */
            $venue = $this->venueMapper->createModel();
            $this->logger->debug(sprintf('Creating new SOCS venue %s', $venueName));
        }

        // update updated at, we have seen this, we need to record it
        // venue name and externalID in case they have changed since the last import run
        $venue->setUpdatedAt(\Alaress_Date_Format::now())
              ->setName($venueName)
              ->setExternalId($this->externalIdPrefix . $venueName);

        if ($this->perform) {
            $this->venueMapper->save($venue);
        }

        $this->venues[$venueKey] = $venue;

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
            $this->logger->debug(sprintf('Reusing existing SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var SchoolModel $school */
            $school = $e->getDeletedObject();
            if ($this->perform) {
                $this->schoolMapper->restoreSoftDeleted($school);
            }
            $this->logger->debug(sprintf('Resurrecting previously-deleted SOCS school %s', $school->getName()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /** @var SchoolModel $school */
            $school = $this->schoolMapper->createModel();
            $this->logger->debug(sprintf('Creating new SOCS school %s', $schoolName));
        }

        // update updated at, we have seen this, we need to record it
        // school name to ensure it stays constant, and we remember we need to check and ensure it stays in sync
        $school->setUpdatedAt(\Alaress_Date_Format::now())
           ->setName($schoolName);

        if ($this->perform) {
            $this->schoolMapper->save($school);
        }

        $this->schools[$schoolName] = $school;

        return $school;
    }
}
