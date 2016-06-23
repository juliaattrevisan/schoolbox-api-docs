<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Position;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Alaress_Date_Format;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\SportsFixtures\Position\Model as Position;
use Twig_Loader_Chain;
use Twig_Error_Loader;


class Module extends BaseModule
{
    /**
     * The base name for this module.
     *
     * This module will be injected into the dependency injector at "module:$basename".
     *
     * @var string
     */
    protected $basename = 'sportsfixturesPosition';

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
        $routes = [
            'GET' => [
                '/list/{sportId}'      => 'getList',
                '/create/{sportId}'    => 'getCreate',
                '/update/{positionId}' => 'getUpdate',
            ],
            'POST' => [
                '/create/{sportId}'    => 'postCreate',
                '/update/{positionId}' => 'postModify',
                '/delete'              => 'postDelete',
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
     * Initializes any preloads on routes for this module.
     *
     * Preloads adjust route params before they are passed to the controller.
     * Usually, they're used to load a single item into the route's params,
     * which is then passed directly as a parameter to the action.
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '(?:postCreate)', function ($params) {
            /** @var Position $position */
            $position = Mapper::getInstance()->createModel();
            $position->setCreatedAt(Alaress_Date_Format::now());

            return [
                'params' => $params,
                'position' => $position
            ];
        });

        $this->addRoutePreload($matcher, '(?:postModify)', function ($params) {
            $position = Mapper::getInstance()->findById($params['positionId']);

            return [
                'params' => $params,
                'position' => $position
            ];
        });
    }

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer) {

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
        $this->addRoutePermission($authorizer, 'admin', $isSportsAdministrator);
    }

    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }
}