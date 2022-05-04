<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Division;

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
     * @var string
     */
    protected $basename = 'sportsfixturesDivision';

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule|null $context
     */
    public function __construct(ContainerInterface $injector, ?RestfulModule $context)
    {
        parent::__construct($injector, Mapper::class, 'divisions', $context);
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
        $routes = [
            'GET' => [
                "/$collection/{entityId}/lobby" => "getLobby",
            ],
            "POST" => [
                "/$collection/{entityId}/addPlayers"     => "postAddPlayers",
                "/$collection/{entityId}/uploadPlayers"  => "postUploadPlayers",
                "/$collection/{entityId}/removePlayers"  => "postRemovePlayers",
                "/$collection/{entityId}/reorderPlayers" => "postReorderPlayers",
                "/$collection/{entityId}/setPositions"   => "postPlayerPositions",
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        parent::initRoutePreloads($matcher);
        $this->addRoutePreload(
            $matcher,
            '(?:postModify|postAddPlayers|postRemovePlayers|postReorderPlayers|postUploadPlayers)',
            function ($params) {
                $division = $this->getMapper()->findById($params['entityId']);
                return [
                    'params'   => $params,
                    'division' => $division
                ];
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        /* Permissions requirements for being a Sports Administrator
         */
        $isSportsAdministrator = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_SPORTS_ADMINISTRATOR, UserAccess::ACCESS_WRITE)
            )
        );

        /* Apply route permissions here as you add them to initRoutes()
         * Note that if the perms change you need to log out/log in to refresh
         */
        $this->addRoutePermission($authorizer, '.*', $isSportsAdministrator);
    }
}
