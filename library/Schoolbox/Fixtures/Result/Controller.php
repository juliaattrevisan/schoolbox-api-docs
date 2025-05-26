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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Weather as MatchupWeather;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Status\Mapper as StatusMapper;
use Schoolbox\Fixtures\Status\Model as Status;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Metadata\Factory as TeamMetadataFactory;
use Schoolbox\TagCloud\Mapper as TagCloudMapper;
use Schoolbox\Weather\WeatherstackClient;

/**
 * Class Controller
 *
 * @package Schoolbox\Fixtures\Result
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
     * @var MatchupMapper
     */
    protected $matchupMapper;

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
        $this->setMatchupMapper(MatchupMapper::getInstance());
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
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface|\Schoolbox\Application\Exception\Exception
     * @return void
     */
    public function getCreateFormForTeam($entity, $team, $context = null)
    {
        if (empty($context)) {
            try {
                $context = $this->getMatchupMapper()->findById($entity->getMatchup()->getId());
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

        $this->response = $this->responseFromTemplate(
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
        );
    }

    /**
     * @inheritdoc
     *
     * @param Result $entity
     * @throws \Exception
     */
    public function createResultForTeam($entity)
    {
        $this->persistEntity($entity);
    }

    /**
     * List of Results and the Match they are in
     *
     * @param Matchup $context
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound|\Schoolbox\Application\Exception\Exception
     */
    public function getList($context = null)
    {
        try {
            /** @var Matchup $context */
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

            $this->response = $this->responseFromTemplate(
                '@sportsfixturesResult/list.twig',
                [
                    'sport'      => $sport,
                    'division'   => $division,
                    'match'      => $context,
                    'allResults' => $allResults
                ]
            );
        } catch (MapperException $e) {
            /* No Results for this Match?
             * Prompt to spawn one
             */
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
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
     * @param Matchup $context
     * @param Team $team
     * @throws MapperException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface|\Schoolbox\Application\Exception\Exception
     */
    public function getResultForTeam($context, $team)
    {
        try {
            /** @var Matchup $context */
            $matchup = $this->getMatchupMapper()->findById($context->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            $division = $this->getDivisionMapper()->findById($matchup->getDivision()->getId());
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
        if (!$matchup->getHomeTeam()) {
            if ($matchup->getHomeSchool()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($matchup->getHomeSchool()->getName());

                $matchup->setHomeTeam($facelessTeam);
            } else {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
            }
        }

        /* Find faceless away team, ram it into a Team instance
         * This saves endless shenanigans in front-end land
         */
        if (!$matchup->getAwayTeam()) {
            if ($matchup->getAwaySchool()) {
                $facelessTeam = $this->getTeamMapper()->createModel()->setName($matchup->getAwaySchool()->getName());

                $matchup->setAwayTeam($facelessTeam);
            } else {
                $matchup->setAwayTeam($this->getTeamMapper()->createModel());
            }
        }

        /* Tell me the weather:
         * since this module is special, the weather client is not injected
         * into the constructor: instead it must be conjured out of DI.
         */
        /** @var WeatherstackClient $weatherClient */
        $weatherClient = $this->getDi()->get('weatherClient');

        $forecast = (new MatchupWeather($matchup, $weatherClient))->loadForecast();

        /* Determine whether you've got write access or not
         * when viewing this page
         */
        $hasMatchModify = $this->hasMatchModify($teamMetadata->getCoaches()->getOffset($team->getId()));

        $this->response = $this->responseFromTemplate(
            '@sportsfixturesResult/team.twig',
            [
                'sport'          => $sport,
                'division'       => $division,
                'match'          => $matchup,
                'schoolMapper'   => $this->getSchoolTagCloudMapper(),
                'team'           => $team,
                'teamMetadata'   => $teamMetadata,
                'result'         => $context,
                'googleApiKey'   => $this->getConfigLoader()->get(ConfigMapper::NAME_GOOGLEAPPS_API_KEY),
                'forecast'       => $forecast,
                'hasMatchModify' => $hasMatchModify
            ]
        );
    }

    /**
     * Determine whether the logged-in user has write access
     * to this Match page
     *
     * @param Collection $allCoaches
     * @return bool
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
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
            // Note sportsfixtures_coach.user_id is nullable, hence the null coalesce below.
            if ($currentCoach->getUser()?->getId() == $sessionUser->getId()) {
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
     * @param Matchup $context
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface|\Schoolbox\Application\Exception\Exception
     */
    protected function getForm($entity, $context = null)
    {
        // For clarity
        $result = $entity;
        $matchup = $context;

        // If updating existing results
        if ($result->getId()) {
            $matchup = MatchupMapper::getInstance()->findById($result->getMatchup()->getId());
            $result->setMatchup($matchup);

            // Don't permit update of archived results
            if ($matchup->getSeason()->isArchived()) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
            }
        }

        // Don't permit creation of results for archived matches
        if (!$result->getId() && $matchup->getSeason()->isArchived()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SEASON_ARCHIVED);
        }

        try {
            $team = $this->getTeamMapper()->findById($entity->getTeam()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        $this->getCreateFormForTeam($result, $team, $matchup);
    }

    /**
     * Persist method for entity
     *
     * @param Result $entity
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    protected function persistEntity($entity)
    {
        try {
            $matchId = $this->requestPost['matchId'];
            $matchup = $this->getMatchupMapper()->findById($matchId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_MATCH_NOT_FOUND);
        }

        try {
            $teamId = $this->requestPost['teamId'];
            $team   = $this->getTeamMapper()->findById($teamId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
        }

        // Save status (optional)
        $statusId = $this->requestPost->get('statusId');
        if ($statusId) {
            try {
                $status = $this->getStatusMapper()->findById($statusId);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_STATUS_NOT_FOUND);
            }
        } else {
            $status = $this->getStatusMapper()->createModel();
        }

        $this->saveResult($matchup, $team, $status, $this->requestPost['description'], $entity);

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate(
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
     * @param Matchup $matchup
     * @param Team $team
     * @param Status $status
     * @param string $description
     * @param Result $result
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveResult(Matchup $matchup, Team $team, Status $status, $description, Result $result)
    {
        $result->setMatchup($matchup);
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
     * @return MatchupMapper
     */
    protected function getMatchupMapper()
    {
        return $this->matchupMapper;
    }

    /**
     * @param MatchupMapper $matchupMapper
     */
    protected function setMatchupMapper($matchupMapper)
    {
        $this->matchupMapper = $matchupMapper;
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
