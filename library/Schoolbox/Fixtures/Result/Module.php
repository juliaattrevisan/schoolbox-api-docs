<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Result;

use Alaress\Web\Router\PathMatcher;
use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Aura\Router\Router;
use External_UserInterface;
use Psr\Container\ContainerInterface;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Authorizer\Rule\IsCoachOfTeam;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * Dependency on external user database.
     *
     * Required for checking that the requesting user is the student's parent.
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule|null $context
     */
    public function __construct(ContainerInterface $injector, ?RestfulModule $context)
    {
        parent::__construct($injector, Mapper::class, 'results', $context, 'sportsfixturesResult');
    }

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        /* Any authenticated user can access the result page
         */
        $resultAccess = new Rule\All(
            new Rule\IsLoggedIn()
        );

        /* Apply route permissions here as you add them to initRoutes()
         * Note that if the perms change you need to log out/log in to refresh
         */
        $this->addRoutePermission($authorizer, 'getResultForTeam|getList', $resultAccess);

        /* Superusers can see this
         * Sports Admin can see this
         * Coaches can see this
         */
        $resultModifyFormAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_SPORTS_ADMINISTRATOR, UserAccess::ACCESS_WRITE),
                new IsCoachOfTeam()
            )
        );

        $this->addRoutePermission($authorizer, 'getCreateFormForTeam|getModifyForm|getCreateForm|getEntity|createEntity|updateEntity|deleteEntity', $resultModifyFormAccess);
    }

    /**
     * {@inheritdoc}
     *
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);

        $collection = $this->getCollectionName();

        // if this module has context (ie. a containing entity) then create a prefix to be used with contextual routes
        $context = $this->context
            ? "{$this->context->getCollectionName()}/{contextId}/"
            : '';

        $routes = [
            'GET' => [
                "/$context$collection/{teamId}/new" => 'getCreateFormForTeam',
                "/$context$collection/{teamId}"     => 'getResultForTeam',
            ],
            'POST' => [
                "/match/{matchId}/team/{teamId}/$collection" => 'createResultForTeam',
            ]
        ];

        foreach ($routes['GET'] as $path => $controllerMethod) {
            $this->addGetRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+', 'entityId' => '\d+' ]);
        }

        foreach ($routes['POST'] as $path => $controllerMethod) {
            $this->addPostRoute($router, $controllerMethod, $basePath, $path)->setTokens([ 'contextId' => '\d+', 'entityId' => '\d+' ]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        parent::initRoutePreloads($matcher);

        // these routes require that the entity exist
        $this->addRoutePreload($matcher, '(?:getCreateFormForTeam)', function ($params) {
            try {
                $team = $this->getTeamMapper()->findById($params['teamId']);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
            }

            return [
                'entity' => $this->getMapper()->createModel(),
                'context' => $this->findContextById($params['contextId']),
                'team' => $team
            ];
        });

        $this->addRoutePreload($matcher, '(?:createResultForTeam)', function ($params) {
            return [
                'entity' => $this->getMapper()->createModel(),
                'team' => $this->getTeamMapper()->createModel()
            ];
        });

        /* Result Page for a particular Team
         * Check that the Team still exists
         */
        $this->addRoutePreload($matcher, '(?:getResultForTeam)', function ($params) {
            try {
                $team = $this->getTeamMapper()->findById($params['teamId']);
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_TEAM_NOT_FOUND);
            }

            return [
                'context' => $this->findContextById($params['contextId']),
                'team' => $team
            ];
        });
    }

    /* Getters / Setters
     */

    /**
     * @return TeamMapper
     */
    protected function getTeamMapper()
    {
        return $this->getMapperFactory()->get(TeamMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
