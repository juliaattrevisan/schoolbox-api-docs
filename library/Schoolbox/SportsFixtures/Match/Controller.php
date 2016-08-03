<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Match;

use Alaress_Date_Format;
use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Season\Model as Season;
use \Schoolbox\SportsFixtures\Season\Mapper as SeasonMapper;
use \Schoolbox\SportsFixtures\Division\Model as Division;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use \Schoolbox\SportsFixtures\Match\Model as Match;
use \Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use \Schoolbox\SportsFixtures\ItineraryItem\Mapper as ItineraryItemMapper;
use \Schoolbox\SportsFixtures\Venue\Model as Venue;
use \Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;
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
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

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
        $this->setSchoolTagCloudMapper(TagCloudMapper::getInstance('sportsfixtures_school', 'id', 'name'));
    }

    /**
     * List of Matches
     *
     * @param Division $context
     */
    public function getList($context = null)
    {
        $division = $context;

        // Division/Match Season extant/deleted/archived logic for list view:
        //
        //     matchSeason:    {extant/archived/deleted}
        //     divisionSeason: {extant/archived/deleted}
        //
        // If the division season is deleted or archived, 404 the list.
        // For each match, if attached to archived season, show it greyed out, without modify action buttons.

        // Don't display divisions attached to archived seasons
        if($division->getSeason()->isArchived()){
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        /** @var MatchMapper $mapper */
        $mapper = $this->getMapper();

        $allMatches = $mapper->findByColumn('division_id', $division->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);



        /* Accumulate faceless schools
         */
        /** @var Match $currentMatch */
        foreach ($allMatches as $currentMatch) {

            if ($currentMatch->getHomeSchoolId()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($this->getSchoolTagCloudMapper()->getTagName($currentMatch->getHomeSchoolId()));
                $currentMatch->setHomeTeam($facelessTeam);
            }

            if ($currentMatch->getAwaySchoolId()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($this->getSchoolTagCloudMapper()->getTagName($currentMatch->getAwaySchoolId()));
                $currentMatch->setAwayTeam($facelessTeam);
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/list.twig', [
            'sport' => $sport,
            'division' => $context,
            'allMatches' => $allMatches,
        ]));
    }

    /**
     * @param Match $match
     * @param Division $division
     * @return void
     */
    protected function getForm($match, $division = null)
    {
        // Is this an update of an extant match ?
        if($match->getId()){
            $division = $match->getDivision();

            try{
                $division->setSeason(SeasonMapper::getInstance()->findById($division->getSeason()->getId()));
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_NOT_FOUND);
            }

            try {
                $division->setSport(SportMapper::getInstance()->findById($division->getSport()->getId()));
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
            }
        }
        // Else creating new match
        else {
            $match->setSeason($division->getSeason());
        }

        $match->setDivision($division);

        // Division/Match Season extant/deleted/archived logic for detailed view:
        //
        //     action:         {create/update}
        //     matchSeason:    {extant/archived/deleted}
        //     divisionSeason: {extant/archived/deleted}
        //
        // We use the 'archived' as a view filter. They exist but their details can't be accessed. In some views we
        // show them greyed out when it may be confusing otherwise (see below).

        // Case 1: division season is archived or deleted
        if($division->getSeason()->isArchived()){
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        // Case 2: division season is extant, but match season is archived
        // In this case, we would still show the match in the list view, but it would be greyed out
        // with no modify link to indicate you should not get here.
        if($match->getSeason()->isArchived()){
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        // Case 3: division and match seasons are extant
        // continue...

        $allItineraryItems = $this->getItineraryItemMapper()->findByColumn('match_id', $match->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);


        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/create.twig', [
            'sport'             => $division->getSport(),
            'division'          => $match->getDivision(),
            'match'             => $match,
            'schoolMapper'      => $this->getSchoolTagCloudMapper(),
            'schoolToken'       => TagCloudMapper::SCHOOL_PREFIX,
            'allItineraryItems' => $allItineraryItems,
            'action'            => $match->getId() ? 'Modify' : 'Create'
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
        $dateTime = "{$this->getRequest()->post['startAtDate']} {$this->getRequest()->post['startAtTime']}";
        $startAt  = \Alaress_Date_Format::newLocal($dateTime);

        // Venue
        try {
            $venue   = $this->getVenueMapper()->findById($this->getRequest()->post['venueId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
        }

        // Teams
        try {
            if (preg_match("/" . TagCloudMapper::SCHOOL_PREFIX . "(.*)/", $this->getRequest()->post['homeTeamId'], $matches)) {
                // Home team is a faceless school, save the team as null
                $homeTeam = new Team();
                $homeSchoolId = $matches[1];
            } else {
                $homeTeam = $this->getTeamMapper()->findById($this->getRequest()->post['homeTeamId']);
                $homeSchoolId = null;
            }

            if (preg_match("/" . TagCloudMapper::SCHOOL_PREFIX . "(.*)/", $this->getRequest()->post['awayTeamId'], $matches)) {
                // Away team is a faceless school, save the team as null
                $awayTeam = new Team();
                $awaySchoolId = $matches[1];
            } else {
                $awayTeam = $this->getTeamMapper()->findById($this->getRequest()->post['awayTeamId']);
                $awaySchoolId = null;
            }
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        // Notes
        $notes = $this->getRequest()->post['notes'];

        $this->saveMatch($entity, $division, $name, $season, $startAt, $homeTeam, $awayTeam, $homeSchoolId, $awaySchoolId, $venue, $notes);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesMatch.getList', [
            'contextId' => $division->getId()
        ]));
    }

    /**
     * Save Match data to db
     *
     * @param Model $match
     * @param Division $division
     * @param string $name
     * @param Season $season,
     * @param Alaress_Date_Format $startAt
     * @param Team $homeTeam
     * @param Team $awayTeam
     * @param int $homeSchoolId
     * @param int $awaySchoolId
     * @param Venue $venue
     * @param string $notes
     */
    private function saveMatch(Match $match, Division $division, $name, Season $season, Alaress_Date_Format $startAt, Team $homeTeam, Team $awayTeam, $homeSchoolId, $awaySchoolId, Venue $venue, $notes)
    {
        $match
            ->setDivision($division)
            ->setName($name)
            ->setSeason($season)
            ->setStartAt($startAt)
            ->setHomeTeam($homeTeam)
            ->setAwayTeam($awayTeam)
            ->setHomeSchoolId($homeSchoolId)
            ->setAwaySchoolId($awaySchoolId)
            ->setVenue($venue)
            ->setNotes($notes)
            ->setUpdatedAt(\Alaress_Date_Format::now());

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
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
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
     */
    protected function setSeasonMapper($seasonMapper)
    {
        $this->seasonMapper = $seasonMapper;
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
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
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
     * @return $this
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
     */
    public function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
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
     */
    public function setItineraryItemMapper($itineraryItemMapper)
    {
        $this->itineraryItemMapper = $itineraryItemMapper;
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
     */
    public function setVenueMapper($venueMapper)
    {
        $this->venueMapper = $venueMapper;
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
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * @return TagCloudMapper
     */
    protected function getSchoolTagCloudMapper()
    {
        return $this->schoolTagCloudMapper;
    }

    /**
     * @param TagCloudMapper $schoolTagCloudMapper
     */
    protected function setSchoolTagCloudMapper($schoolTagCloudMapper)
    {
        $this->schoolTagCloudMapper = $schoolTagCloudMapper;
    }
}
