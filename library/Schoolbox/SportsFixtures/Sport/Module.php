<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
 
namespace Schoolbox\SportsFixtures\Sport;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\SportsFixtures\Sport\Model as Sport;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'sportsfixturesSport';

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
                '/list'             => 'getList',
                '/create'           => 'getCreate',
                '/update/{sportId}' => 'getUpdate',
            ],
            'POST' => [
                '/create'           => 'postCreate',
                '/update/{sportId}' => 'postModify',
                '/delete'           => 'postDelete',
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
        $this->addRoutePreload($matcher, '(?:postCreate)', function ($params) {
            /** @var Sport $sport */
            $sport = Mapper::getInstance()->createModel();
            $sport->setCreatedAt(\Alaress_Date_Format::now());

            return [
                'params' => $params,
                'sport' => $sport
            ];
        });

        $this->addRoutePreload($matcher, '(?:postModify)', function ($params) {
            $sport = Mapper::getInstance()->findById($params['sportId']);

            return [
                'params' => $params,
                'sport' => $sport
            ];
        });
    }

    /**
     * {@inheritdoc}
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
