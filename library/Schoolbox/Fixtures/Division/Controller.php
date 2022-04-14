<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Division;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Fixtures\Season\Mapper as SeasonMapper;
use Schoolbox\Fixtures\Position\Model as Position;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Position\Model as TeamPosition;
use Schoolbox\Fixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Division\User\Model as DivisionUser;
use Schoolbox\Fixtures\Division\User\Mapper as DivisionUserMapper;
use Schoolbox\User as User;
use Schoolbox\UserMapper;

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
     * @var Helper\Collector
     */
    protected $divisionCollector;

    /**
     * @var Helper\Translator
     */
    protected $divisionTranslator;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(DivisionMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setSeasonMapper(SeasonMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->divisionUserMapper = DivisionUserMapper::getInstance();
        $this->setUserMapper(UserMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setDivisionCollector(new Helper\Collector());
        $this->setDivisionTranslator(new Helper\Translator());
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
            $allDivisions = $this->getMapper()->findByColumn('sport_id', $context->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesDivision/list.twig', [
                'sport'        => $context,
                'allDivisions' => $allDivisions
            ]));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }
    }

    /**
     * @param Division $entity
     * @param Sport         $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            $context = $this->getSportMapper()->findById($entity->getSport()->getId());
        }
        $entity->setSport($context);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesDivision/create.twig', [
            'division' => $entity,
            'sport'    => $context,
            'action'   => $entity->getId() ? 'Modify' : 'Create',
            'referer'  => base64_encode($this->getRequest()->headers->get('Referer'))
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
            /** @var Division $division */
            $division = $this->getMapper()->findById($entityId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }


        // Add players and teams into the division
        $division = $this->getDivisionCollector()->collectPlayers($division);
        $division = $this->getDivisionCollector()->collectTeams($division);

        // Create twig-friendly data model
        $dataModel = $this->getDivisionTranslator()->translateDivision($division);
        $dataModel['positions'] = $this->getPositionMapper()->findByColumn('sport_id', $division->getSport()->getId());

        if ($this->getRequest()->isXhr()) {
            // Output as XHR data model
            $this->getResponse()->content->set(
                $this->buildJsonOutput($dataModel)
            );
        } else {
            // Create file upload zone
            $zone = new Zone("uploadZone");
            $zone->setAttachPrompt('upload a list of players');
            $dataModel['user']       = $this->getUser();
            $dataModel['uploadZone'] = $zone;
            $this->getResponse()->content->set(
                $this->buildHtmlOutput('@sportsfixturesDivision/lobby.twig', $dataModel)
            );
        }
    }

    /**
     * @param Division $entity
     * @return void
     */
    protected function persistEntity($entity)
    {
        try {
            $sport  = $this->getSportMapper()->findById($this->request->post->get('sportId'));
            $season = $this->getSeasonMapper()->findById($this->request->post->get('seasonId'));

            $this->saveDivision($sport, $season, $this->request->post->get('name'), $this->request->post->get('url'), $entity);

            $return = base64_decode($this->request->post->get('returnUrl'));

            if (empty($return)) {
                $return = $this->getRouter()->generate('sportsfixturesSport.getEntity', [
                    'entityId' => $sport->getId()
                ]);
            }

            $this->getResponse()->redirect->afterPost($return);
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
        $involvements = $this->divisionUserMapper
            ->findByColumn('division_id', $division->getId())
            ->indexByCallback(
                static function (DivisionUser $divisionUser) {
                    return $divisionUser->getUser()->getId();
                }
            )->keys();

        /* Find the people who were chosen but aren't already in this division somewhere
         * This is the diff of who you chose vs who's already here
         */
        $uninvolvedUsers = array_diff($this->getRequest()->post->get('userId'), $involvements);

        /* Hydrate only the guys you need
         */
        $users = $this->getUserMapper()->findByColumn('id', $uninvolvedUsers);

        foreach ($users as $user) {
            // Don't re-add any players already in the division
            try {
                $this->divisionUserMapper->save(
                    $this->divisionUserMapper->createModel()
                         ->setDivision($division)
                         ->setUser($user)
                         ->setSequence(0)
                );
            } catch (MapperException $e) {
                // Probably a duplicate key. That is fine
            }
        }
        $this->renderPostAddPlayersResponse($division);
    }


    /**
     * Uploads players from a CSV into a Division
     *
     * @param Division $division
     */
    public function postUploadPlayers(Division $division)
    {
        $divisionUsers = [];

        //get rid of newlines
        $string = preg_replace('/(\r\n|\n|\r)/', ',', $_POST['bulkAdd']);

        //split the list into an array
        $list = preg_split('/[,; ]/', $string);

        // What are the users in this upload?
        foreach ($list as $key) {
            $key = trim($key);

            $user = null;

            // Try external ID, then username

            if (!empty($key)) {
                try {
                    $user = $this->getUserMapper()->findByExternalId($key);
                } catch (MapperException $e) {
                    try {
                        $user = $this->getUserMapper()->findOneByColumn('username', $key);
                    } catch (MapperException $e) {
                        // noop
                    }
                }
            }

            if ($user instanceof User) {
                try {
                    $this->divisionUserMapper->save(
                        $this->divisionUserMapper->createModel()
                                                 ->setDivision($division)
                                                 ->setUser($user)
                                                 ->setSequence(0)
                    );
                } catch (MapperException $e) {
                    // Probably a duplicate key. That is fine
                }
            }
        }
        $this->renderPostAddPlayersResponse($division);
    }

    /**
     * Remove players from a Division
     *
     * @param Division $division
     */
    public function postRemovePlayers(Division $division)
    {
        $this->divisionUserMapper->delete(
            $this->divisionUserMapper->findByColumnList([
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
                /** @var DivisionUser $divisionUser */
                $divisionUser = $this->divisionUserMapper->findOneByColumnList([
                    'user_id'     => $userId,
                    'division_id' => $division->getId(),
                ]);
                $divisionUsers[] = $divisionUser->setSequence($sequenceNo--);
            } catch (MapperException $e) {
                // User isn't in division. Ignore
                continue;
            }
        }
        $this->divisionUserMapper->save($divisionUsers);
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
    public function postPlayerPositions($entityId)
    {

        $teamId = $this->request->post->get('team_id');
        try {
            // Load team
            $team = $this->getTeamMapper()->findById($teamId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $userId = $this->request->post->get('user_id');
        try {
            // Load player
            $player = $this->getUserMapper()->findById($userId);
        } catch (MapperException $e) {
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

        $positions = $this->request->post->get("positions");

        if (is_array($positions)) {
            foreach ($positions as $position) {
                // Find the position

                if (!isset($positionCache[$position])) {
                    // Haven't got this position, load it and shove into cache
                    /** @var Position $positionModel */
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

                $teamPosition = new TeamPosition();
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
     * @param Sport $sport
     * @param Season $season
     * @param string $name
     * @param string $url
     * @param Division $division
     */
    private function saveDivision(Sport $sport, Season $season, $name, $url, Division $division)
    {
        $division
            ->setSport($sport)
            ->setSeason($season)
            ->setName($name)
            ->setUrl($url)
            ->setUpdatedAt(\Alaress_Date_Format::now());

        $this->getMapper()->save($division);
    }

    /**
     * Outputs the response from an add player method.
     *
     * @param Division $division
     */
    protected function renderPostAddPlayersResponse(Division $division)
    {
        if ($this->getRequest()->isXhr()) {
            // Output a XHR data model + HTML output
            $division = $this->getDivisionCollector()->collectPlayers($division);
            $division = $this->getDivisionCollector()->collectTeams($division);
            $dataModel = $this->getDivisionTranslator()->translateDivision($division);

            $dataModel['positions'] = $this->getPositionMapper()->findByColumn('sport_id', $division->getSport()->getId());
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
    protected function setSeasonMapper(SeasonMapper $seasonMapper)
    {
        $this->seasonMapper = $seasonMapper;
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
     * @return Helper\Collector
     */
    public function getDivisionCollector()
    {
        return $this->divisionCollector;
    }

    /**
     * @param Helper\Collector $divisionCollector
     * @return static
     */
    public function setDivisionCollector($divisionCollector)
    {
        $this->divisionCollector = $divisionCollector;
        return $this;
    }

    /**
     * @return Helper\Translator
     */
    public function getDivisionTranslator()
    {
        return $this->divisionTranslator;
    }

    /**
     * @param Helper\Translator $divisionTranslator
     * @return static
     */
    public function setDivisionTranslator($divisionTranslator)
    {
        $this->divisionTranslator = $divisionTranslator;
        return $this;
    }
}
