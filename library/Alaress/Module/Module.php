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
use Aura\Router\Route;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Web\Router\PathMatcher;
use Alaress\Web\Router\RouteCollection;
use LogicException;
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
        if ($this->installModule($injector)) {
            $this->initSubmodules($injector, $basePath);
            $this->initRoutes($injector->get('router'), $basePath);
            // TODO preloads and permissions should be able to be set up separately
            $this->initRoutePreloads($injector->get('preloader'));
            $this->initRoutePermissions($injector->get('authorizer'));
            $this->initTemplatePaths($injector->get('templateEngine:loader'));
        }
    }

    /**
     * Installs this module into the dependency injection system.
     *
     * @param  ContainerInterface $injector
     * @return bool Was the module installed?
     * @throws LogicException if this module has no basename, or it is already defined as a different class
     */
    protected function installModule(ContainerInterface $injector)
    {
        if (!$this->basename) {
            throw new \LogicException(sprintf('Module class "%s" has no basename set', get_class($this)));
        }
        $key = 'module:'.$this->basename;
        if ($injector->has($key)) {
            $other = $injector->get($key);
            if (get_class($other) != get_class($this)) {
                throw new \LogicException(
                    sprintf(
                        'Module "%s" is already defined as instanceof "%s" - won\'t redefine as instanceof "%s"',
                        $this->basename,
                        get_class($other),
                        get_class($this)
                    )
                );
            } else {
                user_error(
                    sprintf('Module "%s" is already defined - not redefining', $this->basename),
                    E_USER_WARNING
                );
                return false;
            }
        } else {
            $injector->set($key, $this);
        }
        return true;
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
     * Helper for calling $router->add() for this component's basename.
     *
     * @param Router $router   The router to add the route to
     * @param string $alias    The alias for this route.
     *                         Its actual alias will be "$this->basename.$alias"
     * @param string $basePath The base path for this route
     * @param string $path     The path for this route, including a leading / if required.
     *                         Its actual path will be "$basePath$path".
     *
     * @return Route
     */
    protected function addRoute(Router $router, $alias, $basePath, $path)
    {
        return $router->getRoutes()->add($this->basename.'.'.$alias, $basePath.$path);
    }

    /**
     * Helper for calling $router->addGet() for this component's basename.
     *
     * @param Router $router   The router to add the route to
     * @param string $alias    The alias for this route.
     *                         Its actual alias will be "$this->basename.$alias"
     * @param string $basePath The base path for this route
     * @param string $path     The path for this route, including a leading / if required.
     *                         Its actual path will be "$basePath$path".
     *
     * @return Route
     */
    protected function addGetRoute(Router $router, $alias, $basePath, $path)
    {
        return $router->getRoutes()->addGet($this->basename.'.'.$alias, $basePath.$path);
    }

    /**
     * Helper for calling $router->addGet() for this component's basename.
     *
     * @param Router $router   The router to add the route to
     * @param string $alias    The alias for this route.
     *                         Its actual alias will be "$this->basename.$alias"
     * @param string $basePath The base path for this route
     * @param string $path     The path for this route, including a leading / if required.
     *                         Its actual path will be "$basePath$path".
     *
     * @return Route
     */
    protected function addPostRoute(Router $router, $alias, $basePath, $path)
    {
        return $router->getRoutes()->addPost($this->basename.'.'.$alias, $basePath.$path);
    }

    /**
     * Helper for attaching a set of REST routes for this module.
     *
     * @param Router $router   The router to add the route to
     * @param string $basePath The base path for this route
     */
    protected function attachResourceRoutes(Router $router, $basePath)
    {
        /** @var RouteCollection $routes */
        $routes = $router->getRoutes();
        $routes->attachResource($this->basename, $basePath);
        $routes->attachResourcePostAlias($this->basename, $basePath);
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
     * @param  PathMatcher $matcher
     * @param  string      $regexPart  The part of the route to match (after $this->basename)
     * @param  callable    $preload
     * @return $this
     */
    protected function addRoutePreload(PathMatcher $matcher, $regexPart, callable $preload)
    {
        $matcher->add("/^{$this->basename}\\.{$regexPart}$/", $preload);
        return $this;
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
     * @param  Authorizer         $authorizer
     * @param  string             $regexPart  The part of the route to match (after $this->basename)
     * @param  IAuthorizationRule $rule
     * @return $this
     */
    protected function addRoutePermission(Authorizer $authorizer, $regexPart, IAuthorizationRule $rule)
    {
        $authorizer->addRule("/^{$this->basename}\\.{$regexPart}$/", $rule);
        return $this;
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
     * @param  string            $dir            The directory to use as the template path
     * @param  Twig_Loader_Chain $templateLoader
     * @return $this
     */
    protected function addTemplatePath(Twig_Loader_Chain $templateLoader, $dir)
    {
        $loader = new Twig_Loader_Filesystem([]);
        $loader->addPath($dir, $this->basename);
        $templateLoader->addLoader($loader);
        return $this;
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
