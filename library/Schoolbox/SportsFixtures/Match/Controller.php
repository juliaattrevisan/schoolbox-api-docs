<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Match;

use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
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
     * @param null $context
     */
    public function getList($context = null)
    {
        /* TODO: Not sure if we need this here
         * but I'll set it up anyway just in case
         */
        try {
            /** @var Division $context */
            $sport = $this->getSportMapper()->findById($context->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        try {
            $division = $this->getDivisionMapper()->findById($context->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        /** @var MatchMapper $mapper */
        $mapper = $this->getMapper();

        $allMatches = $mapper->findByColumn('division_id', $division->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/list.twig', [
            'sport' => $sport,
            'division' => $context,
            'allMatches' => $allMatches,
            'schoolMapper' => $this->getSchoolTagCloudMapper()
        ]));
    }

    /**
     * @param Match $entity
     * @param object $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            try {
                $context = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
            }
        }

        try {
            $sport = $this->getSportMapper()->findById($context->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $allItineraryItems = $this->getItineraryItemMapper()->findByColumn('match_id', $entity->getId(), [
            'order' => [
                'start_at' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/create.twig', [
            'sport'             => $sport,
            'division'          => $context,
            'match'             => $entity,
            'schoolMapper'      => $this->getSchoolTagCloudMapper(),
            'schoolToken'       => TagCloudMapper::SCHOOL_PREFIX,
            'allItineraryItems' => $allItineraryItems,
            'action'            => $entity->getId() ? 'Modify' : 'Create'
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
        try {
            $division = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        try {
            $venue = $this->getVenueMapper()->findById($entity->getVenue()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/entity.twig', [
            'sport'    => $sport,
            'division' => $division,
            'match'    => $entity,
            'venue'    => $venue,
        ]));
    }

    /**
     * Modify a Match
     *
     * @param Match $entity
     */
    public function persistEntity($entity)
    {
        try {
            $divisionId = $this->getRequest()->post['divisionId'];
            $division   = $this->getDivisionMapper()->findById($divisionId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        $date    = "{$this->getRequest()->post['startAtDate']} {$this->getRequest()->post['startAtTime']}";
        $startAt = \Alaress_Date_Format::newLocal($date);

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

        try {
            /** @var Venue $venue */
            $venueId = $this->getRequest()->post['venueId'];
            $venue   = $this->getVenueMapper()->findById($venueId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
        }

        $this->saveMatch($entity, $division, $this->getRequest()->post['name'], $startAt, $homeTeam, $awayTeam, $homeSchoolId, $awaySchoolId, $venue);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesMatch.getList', [
            'contextId' => $divisionId
        ]));
    }

    /**
     * Save Match data to db
     *
     * @param Model $match
     * @param Division $division
     * @param $name
     * @param \Alaress_Date_Format $startAt
     * @param Team $homeTeam
     * @param Team $awayTeam
     * @param int $homeSchoolId
     * @param int $awaySchoolId
     * @param Venue $venue
     */
    private function saveMatch(Match $match, Division $division, $name, \Alaress_Date_Format $startAt, Team $homeTeam, Team $awayTeam, $homeSchoolId, $awaySchoolId, Venue $venue)
    {
        $match->setDivision($division);
        $match->setName($name);
        $match->setStartAt($startAt);
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
        $match->setHomeSchoolId($homeSchoolId);
        $match->setAwaySchoolId($awaySchoolId);
        $match->setVenue($venue);
        $match->setUpdatedAt(\Alaress_Date_Format::now());

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
