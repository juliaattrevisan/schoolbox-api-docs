<?php
/**
 * Catch-all class for defining modules.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Module;

use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;

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
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer) {}

    /**
     * Initializes template paths for this module.
     *
     * @param Twig_Loader_Chain $templateLoader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {}

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
