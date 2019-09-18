<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Fixtures\Result;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\User;
use Schoolbox\Collection;
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Permission\Access\UserAccessMapper;
use \Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use \Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\Fixtures\Match\Model as Match;
use \Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use \Schoolbox\Fixtures\Match\Weather as MatchWeather;
use \Schoolbox\Fixtures\Result\Model as Result;
use \Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Status\Mapper as StatusMapper;
use Schoolbox\Fixtures\Status\Model as Status;
use \Schoolbox\Fixtures\Team\Model as Team;
use \Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use \Schoolbox\Fixtures\Team\Metadata\Factory as TeamMetadataFactory;
use \Schoolbox\TagCloud\Mapper as TagCloudMapper;
use Schoolbox\Weather\ApixuClient;


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
     * @var MatchMapper
     */
    protected $matchMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var TagCloudMapper
     */
    protected $schoolTagCloudMapper;

    /**
     * @var StatusMapper
     */
    protected $statusMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(ResultMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setMatchMapper(MatchMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setSchoolTagCloudMapper(TagCloudMapper::getInstanceByName('sportsfixtures_school', 'id', 'name'));
        $this->setStatusMapper(StatusMapper::getInstance());
    }

    /**
     * Populate the Response object with a form that provides a means to mutate the given (new) entity.
     * Only text/html is Accept-able.
     *
     * @param object $entity
     * @param object $team
     * @param object $context
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @return void
     */
    public function getCreateFormForTeam($entity, $team, $context = null)
    {
        if (empty($context)) {
            try {
                $context = $this->getMatchMapper()->findById($entity->getMatch()->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
            }
        }

        try {
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        /* If results already exist, modify it instead
         */
        try {
            $entity = $this->getMapper()->findOneByColumnList(
                [
                    'match_id' => $context->getId(),
                    'team_id'  => $team->getId()
                ]
            );
        } catch (MapperException $e) {
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@sportsfixturesResult/create.twig',
                [
                    'sport'    => $sport,
                    'statuses' => $this->getStatusMapper()->findAll(),
                    'division' => $division,
                    'match'    => $context,
                    'team'     => $team,
                    'result'   => $entity,
                    'action'   => $entity->getId() ? 'Modify' : 'Create'
                ]
            )
        );
    }

    /**
     * @inheritdoc
     *
     * @param $entity
     * @throws \Exception
     */
    public function createResultForTeam($entity)
    {
        $this->persistEntity($entity);
    }

    /**
     * List of Results and the Match they are in
     *
     * @param Match $context
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getList($context = null)
    {
        try {
            /** @var Match $context */
            $division = $this->getDivisionMapper()->findById($context->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        try {
            $allResults = $this->getMapper()->findByColumn('match_id', $context->getId());

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    '@sportsfixturesResult/list.twig',
                    [
                        'sport'      => $sport,
                        'division'   => $division,
                        'match'      => $context,
                        'allResults' => $allResults
                    ]
                )
            );
        } catch (MapperException $e) {

            /* No Results for this Match?
             * Prompt to spawn one
             */
            $this->getResponse()->redirect->to(
                $this->getRouter()->generate(
                    'sportsfixturesResult.getCreateForm',
                    [
                        'contextId' => $context->getId()
                    ]
                )
            );
        }
    }

    /**
     * View all Results (from both Teams) on the Match page
     * Probably used if you are a Sports Admin and you want
     * to give Results to both Teams without having to mash
     * your way through the UI; can do it quickly from here
     *
     * @param Result $entity
     */
    public function getEntity($entity)
    {
        /* noop
         */
    }

    /**
     * Show Results for a particular Team from that Match
     * The same as getEntity() except that it only shows
     * the Results for the specified Team
     *
     * @api /fixtures/matches/:matchId/results/:teamId
     * @param Match $context
     * @param Team $team
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getResultForTeam($context, $team)
    {
        try {
            /** @var Match $context */
            $match = $this->getMatchMapper()->findById($context->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            $division = $this->getDivisionMapper()->findById($match->getDivision()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_DIVISION_NOT_FOUND);
        }

        try {
            $sport = $this->getSportMapper()->findById($division->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        /* Only spawn Results for Teams that were in this Match
         */
        if (!$context->hasTeam($team)) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        /* Team Lists
         * Get home team player list, if applicable
         * Get away team player list, if applicable
         */
        $factory = new TeamMetadataFactory($context);
        $teamMetadata = $factory->produce();

        /* Find faceless home team, ram it into a Team instance
         * This saves endless shenanigans in front-end land
         */
        if (!$match->getHomeTeam()) {

            if($match->getHomeSchool()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($match->getHomeSchool()->getName());

                $match->setHomeTeam($facelessTeam);
            }
            else {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
            }
        }

        /* Find faceless away team, ram it into a Team instance
         * This saves endless shenanigans in front-end land
         */
        if (!$match->getAwayTeam()) {

            if($match->getAwaySchool()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($match->getAwaySchool()->getName());

                $match->setAwayTeam($facelessTeam);
            }
            else {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
            }
        }

        /* Tell me the weather
         */
        /** @var Match $match */
        $apiKey = $this->getConfigLoader()->get('weather_api_key');
        $apiUrl = $this->getConfigLoader()->get('weather_api_url');
        $apixuClient = new ApixuClient($apiKey, $apiUrl);
        $forecast = new MatchWeather($match, $apixuClient);
        $forecast->loadForecast();

        /* Determine whether you've got write access or not
         * when viewing this page
         */
        $hasMatchModify = $this->hasMatchModify($teamMetadata->getCoaches()->getOffset($team->getId()));

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@sportsfixturesResult/team.twig',
                [
                    'sport'          => $sport,
                    'division'       => $division,
                    'match'          => $match,
                    'schoolMapper'   => $this->getSchoolTagCloudMapper(),
                    'team'           => $team,
                    'teamMetadata'   => $teamMetadata,
                    'result'         => $context,
                    'googleApiKey'   => $this->getConfigLoader()->get('googleapps_api_key'),
                    'forecast'       => $forecast,
                    'hasMatchModify' => $hasMatchModify
                ]
            )
        );
    }

    /**
     * Determine whether the logged-in user has write access
     * to this Match page
     *
     * @param Collection $allCoaches
     * @return bool
     * @throws MapperException
     */
    private function hasMatchModify(Collection $allCoaches): bool
    {
        /** @var User $sessionUser */
        $sessionUser = $_SESSION['user'];

        /* Skip all other checks if you're superuser
         */
        if ($sessionUser->isSuperuser()) {
            return true;
        }

        /* Otherwise continue checking against service assignations
         * If you're a Sports Admin, also return true
         */
        $allUserAccess = UserAccessMapper::getInstance()->findUsersForService(
            UserAccess::SERVICE_SPORTS_ADMINISTRATOR,
            0,
            UserAccess::ACCESS_WRITE
        );

        foreach ($allUserAccess as $currentUserAccess) {
            if ($currentUserAccess->getId() == $sessionUser->getId()) {
                return true;
            }
        }

        /* Coaches of this Team can add results
         */
        foreach ($allCoaches as $currentCoach) {
            if ($currentCoach->getUser()->getId() == $sessionUser->getId()) {
                return true;
            }
        }

        /* Nobody else can write/modify anything on the Match Result page
         */
        return false;
    }

    /**
     * Display form for create/update Results
     *
     * @param Result $entity
     * @param Match $context
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getForm($entity, $context = null)
    {
        // For clarity
        $result = $entity;
        $match  = $context;

        // If updating existing results
        if ($result->getId()) {
            $match = MatchMapper::getInstance()->findById($result->getMatch()->getId());
            $result->setMatch($match);

            // Don't permit update of archived results
            if ($match->getSeason()->isArchived()) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
            }
        }

        // Don't permit creation of results for archived matches
        if (!$result->getId() && $match->getSeason()->isArchived()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
        }

        try {
            $team = $this->getTeamMapper()->findById($entity->getTeam()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $this->getCreateFormForTeam($result, $team, $match);
    }

    /**
     * Persist method for entity
     *
     * @param Result $entity
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    protected function persistEntity($entity)
    {
        try {
            /** @var Match $match */
            $matchId = $this->getRequest()->post['matchId'];
            $match   = $this->getMatchMapper()->findById($matchId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            /** @var Team $team */
            $teamId = $this->getRequest()->post['teamId'];
            $team   = $this->getTeamMapper()->findById($teamId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        // Save status (optional)
        /** @var Status $status */
        $statusId = $this->getRequest()->post->get('statusId');
        if ($statusId) {
            try {
                $status = $this->getStatusMapper()->findById($statusId);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_STATUS_NOT_FOUND);
            }
        } else {
            $status = $this->getStatusMapper()->createModel();
        }

        $this->saveResult($match, $team, $status, $this->getRequest()->post['description'], $entity);

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'sportsfixturesResult.getResultForTeam',
                [
                    'contextId' => $matchId,
                    'teamId'    => $team->getId()
                ]
            )
        );
    }

    /**
     * Save Result data to db
     *
     * @param Match $match
     * @param Team $team
     * @param Status $status
     * @param string $description
     * @param Result $result
     * @throws MapperException
     */
    private function saveResult(Match $match, Team $team, Status $status, $description, Result $result)
    {
        $result->setMatch($match);
        $result->setTeam($team);
        $result->setStatus($status);
        $result->setDescription($description);
        $result->setUpdatedAt(Alaress_Date_Format::now());

        $this->getMapper()->save($result);
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
     * @return MatchMapper
     */
    protected function getMatchMapper()
    {
        return $this->matchMapper;
    }

    /**
     * @param MatchMapper $matchMapper
     */
    protected function setMatchMapper($matchMapper)
    {
        $this->matchMapper = $matchMapper;
    }

    /**
     * @return TeamMapper
     */
    protected function getTeamMapper()
    {
        return $this->teamMapper;
    }

    /**
     * @param TeamMapper $teamMapper
     */
    protected function setTeamMapper($teamMapper)
    {
        $this->teamMapper = $teamMapper;
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

    /**
     * @return StatusMapper
     */
    public function getStatusMapper()
    {
        return $this->statusMapper;
    }

    /**
     * @param StatusMapper $statusMapper
     * @return static
     */
    public function setStatusMapper($statusMapper)
    {
        $this->statusMapper = $statusMapper;
        return $this;
    }
}
