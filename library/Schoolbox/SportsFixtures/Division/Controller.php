<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Sport\Model as Sport;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;

class Controller extends AbstractController
{

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var User\Mapper
     */
    protected $divisionUserMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(DivisionMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setDivisionUserMapper(User\Mapper::getInstance());
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param Division $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity)
    {
        $sport = $this->getSportMapper()->findById($entity->getSport()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesDivision/entity.twig', [
            'sport'    => $sport,
            'division' => $entity
        ]));
    }

    /**
     * List of Divisions and the Sport they are in
     *
     * @param Sport $context
     */
    public function getList($context = null)
    {
        try {
            $allDivisions = $this->getDivisionMapper()->findByColumn('sport_id', $context->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesDivision/list.twig', [
                'sport'        => $context,
                'allDivisions' => $allDivisions
            ]));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * @param Division $entity
     * @param Sport    $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            $context = $this->getSportMapper()->findById($entity->getSport()->getId());
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesDivision/create.twig', [
            'division' => $entity,
            'sport'    => $context,
            'action'   => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Show the player lobby for this division
     *
     * @param int $divisionId
     */
    public function getLobby($divisionId)
    {
        try {
            $division = $this->getDivisionMapper()->findById($divisionId);
            $sport    = $this->getSportMapper()->findById($division->getSport()->getId());
            $teams    = $this->getTeamMapper()->findByColumn('division_id', $divisionId);
            $players  = $this->getDivisionUserMapper()->findByColumn('division_id', $divisionId, ['order' => ['sequence' => 'desc']]);

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    '@sportsfixturesDivision/lobby.twig', [
                        'division' => $division,
                        'sport'    => $sport,
                        'teams'    => $teams,
                        'players'  => $players,
                    ]
                )
            );
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * @param Division $entity
     * @return void
     */
    protected function persistEntity($entity)
    {
        try {
            $sport = $this->getSportMapper()->findById($this->getRequest()->post['sportId']);

            $this->saveDivision($sport, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $entity);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesDivision.getList', [
                'contextId' => $sport->getId()
            ]));

        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Division data to db
     *
     * @param $sport
     * @param $name
     * @param $url
     * @param Division $division
     */
    private function saveDivision($sport, $name, $url, Division $division)
    {
        $division->setName($name);
        $division->setUrl($url);
        $division->setSport($sport);
        $division->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getDivisionMapper()->save($division);
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
     * @return User\Mapper
     */
    public function getDivisionUserMapper()
    {
        return $this->divisionUserMapper;
    }

    /**
     * @param User\Mapper $divisionUserMapper
     * @return $this
     */
    public function setDivisionUserMapper($divisionUserMapper)
    {
        $this->divisionUserMapper = $divisionUserMapper;
        return $this;
    }
}
