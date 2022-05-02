<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Psr\Container\ContainerInterface;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Schoolbox\Permission\Access\UserAccess;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule|null $context
     */
    public function __construct(ContainerInterface $injector, ?RestfulModule $context)
    {
        parent::__construct($injector, Mapper::class, 'teams', $context, 'sportsfixturesTeam');
    }

    /**
     * Initializes any routes for this module, with a given base path.
     *
     * The base path for each route needs to match up to the name of the
     * controller in $this->initControllers(), so if its base path is
     * "user", there must be a controller in the DI container called
     * "controller:user"
     *
     * @param Router $router
     * @param string $basePath The base route for this module
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);
        $collection = $this->getCollectionName();
        $this->addPostRoute($router, "postAddPlayers", $basePath, "/$collection/{entityId}/addPlayers");
        $this->addPostRoute($router, "postRemovePlayers", $basePath, "/$collection/{entityId}/removePlayers");
        $this->addPostRoute($router, "postReorderPlayers", $basePath, "/$collection/{entityId}/reorderPlayers");
    }

    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        parent::initRoutePreloads($matcher);
        $this->addRoutePreload(
            $matcher,
            '(?:postAddPlayers|postRemovePlayers|postReorderPlayers)',
            function ($params) {
                return ['team' => $this->getMapper()->findById($params['entityId'])];
            }
        );
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException If any sub-rules to composite do not implement IAuthorizationRule
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // SU can do mutate whatever, whenever. Sports Admin can mutate if not archived.
        $canMutateTeams = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\HasServicePermission(
                    UserAccess::SERVICE_SPORTS_ADMINISTRATOR,
                    UserAccess::ACCESS_WRITE
                )
            )
        );

        // Match all routes except for 'getList' and 'getEntity'
        $this->addRoutePermission($authorizer, '(?!(getList|getEntity)$).*', $canMutateTeams);

        // Any logged in user can see a list of teams (whether AJAX JSON or HTML page), or team details
        $this->addRoutePermission($authorizer, 'getList|getEntity', new Rule\IsLoggedIn());
    }
}
