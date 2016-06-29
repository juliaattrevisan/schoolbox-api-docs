<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\SportsFixtures\Sport\Model as Sport;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Division\User\Model as DivisionUserModel;
use \Schoolbox\SportsFixtures\Division\User\Mapper as DivisionUserMapper;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

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
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var DivisionUserMapper
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
        $this->setDivisionUserMapper(DivisionUserMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
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
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
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
     * @param int $entityId
     */
    public function getLobby($entityId)
    {
        try {
            $division = $this->getDivisionMapper()->findById($entityId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        try {
            $teams = $this->getTeamMapper()->findByColumn('division_id', $entityId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $players  = $this->getDivisionUserMapper()->findByColumn('division_id', $entityId, ['order' => ['sequence' => 'desc']]);
        $zone     = new Zone("uploadZone");
        $zone->setMimeFilter('text/csv')->setAttachPrompt('upload a list of players');

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@sportsfixturesDivision/lobby.twig', [
                    'division'   => $division,
                    'sport'      => $sport,
                    'teams'      => $teams,
                    'players'    => $players,
                    'uploadZone' => $zone,
                    'user'       => $this->getUser(),
                ]
            )
        );
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
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }
    }

    /**
     * Add players to a Division
     *
     * @param Division $division
     */
    public function postAddPlayers(Division $division)
    {
        $divisionUsers = [];
        foreach ($this->getUserMapper()->findByColumn('id', $this->getRequest()->post->get('userId')) as $user) {
            $divisionUsers[] = $this->getDivisionUserMapper()->createModel()
                ->setDivision($division)
                ->setUser($user)
                ->setSequence(0);
        }
        $this->getDivisionUserMapper()->save($divisionUsers);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesDivision.getLobby',
                ['entityId' => $division->getId(),]
            )
        );
    }


    /**
     * Uploads players from a CSV into a Division
     *
     * @param Division $division
     */
    public function postUploadPlayers(Division $division)
    {
        $divisionUsers = [];

        // Do stuff with upload
        $factory = new CollectorFactory();
        $attach  = $this->getRequest()->post->get('attachment');
        if (is_array($attach)) {
            $upload = $factory->build($attach)->getFirst();
        } else {
            // error out
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        // What are the users in this upload?
        $reader = new Reader($upload->readStream());
        $reader->setLoadFromFirst(true);
        foreach ($reader as $row) {
            // Try ID, then username, then external ID
            $user = null;
            try {
                if (!empty($row['Schoolbox User ID'])) {
                    $user = $this->getUserMapper()->findById($row['Schoolbox User ID']);
                } elseif (!empty($row['Username'])) {
                    $user = $this->getUserMapper()->findOneByColumn('username', $row['Username']);
                } elseif (!empty($row['External ID'])) {
                    $user = $this->getUserMapper()->findByExternalId($row['External ID']);
                }
            } catch (MapperException $e) {
                // noop
            }
            if ($user instanceof UserModel) {
                $divisionUsers[] = $this->getDivisionUserMapper()->createModel()
                    ->setDivision($division)
                    ->setUser($user)
                    ->setSequence(0);
            }
        }

        // and save
        $this->getDivisionUserMapper()->save($divisionUsers);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesDivision.getLobby',
                ['entityId' => $division->getId(),]
            )
        );
    }

    /**
     * Remove players from a Division
     *
     * @param Division $division
     */
    public function postRemovePlayers(Division $division)
    {
        $this->getDivisionUserMapper()->delete(
            $this->getDivisionUserMapper()->findByColumnList([
                'user_id'     => $this->getRequest()->post->get('userId'),
                'division_id' => $division->getId(),
            ])
        );
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesDivision.getLobby',
                ['entityId' => $division->getId(),]
            )
        );
    }
    /**
     * Reorder players within a Division
     *
     * @param Division $division
     */
    public function postReorderPlayers(Division $division)
    {
        $userIds       = $this->getRequest()->post->get('userId');
        $divisionUsers = [];
        $sequenceNo    = count($userIds);
        foreach ($userIds as $userId) {
            try {
                /** @var DivisionUserModel $divisionUser */
                $divisionUser = $this->getDivisionUserMapper()->findOneByColumnList([
                    'user_id'     => $userId,
                    'division_id' => $division->getId(),
                ]);
                $divisionUsers[] = $divisionUser->setSequence($sequenceNo--);
            } catch (MapperException $e) {
                // User isn't in division. Ignore
                continue;
            }
        }
        $this->getDivisionUserMapper()->save($divisionUsers);
        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesDivision.getLobby',
                ['entityId' => $division->getId(),]
            )
        );
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
     * @return DivisionUserMapper
     */
    public function getDivisionUserMapper()
    {
        return $this->divisionUserMapper;
    }

    /**
     * @param DivisionUserMapper $divisionUserMapper
     * @return $this
     */
    public function setDivisionUserMapper(DivisionUserMapper $divisionUserMapper)
    {
        $this->divisionUserMapper = $divisionUserMapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }
}
