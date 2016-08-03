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
use \Schoolbox\SportsFixtures\Position\Model as PositionModel;
use \Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;
use \Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use \Schoolbox\SportsFixtures\Team\Model as TeamModel;
use \Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use \Schoolbox\SportsFixtures\Team\Position\Model as TeamPositionModel;
use \Schoolbox\SportsFixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;
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
     * @var PositionMapper
     */
    protected $positionMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var DivisionUserMapper
     */
    protected $divisionUserMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(DivisionMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setDivisionUserMapper(DivisionUserMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param DivisionModel $entity
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
     * @param DivisionModel $entity
     * @param Sport         $context
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
            // Load division
            $division = $this->getDivisionMapper()->findById($entityId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $division->setSport($this->getSportMapper()->findById($division->getSport()->getId()));
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        // Add players and teams into the division
        $division = $this->augmentDivisionWithPlayers($division);
        $division = $this->augmentDivisionWithTeams($division);

        // Create twig-friendly data model
        $dataModel = $this->buildDivisionTemplateDataModel($division);

        if ($this->getRequest()->isXhr()) {
            // Output as XHR data model
            $this->getResponse()->content->set(
                $this->buildJsonOutput($dataModel)
            );
        } else {
            // Create file upload zone
            $zone = new Zone("uploadZone");
            $zone->setMimeFilter('text/csv')->setAttachPrompt('upload a list of players');
            $dataModel['user']       = $this->getUser();
            $dataModel['uploadZone'] = $zone;
            $this->getResponse()->content->set(
                $this->buildHtmlOutput('@sportsfixturesDivision/lobby.twig', $dataModel)
            );
        }
    }

    /**
     * @param DivisionModel $entity
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
     * @param DivisionModel $division
     */
    public function postAddPlayers(DivisionModel $division)
    {
        $divisionUsers = [];
        foreach ($this->getUserMapper()->findByColumn('id', $this->getRequest()->post->get('userId')) as $user) {
            $divisionUsers[] = $this->getDivisionUserMapper()->createModel()
                ->setDivision($division)
                ->setUser($user)
                ->setSequence(0);
        }
        $this->renderPostAddPlayersResponse($division);
    }


    /**
     * Uploads players from a CSV into a Division
     *
     * @param DivisionModel $division
     */
    public function postUploadPlayers(DivisionModel $division)
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
        $this->renderPostAddPlayersResponse($division);
    }

    /**
     * Remove players from a Division
     *
     * @param DivisionModel $division
     */
    public function postRemovePlayers(DivisionModel $division)
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
     * @param DivisionModel $division
     */
    public function postReorderPlayers(DivisionModel $division)
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
     * Update player positions
     *
     * @param int $entityId
     */
    public function postPlayerPositions($entityId) {

        $teamId = $this->getRequest()->post['team_id'];
        try {
            // Load team
            $team = $this->getTeamMapper()->findById($teamId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $userId = $this->getRequest()->post['user_id'];
        try {
            // Load player
            $player = $this->getUserMapper()->findById($userId);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // Blow away all old positions for this player on the team

        $this->getTeamPositionMapper()->deleteByColumnList([
            'user_id' => $userId,
            'team_id' => $teamId
        ]);

        // Cache positions as we grab them

        $positionCache = [];

        // Iterate through all the position checkboxes that were ticked

        $positions = $this->getRequest()->post["positions"];

        if (is_array($positions)) {
            foreach ($positions as $position) {
                // Find the position

                if (!isset($positionCache[$position])) {
                    // Haven't got this position, load it and shove into cache
                    /** @var PositionModel $positionModel */
                    try {
                        $positionModel = $this->getPositionMapper()->findById($position);
                        $positionCache[$position] = $positionModel;
                    } catch (MapperException $e) {
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_POSITION_NOT_FOUND);
                    }
                } else {
                    // Already got this position
                    $positionModel = $positionCache[$position];
                }

                $teamPosition = new TeamPositionModel();
                $teamPosition->setTeam($team);
                $teamPosition->setUser($player);
                $teamPosition->setPosition($positionModel);
                $this->getTeamPositionMapper()->save($teamPosition);
            }
        }

        // Return to lobby

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesDivision.getLobby',
                ['entityId' => $entityId]
            )
        );
    }

    /**
     * Save Division data to db
     *
     * @param $sport
     * @param $name
     * @param $url
     * @param DivisionModel $division
     */
    private function saveDivision($sport, $name, $url, DivisionModel $division)
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
     * @return static
     */
    protected function setSportMapper(SportMapper $sportMapper)
    {
        $this->sportMapper = $sportMapper;
        return $this;
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
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return TeamMapper
     */
    protected function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param  TeamMapper $teamMapper
     * @return static
     */
    protected function setTeamMapper(TeamMapper $teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return PositionMapper
     */
    protected function getPositionMapper()
    {
        return $this->positionMapper;
    }

    /**
     * @param  PositionMapper $positionMapper
     * @return static
     */
    protected function setPositionMapper(PositionMapper $positionMapper)
    {
        $this->positionMapper = $positionMapper;
        return $this;
    }    

    /**
     * @return DivisionUserMapper
     */
    protected function getDivisionUserMapper()
    {
        return $this->divisionUserMapper;
    }

    /**
     * @param DivisionUserMapper $divisionUserMapper
     * @return static
     */
    protected function setDivisionUserMapper(DivisionUserMapper $divisionUserMapper)
    {
        $this->divisionUserMapper = $divisionUserMapper;
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

    /**
     * @return TeamPlayerMapper
     */
    protected function getTeamPlayerMapper()
    {
        return $this->teamPlayerMapper;
    }

    /**
     * @param TeamPlayerMapper $teamPlayerMapper
     * @return static
     */
    protected function setTeamPlayerMapper(TeamPlayerMapper $teamPlayerMapper)
    {
        $this->teamPlayerMapper = $teamPlayerMapper;
        return $this;
    }

    /**
     * @return TeamPositionMapper
     */
    protected function getTeamPositionMapper()
    {
        return $this->teamPositionMapper;
    }

    /**
     * @param TeamPositionMapper $teamPositionMapper
     * @return static
     */
    protected function setTeamPositionMapper(TeamPositionMapper $teamPositionMapper)
    {
        $this->teamPositionMapper = $teamPositionMapper;
        return $this;
    }

    /**
     * @param DivisionModel $division
     * @return DivisionModel
     */
    protected function augmentDivisionWithPlayers(DivisionModel $division)
    {
        // Load players into division
        // TODO add players into division model as a M2M join
        /** @var DivisionUserModel[] $players */
        $players = $this->getDivisionUserMapper()->findByColumn('division_id', $division->getId(), ['order' => ['sequence' => 'desc']]);
        foreach ($players as $player) {
            $division->getPlayers()->add($player->getUser());
        }
        return $division;
    }

    /**
     * @param DivisionModel $division
     * @return DivisionModel
     */
    protected function augmentDivisionWithTeams(DivisionModel $division)
    {
        // Load players into teams, and teams into division
        // TODO add players into team model as a M2M join
        try {
            $teams = $this->getTeamMapper()->findByColumn('division_id', $division->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $teamPlayers = $this->getTeamPlayerMapper()->findByColumn(
            'team_id',
            $teams->getColumnValues('id'),
            ['order' => ['team_id' => 'asc', 'sequence' => 'desc']]
        );
        foreach ($teamPlayers as $teamPlayer) {
            $teamPlayerTeamId = $teamPlayer->getTeam()->getId();
            foreach ($teams as $team) {
                $teamId = $team->getId();
                if ($teamPlayerTeamId == $teamId) {
                    $team->getPlayers()->add($teamPlayer->getUser());
                }
            }
        }
        $division->setTeams($teams);
        return $division;
    }

    /**
     * @param DivisionModel $division
     * @return array
     */
    protected function buildDivisionTemplateDataModel(DivisionModel $division)
    {
        $dataModel = [
            'division'  => $division,
            'players'   => [],
            'teams'     => [],
            'positions' => [],
        ];
        foreach ($division->getPlayers() as $player) {
            $playerDataModel = [
                'player' => $player,
                'teams'  => [],
            ];
            foreach ($division->getTeams() as $team) {
                if ($team->isPlayerInTeam($player)) {
                    $playerDataModel['teams'][] = $team;
                }
            }
            $dataModel['players'][] = $playerDataModel;
        }
        foreach ($division->getTeams() as $team) {
            $dataModel['teams'][] = $this->buildTeamTemplateDataModel($team);
        }

        $dataModel['positions'] = $this->getPositionMapper()->findByColumn('sport_id', $division->getSport()->getId());
        return $dataModel;
    }

    /**
     * @param TeamModel $team
     * @return array
     */
    protected function buildTeamTemplateDataModel(TeamModel $team)
    {
        $positions = $this->getTeamPositionMapper()->findByColumn('team_id', $team->getId());

        $playerPosition = [];

        /* Organise positions by player id
         */
        foreach ($positions as $currentPosition) {
            $playerPosition[$currentPosition->getUser()->getId()][] = $currentPosition;
        }

        return [
            'team'      => $team,
            'players'   => $team->getPlayers(),
            'positions' => $playerPosition,
        ];
    }

    /**
     * @param DivisionModel $division
     */
    protected function renderPostAddPlayersResponse(DivisionModel $division)
    {
        if ($this->getRequest()->isXhr()) {
            // Output a XHR data model + HTML output
            $division = $this->augmentDivisionWithPlayers($division);
            $division = $this->augmentDivisionWithTeams($division);
            $dataModel = $this->buildDivisionTemplateDataModel($division);
            $dataModel['html'] = $this->buildUnthemedHtmlOutput('@sportsfixturesDivision/lobby/division.twig', $dataModel)->getAsString();
            $this->getResponse()->content->set(
                $this->buildJsonOutput($dataModel)
            );
        } else {
            // Redirect back to lobby
            $this->getResponse()->redirect->afterPost(
                $this->getRouter()->generate(
                    'sportsfixturesDivision.getLobby',
                    ['entityId' => $division->getId(),]
                )
            );
        }
    }
}
