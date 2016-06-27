<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team;

use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Division\Model as Division;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Team\Model as Team;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;

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
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(TeamMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param Team $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity)
    {
        $division = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
        $sport = $this->getSportMapper()->findById($division->getSport()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesTeam/entity.twig', [
            'sport'    => $sport,
            'division' => $division,
            'team'     => $entity
        ]));
    }

    /**
     * List of Teams and the Division they are in
     * @param Division $context
     */
    public function getList($context = null)
    {
        try {
            $sport    = $this->getSportMapper()->findById($context->getSport()->getId());
            $allTeams = $this->getTeamMapper()->findByColumn('division_id', $context->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesTeam/list.twig', [
                'sport'    => $sport,
                'division' => $context,
                'allTeams' => $allTeams
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Team in a particular Division id
     *
     * @param Team     $entity
     * @param Division $context
     * @return void
     */
    public function getForm($entity, $context = null)
    {
        try {
            if (empty($context)) {
                $context = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
            }

            $sport = $this->getSportMapper()->findById($context->getSport()->getId());

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesTeam/create.twig', [
                'sport'    => $sport,
                'division' => $context,
                'team'     => $entity,
                'action'   => $entity->getId() ? 'Modify' : 'Create'
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Modify a Team
     *
     * @param Team $entity
     */
    public function persistEntity($entity)
    {
        try {
            $divisionId = $this->getRequest()->post['divisionId'];
            $division   = $this->getDivisionMapper()->findById($divisionId);

            $this->saveTeam($division, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $entity);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesTeam.getList', [
                'contextId' => $divisionId
            ]));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Team data to db
     *
     * @param Division $division
     * @param string $name
     * @param string $url
     * @param Team $team
     */
    private function saveTeam(Division $division, $name, $url, Team $team)
    {
        $team->setName($name);
        $team->setUrl($url);
        $team->setDivision($division);
        $team->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getTeamMapper()->save($team);
    }

    /**
     * Get a list of Teams for a particular Division
     *
     * @param int $contextId
     */
    public function getTeamList($contextId)
    {
        $query = $this->getRequest()->query->get('query');

        /** @var TeamMapper $mapper */
        $mapper = $this->getMapper();
        $allTeams = $mapper->findByName($contextId, $query);

        $teams = [];

        /** @var Team $currentTeam */
        foreach ($allTeams as $currentTeam) {
            $teams[] = array_combine(['id','name'], $currentTeam->findValueLabelPair());
        }

        /* TODO: What's the best way to do this now?
         * Pretty sure we talked about it, but can't remember right now
         */
        header('Content-Type: application/json');
        echo json_encode($teams);
        exit();
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
        return $this->mapper;
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
