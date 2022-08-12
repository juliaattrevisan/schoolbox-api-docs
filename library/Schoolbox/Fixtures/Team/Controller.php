<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team;

use Alaress\Web\Controller\Exception;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Coach;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Model as TeamUser;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Position\Mapper as TeamPositionMapper;
use Schoolbox\Fixtures\Division\Helper\Collector;
use Schoolbox\Fixtures\Division\Helper\Translator;
use Schoolbox\UserMapper;
use Schoolbox\TagCloud\Mapper as TagCloudMapper;
use Schoolbox\Application\Exception as ApplicationException;

/**
 * @method TeamMapper getMapper()
 */
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
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamPositionMapper
     */
    protected $teamPositionMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

    /**
     * @var Coach\TagCloud
     */
    protected $coachCloud;

    /**
     * @var Helper\Translator
     */
    protected $teamTranslator;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(TeamMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setTeamPlayerMapper(TeamPlayerMapper::getInstance());
        $this->setTeamPositionMapper(TeamPositionMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setSchoolTagCloudMapper(TagCloudMapper::getInstanceByName('sportsfixtures_school', 'id', 'name'));
        $this->setCoachCloud(new Coach\TagCloud(Coach\Mapper::getInstance(), $this->getUserMapper()));
        $this->setTeamTranslator(new Helper\Translator());
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

        // Load coaches into entity
        $entity->setCoaches($this->getCoachCloud()->findForTeam($entity));

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesTeam/entity.twig', [
            'sport'    => $sport,
            'division' => $division,
            'team'     => $entity
        ]));
    }

    /**
     * Generate a list of Teams and the Division they are in
     *
     * @param null|Division $context
     * @throws ApplicationException\Exception If no sport found in DB
     */
    public function getList($context = null)
    {
        // ajax a list of Teams for a particular Division
        if (strpos($this->getRequest()->headers->get('accept'), 'application/json') !== false) {
            $this->getJsonList($context);
        } else {
            $this->getHtmlList($context);
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
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
            }
        }

        // Load coaches into entity
        $entity->setCoaches($this->getCoachCloud()->findForTeam($entity));

        try {
            $sport = $this->getSportMapper()->findById($context->getSport()->getId());
        } catch (MapperException $e) {
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
            $divisionId = $this->request->post->get('divisionId');
            $division   = $this->getDivisionMapper()->findById($divisionId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        $this->saveTeam(
            $division,
            $this->request->post->get('name'),
            $this->request->post->get('url'),
            $this->request->post->get('coach', []),
            $entity
        );

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesDivision.getLobby', [
            'entityId' => $divisionId
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
            $teamUsers[] = $this->getTeamPlayerMapper()->createModel()
                ->setTeam($team)
                ->setUser($user)
                ->setSequence(0);
        }
        $this->getTeamPlayerMapper()->save($teamUsers);
        $this->renderPostAddPlayersResponse($team);
    }

    /**
     * Remove players from a Team
     *
     * @param Team $team
     */
    public function postRemovePlayers(Team $team)
    {
        $this->getTeamPlayerMapper()->deleteByColumnList([
            'user_id' => $this->getRequest()->post->get('userId'),
            'team_id' => $team->getId(),
        ]);
        $this->getTeamPositionMapper()->deleteByColumnList([
            'user_id' => $this->getRequest()->post->get('userId'),
            'team_id' => $team->getId(),
        ]);
        $this->renderPostAddPlayersResponse($team);
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
        $sequenceNo = 1;
        foreach ($userIds as $userId) {
            try {
                /** @var TeamUser $teamUser */
                $teamUser = $this->getTeamPlayerMapper()->findOneByColumnList([
                    'user_id' => $userId,
                    'team_id' => $team->getId(),
                ]);
                $teamUsers[] = $teamUser->setSequence($sequenceNo++);
            } catch (MapperException $e) {
                // User isn't in team. Ignore
                continue;
            }
        }
        $this->getTeamPlayerMapper()->save($teamUsers);
        $this->renderPostAddPlayersResponse($team);
    }

    /**
     * Save Team data to db
     *
     * @param Division $division
     * @param string $name
     * @param string $url
     * @param array $coaches
     * @param Team $team
     */
    private function saveTeam(Division $division, $name, $url, $coaches, Team $team)
    {
        $team->setName($name);
        $team->setUrl($url);
        $team->setDivision($division);
        $team->setUpdatedAt(\Alaress_Date_Format::now());

        // Save each coach in the tag cloud too, and add to the team.
        $team->setCoaches($this->getCoachCloud()->saveTagCloud($coaches));
        $this->getMapper()->save($team);
    }

    /**
     * Outputs the response from an add player method.
     *
     * @param Team $team
     */
    protected function renderPostAddPlayersResponse(Team $team)
    {
        if ($this->getRequest()->isXhr()) {
            $this->getTeamTranslator()->collectPlayers($team);

            // Output a XHR data model + HTML output
            $dataModel = $this->getTeamTranslator()->translateTeam($team);
            $dataModel['html'] = $this->buildUnthemedHtmlOutput('@sportsfixturesDivision/lobby/teamPlayerList.twig', $dataModel)->getAsString();


            /* this is stupid
             */
            $divisionCollector = new Collector();
            $divisionTranslator = new Translator();

            $divisionCollector->collectPlayers($team->getDivision());
            $divisionCollector->collectTeams($team->getDivision());
            $divisionLobby = $divisionTranslator->translateDivision($team->getDivision());
            $dataModel['divisionLobby'] = $this->buildUnthemedHtmlOutput('@sportsfixturesDivision/lobby/division.twig', $divisionLobby)->getAsString();


            $this->getResponse()->content->set(
                $this->buildJsonOutput($dataModel)
            );
        } else {
            // Redirect back to lobby
            $this->getResponse()->redirect->afterPost(
                $this->getRouter()->generate(
                    'sportsfixturesDivision.getLobby',
                    ['entityId' => $team->getDivision()->getId()]
                )
            );
        }
    }


    /**
     * Return a JSON formatted list of teams
     *
     * @param null|Division $context
     */
    private function getJsonList(Division $context = null)
    {
        $query = trim($this->getRequest()->query->get('query'));

        $teams = [];
        if ($context === null) {
            /**
             * searching for an arbitrary keyword match
             * across teams / divisions / sports via a custom query
             * will not create a Team instance
             */
            if ('' === $query) {
                //empty query exit early
                $problem = new ApiProblem('Empty Search');
                $problem->setStatus(400);
                $this->getResponse()->status->setCode(400);
                return $this->getResponse()->content->set($this->buildJsonOutput($problem->asArray()));
            }
            $database = $this->getMapper()->getDatabase();
            $queryLike = "%{$database->getQuoter()->escapeForLike($query)}%";
            //build query to check all possible options Team, sport, division
            $query = "
            SELECT st.id as teamID, ss.name AS sport, sd.name AS division, st.name AS team
            FROM sportsfixtures_division as sd
            JOIN sportsfixtures_sport ss on ss.id = sd.sport_id
            JOIN sportsfixtures_team st on sd.id = st.division_id
            WHERE st.name like :search
                OR ss.name like :search
                OR sd.name like :search;
            ";
            $ex = $database->prepare($query);
            $ex->execute(['search' => $queryLike]);
            $allTeams = $ex->fetchAll();

            //build the $teams array
            /** @var array|Team $currentTeam */
            foreach ($allTeams as $currentTeam) {
                $teams[] = [
                    'id' => $currentTeam['teamID'],
                    'name' => "{$currentTeam['team']} ({$currentTeam['sport']} / {$currentTeam['division']})"
                ];
            }
        } else {
            /**
             * Standard query for finding teams by division id
             */
            $allTeams = $this->getMapper()->findByName($context->getId(), $query);

            //build the $teams array
            /** @var array|Team $currentTeam */
            foreach ($allTeams as $currentTeam) {
                $teams[] = [
                    'id' => $currentTeam->getId(),
                    'name' => "{$currentTeam->getName()} ({$currentTeam->getDivision()->getSport()->getName()} / {$currentTeam->getDivision()->getName()})"
                ];
            }
        }
        /**
         * Both methods above, $teams is a multidimensional array
         * just continue
         */

        // If the 'schools' parameter is set we also want to add schools from the tag cloud

        if ((int)$this->getRequest()->query->get('schools') === 1) {
            // Add schools from the tag cloud
            $schools = $this->getSchoolTagCloudMapper()->findForTag($this->getUser(), $query);

            foreach ($schools as $school) {
                // Prefix their ID with the external school token, we will parse it later when saving a match
                $school['id'] = TagCloudMapper::SCHOOL_PREFIX . $school['id'];
                $teams[]      = $school;
            }

            // Sort return array by name
            usort($teams, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        $this->getResponse()->content->set($this->buildJsonOutput($teams));
    }


    /**
     * Renders a HTML list of teams
     *
     * @param Division $context Intentionally does not permit null
     * @throws ApplicationException\Exception If no sport found in DB
     */
    private function getHtmlList(Division $context)
    {
        try {
            $sport = $this->getSportMapper()->findById($context->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $allTeams = $this->getMapper()->findByColumn(
            'division_id',
            $context->getId(),
            [
                'order' => [
                    'name' => 'asc',
                ],
            ]
        );

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@sportsfixturesTeam/list.twig',
                [
                    'sport'    => $sport,
                    'division' => $context,
                    'allTeams' => $allTeams,
                ]
            )
        );
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

    /**
     * @return Coach\TagCloud
     */
    public function getCoachCloud()
    {
        return $this->coachCloud;
    }

    /**
     * @param Coach\TagCloud $coachCloud
     * @return static
     */
    public function setCoachCloud($coachCloud)
    {
        $this->coachCloud = $coachCloud;
        return $this;
    }

    /**
     * @return Helper\Translator
     */
    public function getTeamTranslator()
    {
        return $this->teamTranslator;
    }

    /**
     * @param Helper\Translator $teamTranslator
     * @return static
     */
    public function setTeamTranslator($teamTranslator)
    {
        $this->teamTranslator = $teamTranslator;
        return $this;
    }
}
