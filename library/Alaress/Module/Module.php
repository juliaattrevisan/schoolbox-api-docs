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
use Alaress\Web\Controller\Base;
use Alaress\Web\Request\IsJson;
use Alaress\Web\Request\IsXhr;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Aura\Router\Route;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Alaress\Web\Router\PathMatcher;
use Alaress\Web\Router\RouteCollection;
use LogicException;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Alaress\Twig\Loader\IChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Error\LoaderError;

use function array_merge;
use function strtolower;

class Module
{
    // HTTP methods
    public const METHOD_GET    = 'GET';
    public const METHOD_POST   = 'POST';
    public const METHOD_PUT    = 'PUT';
    public const METHOD_PATCH  = 'PATCH';
    public const METHOD_DELETE = 'DELETE';


    /** @var RouteParser */
    private static $routeParser;

    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $injector;

    /**
     * The base name for this module.
     *
     * @var string
     */
    protected $basename;

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
    protected $routeIndex = [];

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
    protected $permissionIndex = [];

    /**
     * Constructor
     *
     * @param ContainerInterface $injector
     */
    public function __construct(ContainerInterface $injector)
    {
        $this->injector = $injector;
    }


    /**
     * Shorthand for all the things you can configure on a route
     *
     * @todo does not yet support repetition quantifiers in route, eg 'year:\d{4}'
     * @see \Alaress\Module\Test\RouterParserTest
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
        foreach ($storage as $i => [$stored, $usages]) {
            if ($stored === $object) {
                $index = $i;
                break;
            }
        }

        if (isset($index)) {
            $storage[$index][1][] = $string;
        } else {
            $storage[] = [$object, [$string]];
        }
    }


    /**
     * Installs a module's routes.
     *
     * A module's routes are the bare minimum the kernel requires to bind a
     * module to a route, and then execute it.
     *
     * @param string|null $basePath The base URL for this module, used by
     *                              `initRoutes`.
     *                              If this is null, this can't have any routes.
     */
    public function installRoutes(?string $basePath)
    {
        if (isset($basePath)) {
            // perform any route registration available
            $this->registerRoutes();

            // preload registered routes
            foreach ($this->routeIndex as $controllerMethod => [$method, $path, $tokens]) {
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
     * Register this module.
     *
     * @param string|null $basePath
     * @return void
     */
    public function registerModule(?string $basePath): void
    {
        Registry::register($this, $basePath);
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

        // configure pre-registered preloaders
        foreach ($this->preloadIndex as [$preload, $methods]) {
            $regex = '(?:' . implode('|', $methods) . ')';
            $this->addRoutePreload($this->getPathMatcher(), $regex, $preload);
        }
        // no need that no more
        unset($this->preloadIndex);

        // configure pre-registered permissions
        foreach ($this->permissionIndex as [$rule, $methods]) {
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
    public function initRoutes(Router $router, $basePath)
    {
    }

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
        return $router->getRoutes()->add($this->basename . '.' . $alias, $basePath . $path);
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
        return $router->getRoutes()->addGet($this->basename . '.' . $alias, $basePath . $path);
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
        return $router->getRoutes()->addPost($this->basename . '.' . $alias, $basePath . $path);
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

        // Attach POST aliases for the PUT/PATCH/DELETE routes added by
        // `attachResource`.
        $routes->attach(
            $this->basename,
            $basePath,
            function (RouteCollection $subroute) {
                $subroute->setTokens(['id' => '\d+', 'format' => '(\.[^/]+)?',]);
                $subroute
                    ->addPost('update:post', '/{id}/update')
                    ->addValues(['action' => 'update']);
                $subroute
                    ->addPost('replace:post', '/{id}/replace')
                    ->addValues(['action' => 'replace']);
                $subroute
                    ->addPost('delete:post', '/{id}/delete')
                    ->addValues(['action' => 'delete']);
            }
        );
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
    }

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
    public function initRoutePermissions(Authorizer $authorizer)
    {
    }

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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
    }


    /**
     * Helper method for adding a template path for this component's basename.
     *
     * Now adds school-specific templates to "$dir/custom/lower($config[site_school_code])".
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
        $overridePath = $basePath . '/custom/' . strtolower($this->getConfig()->get('site_school_code'));
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
        return $this->injector->get('mapperFactory');
    }

    /**
     * @return \External_UserInterface
     */
    protected function getExternalUser()
    {
        return $this->injector->get('externalUser');
    }

    /**
     * @return \External_TimetableInterface
     */
    protected function getExternalTimetable()
    {
        return $this->injector->get('externalTimetable');
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
        return $this->injector->get('config');
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
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            throw new \InvalidArgumentException("Auth rule '$name' does not exist", 0, $e);
        }
    }


    /**
     * Actually builds the controller, if this module has one.
     *
     * Modules which instantiate only a homepage/dashboard/ePortfolio component
     * will not have a controller: they will not have any routes either, as
     * they have no controller to reference those routes to. This method will
     * throw a `LogicException` in this case.
     *
     * This may pull dependencies from the DI container, but will not write to
     * it.
     *
     * @return Base
     * @throws LogicException if this module has no controller
     */
    public function buildController(): Base
    {
        throw new LogicException(sprintf('Controller does not exist for module "%s"', $this->basename));
    }


    /**
     * Installs dependencies into the controller.
     *
     * Ported from DI configuration of controller
     *
     * @template T of Base
     * @param T               $controller
     * @param ContainerInterface $container
     * @return T
     */
    public function installControllerDependencies(Base $controller, ContainerInterface $container): Base
    {
        $controller
            ->setRequest($container->get('webFactory')->newRequest())
            ->setResponse($container->get('webFactory')->newResponse())
            ->setSession($container->get('session'))
            ->setRouter($container->get('router'))
            ->setAuthorizer($container->get('authorizer'))
            ->setDi($container)
            ->setExceptionGenerator($container->get('mapperFactory')->get(ErrorMapper::class))
            ->setSentry($container->has('sentry') ? $container->get('sentry') : null)
            ->setIsJsonSpec(new IsJson())
            ->setIsXhrSpec(new IsXhr());
        return $controller;
    }


    /**
     * @return string
     */
    public function getBasename()
    {
        return $this->basename;
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
