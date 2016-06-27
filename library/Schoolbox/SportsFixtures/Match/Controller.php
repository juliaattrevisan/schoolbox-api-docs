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
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(MatchMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
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
            $division = $this->getDivisionMapper()->findById($context->getId());

            $allTeams = $this->getMapper()->findByColumn('division_id', $division->getId(), [
                'order' => [
                    'start_at' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/list.twig', [
                'sport' => $sport,
                'division' => $context,
                'allMatches' => $allTeams
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * @param Match $entity
     * @param object $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        try {
            if (empty($context)) {
                $context = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
            }

            $sport = $this->getSportMapper()->findById($context->getSport()->getId());

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/create.twig', [
                'sport'    => $sport,
                'division' => $context,
                'match'    => $entity,
                'action'   => $entity->getId() ? 'Modify' : 'Create'
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
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
        $division = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
        $sport    = $this->getSportMapper()->findById($division->getSport()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesMatch/entity.twig', [
            'sport' => $sport,
            'division' => $division,
            'match' => $entity
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

            $date = "{$this->getRequest()->post['startAtDate']} {$this->getRequest()->post['startAtTime']}";
            $startAt = \Alaress_Date_Format::newLocal($date);

            $homeTeam = $this->getTeamMapper()->findById($this->getRequest()->post['homeTeamId']);
            $awayTeam = $this->getTeamMapper()->findById($this->getRequest()->post['awayTeamId']);

            $this->saveMatch($entity, $division, $this->getRequest()->post['name'], $startAt, $homeTeam, $awayTeam);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesMatch.getList', [
                'contextId' => $divisionId
            ]));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
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
     */
    private function saveMatch(Match $match, Division $division, $name, \Alaress_Date_Format $startAt, Team $homeTeam, Team $awayTeam)
    {
        $match->setDivision($division);
        $match->setName($name);
        $match->setStartAt($startAt);
        $match->setHomeTeam($homeTeam);
        $match->setAwayTeam($awayTeam);
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
}
