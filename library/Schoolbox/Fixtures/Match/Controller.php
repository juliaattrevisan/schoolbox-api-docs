<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Match;

use Alaress_Date_Format;
use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Collection;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use \Schoolbox\Fixtures\Season\Model as Season;
use \Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use \Schoolbox\Fixtures\Division\Model as Division;
use \Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\Fixtures\Team\Model as Team;
use \Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use \Schoolbox\Fixtures\Match\Model as Match;
use \Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use \Schoolbox\Fixtures\ItineraryItem\Model as ItineraryItem;
use \Schoolbox\Fixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use \Schoolbox\Fixtures\Venue\Model as Venue;
use \Schoolbox\Fixtures\Venue\Mapper as VenueMapper;
use \Schoolbox\Fixtures\School\Model as School;
use \Schoolbox\Fixtures\School\Mapper as SchoolMapper;
use \Schoolbox\TagCloud\Mapper as TagCloudMapper;

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
     * @var MatchMapper
     */
    protected $matchMapper;

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
        parent::__construct(MatchMapper::getInstance());
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
         *
         * @var Collection|Match[][] $seasonMatches
         */
        $seasonMatches = new Collection();

        // Flag to indicate whether any extant matches are scheduled in the division's current season
        $isMatchesInCurrentSeason = false;

        /* Accumulate faceless schools and group by season
         */
        /** @var Match $currentMatch */
        foreach ($allMatches as $currentMatch) {

            if ($currentMatch->getSeason()->getId() == $division->getSeason()->getId()) {
                $isMatchesInCurrentSeason = true;
            }

            /* If we don't know anything about the entities participating, use the least-assuming entity (a school) as
             * a placeholder. The system shouldn't be in this state, but somehow is.
             */
            if (!$currentMatch->getHomeTeam() instanceof Team) {
                if (!$currentMatch->getHomeSchool() instanceof School) {
                    $facelessSchool = $this->getSchoolMapper()->createModel()->setName('Unknown');
                    $currentMatch->setHomeSchool($facelessSchool);
                }
            }

            /* If we don't know anything about the entities participating, use the least-assuming entity (a school) as
             * a placeholder. The system shouldn't be in this state, but somehow is.
             */
            if (!$currentMatch->getAwayTeam() instanceof Team) {
                if (!$currentMatch->getAwaySchool() instanceof School) {
                    $facelessSchool = $this->getSchoolMapper()->createModel()->setName('Unknown');
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/list.twig', [
            'division'   => $division,
            'allSeasons' => $allSeasons,
            'allMatches' => $seasonMatches
        ]));
    }

    /**
     * Populate the Response object with a form that provides a means to mutate the given (new) entity.
     * Only text/html is Accept-able.
     *
     * @param Match $entity
     * @param Division $context
     * @throws Exception
     * @return void
     */
    public function getCreateForm($entity, $context = null)
    {
        $entity->setDivision($context);

        // Was a season specified ?
        $seasonId = $this->getRequest()->query['season'];

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
     * @param Match $entity
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
     * @param Match $match
     * @param Division $division
     * @return void
     */
    protected function getForm($match, $division = null)
    {
        // Do not permit creation or modification of matches in archived seasons.
        if ($match->getSeason()->isArchived()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
        }

        $allItineraryItems = $this->getItineraryItemMapper()->findByColumn('match_id', $match->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);
        $match->setItinerary($allItineraryItems);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/create.twig', [
            'match'     => $match,
            'tagPrefix' => TagCloudMapper::SCHOOL_PREFIX,
            'action'    => $match->getId() ? 'Modify' : 'Create'
        ]));
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

        $this->getResponse()->redirect->to($this->getRouter()->generate('sportsfixtures.getLanding', []));
    }

    /**
     * Modify a Match
     *
     * @param Match $entity
     */
    public function persistEntity($entity)
    {
        // Division
        try {
            $division = DivisionMapper::getInstance()->findById($this->getRequest()->post['divisionId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        // Name
        $name = $this->getRequest()->post['name'];

        // Season
        try {
            $season = SeasonMapper::getInstance()->findById($this->getRequest()->post['seasonId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
        }

        // Start At
        try {
            $dateTime = $this->getRequest()->post['startAtDate'];
            $startAt  = \Alaress_Date_Format::newLocal($dateTime);
        } catch (\InvalidArgumentException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        // Venue
        try {
            $venue = $this->getVenueMapper()->findById($this->getRequest()->post['venueId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
        }

        // Teams
        try {
            list ($homeTeam, $homeSchool) = $this->calculateTeam($this->getRequest()->post['homeTeamId']);
            list ($awayTeam, $awaySchool) = $this->calculateTeam($this->getRequest()->post['awayTeamId']);

        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        // Notes
        $notes = $this->getRequest()->post['notes'];

        // Itinerary
        $itinerary = $this->transposeItineraryPost($this->getRequest()->post['itinerary']);

        $this->saveMatch($entity, $division, $name, $season, $startAt, $homeTeam, $awayTeam, $homeSchool, $awaySchool, $venue, $notes, $itinerary);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesMatch.getList', [
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

        $itCount = count($itPost['matchId']);

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
            $it->setMatch((new Match())->setId($itPost['matchId'][$i]));
            $it->setDescription($itPost['description'][$i]);
            $it->setStartAt($datetime);

            $c->add($it);
        }

        return $c;
    }

    /**
     * @param string $teamId
     * @param string $prefix
     * @return array $return
     * @var Team $return[0]
     * @var School $return[1]
     */
    protected function calculateTeam($teamId, $prefix = TagCloudMapper::SCHOOL_PREFIX)
    {
        if (preg_match("/$prefix(.*)/", $teamId, $matches)) {
            // Away team is a faceless school, save the team as null
            return [$this->getTeamMapper()->createModel(), $this->getSchoolMapper()->findById($matches[1])];
        } else {
            return [$this->getTeamMapper()->findById($teamId), $this->getSchoolMapper()->createModel()];
        }

    }

    /**
     * Save Match data to db
     *
     * @param Model $match
     * @param Division $division
     * @param string $name
     * @param Season $season ,
     * @param Alaress_Date_Format $startAt
     * @param Team $homeTeam
     * @param Team $awayTeam
     * @param School $homeSchool
     * @param School $awaySchool
     * @param Venue $venue
     * @param string $notes
     * @param Collection|ItineraryItem[] $itinerary
     */
    private function saveMatch(Match $match, Division $division, $name, Season $season, Alaress_Date_Format $startAt, Team $homeTeam, Team $awayTeam, School $homeSchool, School $awaySchool, Venue $venue, $notes, Collection $itinerary)
    {
        $match
            ->setDivision($division)
            ->setName($name)
            ->setSeason($season)
            ->setStartAt($startAt)
            ->setHomeTeam($homeTeam)
            ->setAwayTeam($awayTeam)
            ->setHomeSchool($homeSchool)
            ->setAwaySchool($awaySchool)
            ->setVenue($venue)
            ->setNotes($notes)
            ->setUpdatedAt(\Alaress_Date_Format::now())
            ->setItinerary($itinerary);

        $this->getMapper()->save($match);
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
     * @return MatchMapper
     */
    public function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     * @return static
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
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
