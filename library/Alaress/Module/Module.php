<?php
/**
 * Catch-all class for defining modules.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Module;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module {

    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected $injector;

    /**
     * The base name for this module.
     *
     * This module will be injected into the dependency injector at "module:$basename".
     *
     * @var string
     */
    protected $basename;

    /**
     * Constructor
     *
     * TODO don't set $injector here, pass as a param to init() instead.
     * (This would mean that custom constructors could be used, and $injector
     *  shouldn't be used outside of init() anyway)
     *
     * @param ContainerInterface $injector
     */
    public function __construct(ContainerInterface $injector) {
        $this->setInjector($injector);
    }

    /**
     * Inits module
     *
     * @param string $basePath The base URL for this module, used by initRoutes
     */
    public function init($basePath) {
        $injector = $this->getInjector();
        $this->initSubmodules($injector, $basePath);
        $this->initRoutes($injector->get('router'), $basePath);
        // TODO preloads and permissions should be able to be set up separately
        $this->initRoutePreloads($injector->get('preloader'));
        $this->initRoutePermissions($injector->get('authorizer'));
        $this->initTemplatePaths($injector->get('templateEngine:loader'));
        $this->initControllers($injector);
    }

    /**
     * Installs this module into the dependency injection system.
     *
     * @param ContainerInterface $injector
     */
    protected function installModule(ContainerInterface $injector)
    {
        $injector->set('module:'.$this->basename, $this);
    }

    /**
     * Initializes any submodules of this module.
     *
     * @param ContainerInterface $injector
     * @param string             $basePath The base route for this module
     */
    public function initSubmodules(ContainerInterface $injector, $basePath)
    {
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
    public function initRoutes(Router $router, $basePath) {}

    /**
     * Helper for calling $router->addGet() for this component's basename.
     *
     * @param Router $router   The router to add the route to
     * @param string $alias    The alias for this route.
     *                         Its actual alias will be "$this->basename.$alias"
     * @param string $basePath The base path for this route
     * @param string $path     The path for this route.
     *                         Its actual path will be "$basePath/$path".
     */
    protected function addGetRoute(Router $router, $alias, $basePath, $path)
    {
        $router->addGet($this->basename.'.'.$alias, $basePath.'/'.$path);
    }

    /**
     * Helper for calling $router->addGet() for this component's basename.
     *
     * @param Router $router   The router to add the route to
     * @param string $alias    The alias for this route.
     *                         Its actual alias will be "$this->basename.$alias"
     * @param string $basePath The base path for this route
     * @param string $path     The path for this route.
     *                         Its actual path will be "$basePath/$path".
     */
    protected function addPostRoute(Router $router, $alias, $basePath, $path)
    {
        $router->addPost($this->basename.'.'.$alias, $basePath.'/'.$path);

    }

    /**
     * Helper for attaching a set of REST routes for this module.
     *
     * @param Router $router   The router to add the route to
     * @param string $basePath The base path for this route
     */
    protected function attachResourceRoutes(Router $router, $basePath)
    {
        $router->attachResource($this->basename, $basePath);
        $router->attachResourcePostAlias($this->basename, $basePath);
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
    public function initRoutePreloads(PathMatcher $matcher) {}

    /**
     * Helper method for adding a route preload for this component's basename.
     *
     * @param string      $regexPart  The part of the route to match (after $this->basename)
     * @param PathMatcher $matcher
     * @param callable    $preload
     */
    protected function addRoutePreloadForBasename($regexPart, PathMatcher $matcher, callable $preload)
    {
        $matcher->add("/^{$this->basename}\\.{$regexPart}$/", $preload);
    }

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer) {}

    /**
     * Helper method for adding a route permission for this component's basename.
     *
     * @param string             $regexPart  The part of the route to match (after $this->basename)
     * @param Authorizer         $authorizer
     * @param IAuthorizationRule $rule
     */
    protected function addRoutePermissionForBasename($regexPart, Authorizer $authorizer, IAuthorizationRule $rule)
    {
        $authorizer->addRule("/^{$this->basename}\\.{$regexPart}$/", $rule);
    }

    /**
     * Initializes template paths for this module.
     *
     * @param Twig_Loader_Chain $templateLoader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {}

    /**
     * Helper method for adding a template path for this component's basename.
     *
     * @param string            $dir            The directory to use as the template path
     * @param Twig_Loader_Chain $templateLoader
     */
    protected function addTemplatePathForBasename($dir, Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem([]);
        $loader->addPath($dir, $this->basename);
        $templateLoader->addLoader($loader);
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector) {}

    /**
     * @param  ContainerInterface $injector
     * @return $this
     */
    public function setInjector(ContainerInterface $injector) {
        $this->injector = $injector;
        return $this;
    }

    /**
     * @return ContainerInterface
     */
    public function getInjector() {
        return $this->injector;
    }

}
