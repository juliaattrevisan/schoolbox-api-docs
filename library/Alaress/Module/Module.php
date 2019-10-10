<?php
/**
 * Catch-all class for defining modules.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Alaress\Twig\Loader\IChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Error\LoaderError;
use function array_merge;

class Module {

    /** @var RouteParser */
    private static $routeParser;

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
     * Modules that this module depends on.
     *
     * Each entry must be a module in $this->injector.
     *
     * @deprecated Module dependencies should now be automatically installed when required
     *             (@see `Alaress\Module\Loader` and classes which use it)
     *
     * @var array
     */
    protected $moduleDependencies = [];

    /**
     * Has this module had its controller's dependencies installed yet?
     * They should only be installed once.
     *
     * @var bool
     */
    protected $isControllerDependenciesInstalled = false;

    /**
     * List of routes that can be pre-configured for registration
     *
     * @var array
     * @internal string   $routeIndex[0] eg 'GET'
     * @internal string   $routeIndex[1] eg '/{studentId}/{classId}/update'
     * @internal string[] $routeIndex[2] eg ['studentId' => '\d+']   (note that classId does not have a regex constraint)
     */
    protected $routeIndex   = [];

    /**
     * List of preloads indexed by callable, for pre-registered routes
     *
     * @var array
     */
    protected $preloadIndex = [];

    /**
     * List of permissions indexed by Rule, for pre-registered routes
     *
     * @var array
     */
    protected $permissionIndex  = [];


    /**
     * Constructor
     *
     * TODO don't set $injector here, pass as a param to init() instead.
     * (This would mean that custom constructors could be used, and $injector
     *  shouldn't be used outside of init() anyway)
     *
     * @param ContainerInterface $injector
     * @param string             $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '') {
        $this->setInjector($injector);
        if ($basename) {
            $this->setBasename($basename);
        }
    }


    /**
     * Shorthand for all the things you can configure on a route
     *
     * @param string             $httpMethod            typically one of 'GET' or 'POST', but any HTTP method is okay here
     * @param string             $path                  parameterised form of the URL that accepts tokens in-line
     *                                                   - the notation for setting a token is {param:regex}
     * @param string             $controllerMethodName  the method on the controller to which this route points
     * @param IAuthorizationRule $permissions           an optional rule to use to define the permissions allowed on this route
     * @param callable           $preload               an optional callable used to preload this route
     */
    protected function register($httpMethod, $path, $controllerMethodName, IAuthorizationRule $permissions = null, callable $preload = null)
    {
        // index the route
        $this->routeIndex[$controllerMethodName] = array_merge([$httpMethod], $this->getRouterParser()->parse($path));

        if ($preload) {
            // index the preloader
            $this->putIntoIndex($this->preloadIndex, $preload, $controllerMethodName);
        }

        if ($permissions) {
            // index the permission callable
            $this->putIntoIndex($this->permissionIndex, $permissions, $controllerMethodName);
        }
    }


    /**
     * Poor man's SplObjectStorage (or, particularly, when you need to index by closure...)
     * Indexes the given "string" by the given "object" into the given "storage"
     *
     * @param array  $storage
     * @param mixed  $object
     * @param string $string
     * @return void
     */
    private function putIntoIndex(array &$storage, $object, $string)
    {
        foreach ($storage as $i => list($stored, $usages)) {
            if ($stored === $object) {
                $index = $i;
                break;
            }
        }

        if (isset($index)) {
            $storage[$index][1][] = $string;
        } else {
            $storage[] = [ $object, [ $string ] ];
        }
    }


    /**
     * Installs module with its most basic configuration.
     *
     * This will not override a previous install of a module with the same basename.
     *
     * @param string $basePath The base URL for this module, used by initRoutes
     */
    public function init($basePath)
    {
        $this->internalInit($basePath, false);
    }

    /**
     * Installs module with its most basic configuration, overriding a previous
     * install of a module with the same basename.
     *
     * @param string $basePath The base URL for this module, used by initRoutes
     */
    public function initOverride($basePath)
    {
        $this->internalInit($basePath, true);
    }

    /**
     * Installs module with its most basic configuration.
     *
     * Only the module's submodules and routes should be initialised here -
     * these are the bare minimum the kernel requires to bind a module to a
     * route, and then execute it.
     *
     * @param string $basePath The base URL for this module, used by initRoutes
     * @param bool   $override If true, override a previous definition with this basename
     */
    protected function internalInit($basePath, $override)
    {
        $injector = $this->getInjector();
        if ($this->installModule($injector, $override)) {
            $this->initSubmodules($injector, $basePath);

            // perform any route registration available
            $this->registerRoutes();

            // preload registered routes
            foreach ($this->routeIndex as $controllerMethod => list ($method, $path, $tokens)) {
                $this->addRoute($this->getRouter(), $controllerMethod, $basePath, $path)
                     ->setMethod($method)
                     ->setTokens($tokens);
            }
            // no need that no more
            unset($this->routeIndex);

            // manual route init
            $this->initRoutes($this->getRouter(), $basePath);
        }
    }

    /**
     * Performs setup steps required to use this module's controller.
     *
     * This should include the module's route permissions and preloads, and its
     * template paths, and any modules the controller depends on.
     */
    public function initControllerDependencies()
    {
        if ($this->isControllerDependenciesInstalled) {
            return;
        }
        $this->isControllerDependenciesInstalled = true;
        $injector                                = $this->getInjector();
        $this->preInitControllerDependencies($injector);
        $this->initDependingModules();

        // configure pre-registered preloaders
        foreach ($this->preloadIndex as list ($preload, $methods)) {
            $regex = '(?:' . implode('|', $methods) . ')';
            $this->addRoutePreload($this->getPathMatcher(), $regex, $preload);
        }
        // no need that no more
        unset($this->preloadIndex);

        // configure pre-registered permissions
        foreach ($this->permissionIndex as list ($rule, $methods)) {
            /** @var IAuthorizationRule $rule */
            $regex = '(?:' . implode('|', $methods) . ')';
            $this->addRoutePermission($this->getAuthorizer(), $regex, $rule);
        }
        // no need that no more
        unset($this->permissionIndex);

        // manual preloads
        $this->initRoutePreloads($this->getPathMatcher());

        // manual permissions
        $this->initRoutePermissions($this->getAuthorizer());

        // initialise templates
        $this->initTemplatePaths($this->getTemplateLoader());
    }

    /**
     * Installs this module into the dependency injection system.
     *
     * @param  ContainerInterface $injector
     * @param  bool               $override If true, override a previous definition with this basename
     * @return bool Was the module installed?
     * @throws LogicException if this module has no basename, or it is already defined as a different class
     */
    protected function installModule(ContainerInterface $injector, $override = false)
    {
        if (!$this->basename) {
            throw new LogicException(sprintf('Module class "%s" has no basename set', get_class($this)));
        }
        $key = 'module:'.$this->basename;
        if (!$override && $injector->has($key)) {
            $other = $injector->get($key);
            if (get_class($other) != get_class($this)) {
                throw new LogicException(
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
        }
        $injector->set($key, $this);
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
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
    }

    /**
     * Shortcut method to register routes with permissions
     *
     * The array structure passed should resemble something like this:
     *
     * $rulePastoralModeratorWrite = new IsPastoralModeratorWrite();
     * $ruleStaff = new Rule\HasRole(Role::STAFF);
     *
     * $routes = [
     *     'GET' => [
     *         '/groups'                   => ['getGroups' => $rulePastoralModeratorWrite],           <-- Pass controller function as array key, and permission as value
     *         '/groups/modify{/groupId}'  => ['getGroupsModify' => $rulePastoralModeratorWrite],
     *         '/report'                   => 'getReport',                                            <-- If no rule, just pass string method name
     *         '/report/builder'           => ['getReportBuilder' => $ruleStaff],
     *         '/report/print'             => ['getReportPrint' => $ruleStaff],
     *     ],
     *     'POST' => [
     *         '/groups/delete'            => ['postGroupsDelete' => $rulePastoralModeratorWrite],
     *         '/groups/modify'            => ['postGroupsModify' =>$rulePastoralModeratorWrite],
     *     ]
     * ];
     *
     * @param array $routes
     */
    protected function registerRoutesArray($routes)
    {
        foreach ($routes as $httpMethod => $route) {
            foreach ($route as $path => $controllerMethod) {
                if (is_array($controllerMethod)) {
                    foreach ($controllerMethod as $methodName => $permissions) {
                        $this->register($httpMethod, $path, $methodName, $permissions);
                    }
                } else {
                    $this->register($httpMethod, $path, $controllerMethod);
                }
            }
        }
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
     * Anything after the ':' character in $alias is ignored when matching
     * the requested path against preloads or permissions.
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
     * Anything after the ':' character in $alias is ignored when matching
     * the requested path against preloads or permissions.
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
     * Anything after the ':' character in $alias is ignored when matching
     * the requested path against preloads or permissions.
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
     * The "update:post", "replace:post" and "delete:post" routes this method
     * match against preloads or permissions for "update", "replace" and "delete"
     * respectively.
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
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
    }

    /**
     * Calls the secondary init on each module that this one depends on.
     *
     * @deprecated Module dependencies should now be automatically installed when required
     *             (@see `Alaress\Module\Loader` and classes which use it)
     *
     * @throws LogicException if a dependency doesn't exist
     */
    protected function initDependingModules()
    {
        foreach ($this->moduleDependencies as $dependency) {
            $dependingModule = $this->getInjector()->get('module:'.$dependency);
            if (!$dependingModule instanceof Module) {
                throw new LogicException(sprintf('Module "%s" does not exist', $dependency));
            }
            $dependingModule->initControllerDependencies();
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
    public function initRoutePreloads(PathMatcher $matcher) {}

    /**
     * Helper method for adding a route preload for this component's basename.
     *
     * @param  PathMatcher  $matcher
     * @param  string|array $regexPart  The part of the route(s) to match (after $this->basename)
     * @param  callable     $preload
     * @return $this
     */
    protected function addRoutePreload(PathMatcher $matcher, $regexPart, callable $preload)
    {
        if (!is_array($regexPart)) {
            $regexPart = [$regexPart];
        }
        foreach ($regexPart as $part) {
            $matcher->add("/^{$this->basename}\\.{$part}$/", $preload);
        }
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
     * @param  string|array       $regexPart  The part of the route(s) to match (after $this->basename)
     * @param  IAuthorizationRule $rule
     * @return $this
     */
    protected function addRoutePermission(Authorizer $authorizer, $regexPart, IAuthorizationRule $rule)
    {
        if (!is_array($regexPart)) {
            $regexPart = [$regexPart];
        }
        foreach ($regexPart as $part) {
            $authorizer->addRule("/^{$this->basename}\\.{$part}$/", $rule);
        }
        return $this;
    }

    /**
     * Initializes template paths for this module.
     *
     * @param IChainLoader $templateLoader
     * @throws LoaderError if the directory does not exist
     */
    public function initTemplatePaths(IChainLoader $templateLoader) {}


    /**
     * Helper method for adding a template path for this component's basename.
     *
     * Now adds school-specific templates to "$dir/custom/$config[site_location]".
     *
     * @param  IChainLoader $templateLoader
     * @param  string            $dir The directory to use as the template path
     * @param  string            $basename
     * @return $this
     * @throws LoaderError if something happened whilst loading
     */
    protected function addTemplatePath(IChainLoader $templateLoader, $dir, $basename = '')
    {
        $basePath     = $dir;
        $overridePath = $basePath . '/custom/' . $this->getConfig()->get('site_location');
        $namespace    = $basename ?: $this->basename;

        // Set up the loader for this namespace.
        $loader = new FilesystemLoader([]);
        if (is_dir($overridePath)) {
            // Add the school-specific dir if it's there
            $loader->addPath($overridePath, $namespace);
        }
        $loader->addPath($basePath, $namespace);
        $templateLoader->addLoader($loader);

        // Set up the core loader for this namespace as "$basename:core".
        // This contains only the base path, even if the custom path exists:
        // this allows custom templates to extend a core version of the template.
        $coreLoader = new FilesystemLoader([]);
        $coreLoader->addPath($basePath, $namespace . ':core');
        $templateLoader->addLoader($coreLoader);

        return $this;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @return MapperFactory
     */
    protected function getMapperFactory(): MapperFactory
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->getInjector()->get('mapperFactory');
    }

    /**
     * @return \External_UserInterface
     */
    protected function getExternalUser()
    {
        return $this->getInjector()->get('externalUser');
    }

    /**
     * @return \External_TimetableInterface
     */
    protected function getExternalTimetable()
    {
        return $this->getInjector()->get('externalTimetable');
    }

    /**
     * Typehinted convenience method for obtaining router reference
     *
     * @return Router
     */
    protected function getRouter()
    {
        return $this->injector->get('router');
    }

    /**
     * Typehinted convenience method for obtaining preloader reference
     *
     * @return PathMatcher
     */
    protected function getPathMatcher()
    {
        return $this->injector->get('preloader');
    }

    /**
     * Typehinted convenience method for obtaining authorizer router reference
     *
     * @return Authorizer
     */
    protected function getAuthorizer()
    {
        return $this->injector->get('authorizer');
    }

    /**
     * Typehinted convenience method for obtaining template loader reference
     *
     * @return IChainLoader
     */
    protected function getTemplateLoader()
    {
        return $this->injector->get('templateEngine:loader');
    }

    /**
     * @return IConfigLoader
     */
    protected function getConfig(): IConfigLoader
    {
        return $this->getInjector()->get('config');
    }

    /**
     * @param string $name
     * @throws \InvalidArgumentException
     * @return IAuthorizationRule
     */
    protected function getAuthRule($name)
    {
        try {
            return $this->injector->get("rule:$name");
        } catch (\Aura\Di\Exception\ServiceNotFound $e) {
            throw new \InvalidArgumentException("Auth rule '$name' does not exist", 0, $e);
        }
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

    /**
     * @return string
     */
    protected function getBasename()
    {
        return $this->basename;
    }


    /**
     * @param $basename
     * @return $this
     */
    protected function setBasename($basename)
    {
        $this->basename = $basename;
        return $this;
    }


    /**
     * Lazily created and return a RouteParser
     *
     * @return RouteParser
     */
    private function getRouterParser(): RouteParser
    {
        if (!self::$routeParser) {
            self::$routeParser = new RouteParser();
        }
        return self::$routeParser;
    }
}
