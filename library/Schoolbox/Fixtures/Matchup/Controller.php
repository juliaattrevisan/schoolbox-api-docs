<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup;

use Alaress_Date_Format;
use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use Schoolbox\Fixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use Schoolbox\Fixtures\Venue\Model as Venue;
use Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use Schoolbox\Fixtures\School\Model as School;
use Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use Schoolbox\TagCloud\Mapper as TagCloudMapper;

class Controller extends AbstractController
{
    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var SeasonMapper
     */
    protected $seasonMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var MatchupMapper
     */
    protected $matchupMapper;

    /**
     * @var VenueMapper
     */
    protected $venueMapper;

    /**
     * @var ItineraryItemMapper
     */
    protected $itineraryItemMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var SchoolMapper
     */
    protected $schoolMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(MatchupMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setSeasonMapper(SeasonMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setItineraryItemMapper(ItineraryItemMapper::getInstance());
        $this->setVenueMapper(VenueMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setSchoolMapper(SchoolMapper::getInstance());
    }

    /**
     * List of Matches
     *
     * @param Division $context
     */
    public function getList($context = null)
    {
        $division = $context;

        $allMatches = $this->getMapper()->findByColumn('division_id', $division->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);

        /** @var Collection|Season[] $allSeasons */
        $allSeasons = new Collection();

        /**
         * Each item is a Collection of matches, indexed by seasonId
         */
        $seasonMatches = new Collection();

        // Flag to indicate whether any extant matches are scheduled in the division's current season
        $isMatchesInCurrentSeason = false;

        /* Accumulate faceless schools and group by season
         */
        /** @var Matchup $currentMatch */
        foreach ($allMatches as $currentMatch) {
            if ($currentMatch->getSeason()->getId() == $division->getSeason()->getId()) {
                $isMatchesInCurrentSeason = true;
            }

            /* If we don't know anything about the entities participating, use the least-assuming entity (a school) as
             * a placeholder. The system shouldn't be in this state, but somehow is.
             */
            if (!$currentMatch->getHomeTeam() instanceof Team) {
                if (!$currentMatch->getHomeSchool() instanceof School) {
                    $facelessSchool = $this->getSchoolMapper()->createModel()->setName(null);
                    $currentMatch->setHomeSchool($facelessSchool);
                }
            }

            /* If we don't know anything about the entities participating, use the least-assuming entity (a school) as
             * a placeholder. The system shouldn't be in this state, but somehow is.
             */
            if (!$currentMatch->getAwayTeam() instanceof Team) {
                if (!$currentMatch->getAwaySchool() instanceof School) {
                    $facelessSchool = $this->getSchoolMapper()->createModel()->setName(null);
                    $currentMatch->setAwaySchool($facelessSchool);
                }
            }

            // Is is the first time we've seen this season ?
            if (!$seasonMatches->getOffset($currentMatch->getSeason()->getId()) instanceof Collection) {
                $seasonMatches->setOffset($currentMatch->getSeason()->getId(), new Collection());
                $allSeasons->add($currentMatch->getSeason());
            }

            $seasonMatches->getOffset($currentMatch->getSeason()->getId())->add($currentMatch);
        }

        // Ensure we always render the division's current season, so we can add matches even if empty
        if (!$isMatchesInCurrentSeason) {
            $allSeasons->add($division->getSeason());
        }

        // Sort seasons by start then end dates
        $allSeasons->sortByMultiFields([
            'start' => 'desc',
            'end'   => 'desc',
        ]);

        // Sort matches within seasons by startAt datetime
        foreach ($seasonMatches as $matches) {
            $matches->sortBy('startAt', 'asc');
        }

        $this->response = $this->responseFromTemplate('@sportsfixturesMatch/list.twig', [
            'division'   => $division,
            'allSeasons' => $allSeasons,
            'allMatches' => $seasonMatches
        ]);
    }

    /**
     * Populate the Response object with a form that provides a means to mutate the given (new) entity.
     * Only text/html is Accept-able.
     *
     * @param Matchup $entity
     * @param Division $context
     * @throws Exception
     * @return void
     */
    public function getCreateForm($entity, $context = null)
    {
        $entity->setDivision($context);

        // Was a season specified ?
        $seasonId = $this->requestQuery['season'] ?? null;

        if ($seasonId) {
            try {
                $season = $this->getSeasonMapper()->findById($seasonId);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
            }
        } else {
            // No season specified. Default to division's current season.
            $season = $context->getSeason();
        }

        $entity->setSeason($season);

        // now the prefab is done, delegate to the common functionality
        $this->getForm($entity, $context);
    }

    /**
     * Populate the Response object with a form that provides a means to mutate the given entity.
     * Only text/html is Accept-able.
     * If entity is deleted, respond with '410 Gone'
     *
     * @param Matchup $entity
     * @throws Exception
     * @return void
     */
    public function getModifyForm($entity)
    {
        // Is this an update of an extant match?
        $division = $entity->getDivision();

        // Hydrate the division's season
        try {
            $division->setSeason(SeasonMapper::getInstance()->findById($division->getSeason()->getId()));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
        }

        // Hydrate the division's sport
        try {
            $division->setSport(SportMapper::getInstance()->findById($division->getSport()->getId()));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        // now the prefab is done, delegate to the common functionality
        $this->getForm($entity, $division);
    }


    /**
     * @param Matchup $matchup
     * @param Division $division
     * @return void
     */
    protected function getForm($matchup, $division = null)
    {
        // Do not permit creation or modification of matches in archived seasons.
        if ($matchup->getSeason()->isArchived()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
        }

        $allItineraryItems = $this->getItineraryItemMapper()->findByColumn('match_id', $matchup->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);
        $matchup->setItinerary($allItineraryItems);

        $this->response = $this->responseFromTemplate('@sportsfixturesMatch/create.twig', [
            'match'     => $matchup,
            'tagPrefix' => TagCloudMapper::SCHOOL_PREFIX,
            'action'    => $matchup->getId() ? 'Modify' : 'Create'
        ]);
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param object $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity)
    {

        /* noop
         *
         * All match pages are on a per-team basis
         */

        $this->response = $this->redirectFound($this->routeGenerator->generate('sportsfixtures.getLanding', []));
    }

    /**
     * Modify a Matchup
     *
     * @param Matchup $entity
     */
    public function persistEntity($entity)
    {
        // Division
        try {
            $division = DivisionMapper::getInstance()->findById($this->requestPost->get('divisionId'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        // Name
        $name = $this->requestPost->get('name');

        // Season
        try {
            $season = SeasonMapper::getInstance()->findById($this->requestPost->get('seasonId'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
        }

        // Start At
        try {
            $dateTime = $this->requestPost->get('startAtDate');
            $startAt  = \Alaress_Date_Format::newLocal($dateTime);
        } catch (\InvalidArgumentException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        // Finish At
        try {
            $dateTime = $this->requestPost->get('finishAtDate');
            $finishAt  = \Alaress_Date_Format::newLocal($dateTime);
        } catch (\InvalidArgumentException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        // Venue
        $venue = null;

        if ('' !== (string)$this->requestPost->get('venueId')) {
            try {
                $venue = $this->getVenueMapper()->findById($this->requestPost->get('venueId'));
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
            }
        }

        // Teams
        try {
            list ($homeTeam, $homeSchool) = $this->calculateTeam($this->requestPost->get('homeTeamId'));
            list ($awayTeam, $awaySchool) = $this->calculateTeam($this->requestPost->get('awayTeamId'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        // Notes
        $notes = $this->requestPost->get('notes');

        // Itinerary
        $itinerary = $this->transposeItineraryPost($this->requestPost->get('itinerary', []));

        $this->saveMatch($entity, $division, $name, $season, $startAt, $finishAt, $homeTeam, $awayTeam, $homeSchool, $awaySchool, $venue, $notes, $itinerary);

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('sportsfixturesMatch.getList', [
            'contextId' => $division->getId()
        ]));
    }

    /**
     * Transposes the post-data of itinerary items into a collection of actual objects
     *
     * @param array $itPost
     * @return Collection|ItineraryItem[]
     * @throws MapperException
     */
    protected function transposeItineraryPost(array $itPost)
    {
        $c = $this->getItineraryItemMapper()->createCollection();

        $itCount = count($itPost['matchId'] ?? []);

        for ($i = 0; $i < $itCount; ++$i) {
            if (empty($itPost['description'][$i])) {
                continue;
            }

            try {
                $datetime = Alaress_Date_Format::newLocal($itPost['date'][$i]);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $it = new ItineraryItem();
            $it->setId($itPost['itemId'][$i]);
            $it->setMatchup((new Matchup())->setId($itPost['matchId'][$i]));
            $it->setDescription($itPost['description'][$i]);
            $it->setStartAt($datetime);

            $c->add($it);
        }

        return $c;
    }


    /**
     * Returns a (Team, School) tuple, using stub instances where required
     * to fulfil this struct
     *     Team $return[0]
     *     School $return[1]
     *
     * @param string $teamId
     * @param string $prefix
     * @return array
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    protected function calculateTeam($teamId, $prefix = TagCloudMapper::SCHOOL_PREFIX)
    {
        if (preg_match("/$prefix(.*)/", $teamId, $matches)) {
            // Away team is a faceless school, save the team as null
            return [$this->getTeamMapper()->createModel(), $this->getSchoolMapper()->findById($matches[1])];
        }

        return [$this->getTeamMapper()->findById($teamId), $this->getSchoolMapper()->createModel()];
    }


    /**
     * Save Match data to db
     *
     * @param Model               $matchup
     * @param Division            $division
     * @param string              $name
     * @param Season              $season ,
     * @param Alaress_Date_Format $startAt
     * @param Alaress_Date_Format $finishAt
     * @param Team                $homeTeam
     * @param Team                $awayTeam
     * @param School              $homeSchool
     * @param School              $awaySchool
     * @param Venue|null          $venue
     * @param string              $notes
     * @param Collection          $itinerary
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveMatch(
        Matchup $matchup,
        Division $division,
        string $name,
        Season $season,
        Alaress_Date_Format $startAt,
        Alaress_Date_Format $finishAt,
        Team $homeTeam,
        Team $awayTeam,
        School $homeSchool,
        School $awaySchool,
        ?Venue $venue,
        string $notes,
        Collection $itinerary
    ) {
        $matchup
            ->setDivision($division)
            ->setName($name)
            ->setSeason($season)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setHomeTeam($homeTeam)
            ->setAwayTeam($awayTeam)
            ->setHomeSchool($homeSchool)
            ->setAwaySchool($awaySchool)
            ->setVenue($venue)
            ->setNotes($notes)
            ->setUpdatedAt(\Alaress_Date_Format::now())
            ->setItinerary($itinerary);

        $this->getMapper()->save($matchup);
    }

    /* Getters/Setters
     */

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     * @return static
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
        return $this;
    }

    /**
     * @return SeasonMapper
     */
    protected function getSeasonMapper()
    {
        return $this->seasonMapper;
    }

    /**
     * @param SeasonMapper $seasonMapper
     * @return static
     */
    protected function setSeasonMapper($seasonMapper)
    {
        $this->seasonMapper = $seasonMapper;
        return $this;
    }

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     * @return static
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return static
     */
    public function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return MatchupMapper
     */
    public function getMatchupMapper()
    {
        return $this->matchupMapper;
    }

    /**
     * @param MatchupMapper $matchupMapper
     * @return static
     */
    public function setMatchMapper($matchupMapper)
    {
        $this->matchupMapper = $matchupMapper;
        return $this;
    }

    /**
     * @return ItineraryItemMapper
     */
    public function getItineraryItemMapper()
    {
        return $this->itineraryItemMapper;
    }

    /**
     * @param ItineraryItemMapper $itineraryItemMapper
     * @return static
     */
    public function setItineraryItemMapper($itineraryItemMapper)
    {
        $this->itineraryItemMapper = $itineraryItemMapper;
        return $this;
    }

    /**
     * @return VenueMapper
     */
    public function getVenueMapper()
    {
        return $this->venueMapper;
    }

    /**
     * @param VenueMapper $venueMapper
     * @return static
     */
    public function setVenueMapper($venueMapper)
    {
        $this->venueMapper = $venueMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return SchoolMapper
     */
    protected function getSchoolMapper()
    {
        return $this->schoolMapper;
    }

    /**
     * @param SchoolMapper $schoolMapper
     * @return static
     */
    protected function setSchoolMapper($schoolMapper)
    {
        $this->schoolMapper = $schoolMapper;
        return $this;
    }
}
