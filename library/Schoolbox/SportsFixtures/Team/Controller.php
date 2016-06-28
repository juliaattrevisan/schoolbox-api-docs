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
use \Schoolbox\SportsFixtures\Team\User\Model as TeamUserModel;
use \Schoolbox\SportsFixtures\Team\User\Mapper as TeamUserMapper;
use Schoolbox\UserMapper;

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
     * @var TeamUserMapper
     */
    protected $teamUserMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(TeamMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setTeamUserMapper(TeamUserMapper::getInstance());
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
        /* ajax a list of Teams for a particular Division
         */
        if ($this->getRequest()->isXhr()) {
            $query = $this->getRequest()->query->get('query');

            /** @var TeamMapper $mapper */
            $mapper   = $this->getMapper();
            $allTeams = $mapper->findByName($context->getId(), $query);

            $teams = [];

            /** @var Team $currentTeam */
            foreach ($allTeams as $currentTeam) {
                $teams[] = array_combine(['id', 'name'], $currentTeam->findValueLabelPair());
            }

            $this->getResponse()->content->set($this->buildJsonOutput($teams));
        }
        else {
            try {
                $sport = $this->getSportMapper()->findById($context->getSport()->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
            }

            $allTeams = $this->getTeamMapper()->findByColumn(
                'division_id',
                $context->getId(),
                [
                    'order' => [
                        'name' => 'asc'
                    ]
                ]
            );

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    '@sportsfixturesTeam/list.twig',
                    [
                        'sport'    => $sport,
                        'division' => $context,
                        'allTeams' => $allTeams
                    ]
                )
            );
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

        if (empty($context)) {
            try {
                $context = $this->getDivisionMapper()->findById($entity->getDivision()->getId());
            }
            catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
            }
        }

        try {
            $sport = $this->getSportMapper()->findById($context->getSport()->getId());
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesTeam/create.twig', [
            'sport'    => $sport,
            'division' => $context,
            'team'     => $entity,
            'action'   => $entity->getId() ? 'Modify' : 'Create'
        ]));
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
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        $this->saveTeam($division, $this->getRequest()->post['name'], $this->getRequest()->post['url'], $entity);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesTeam.getList', [
            'contextId' => $divisionId
        ]));
    }

    /**
     * Add players to a Team
     *
     * @param Team $team
     */
    public function postAddPlayers(Team $team)
    {
        $teamUsers = [];
        foreach ($this->getUserMapper()->findByColumn('id', $this->getRequest()->post->get('userId')) as $user) {
            $teamUsers[] = $this->getTeamUserMapper()->createModel()
                ->setTeam($team)
                ->setUser($user)
                ->setSequence(0);
        }
        $this->getTeamUserMapper()->save($teamUsers);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesTeam.getList',
                ['contextId' => $team->getDivision()->getId()]
            )
        );
    }

    /**
     * Remove players from a Team
     *
     * @param Team $team
     */
    public function postRemovePlayers(Team $team)
    {
        $this->getTeamUserMapper()->delete(
            $this->getTeamUserMapper()->findByColumnList([
                'user_id' => $this->getRequest()->post->get('userId'),
                'team_id' => $team->getId(),
            ])
        );
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesTeam.getList',
                ['contextId' => $team->getDivision()->getId()]
            )
        );
    }

    /**
     * Reorder players within a Team
     *
     * @param Team $team
     */
    public function postReorderPlayers(Team $team)
    {
        $userIds    = $this->getRequest()->post->get('userId');
        $teamUsers  = [];
        $sequenceNo = count($userIds);
        foreach ($userIds as $userId) {
            try {
                /** @var TeamUserModel $teamUser */
                $teamUser = $this->getTeamUserMapper()->findOneByColumnList([
                    'user_id' => $userId,
                    'team_id' => $team->getId(),
                ]);
                $teamUsers[] = $teamUser->setSequence($sequenceNo--);
            } catch (MapperException $e) {
                // User isn't in team. Ignore
                continue;
            }
        }
        $this->getTeamUserMapper()->save($teamUsers);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesTeam.getList',
                ['contextId' => $team->getDivision()->getId()]
            )
        );
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

    /**
     * @return TeamUserMapper
     */
    protected function getTeamUserMapper()
    {
        return $this->teamUserMapper;
    }

    /**
     * @param TeamUserMapper $teamUserMapper
     * @return static
     */
    protected function setTeamUserMapper(TeamUserMapper $teamUserMapper)
    {
        $this->teamUserMapper = $teamUserMapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }
}
