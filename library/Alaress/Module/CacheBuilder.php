<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module;

use Alaress\Web\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Config\Modules\ModuleConfigInterface;
use Predis\ClientInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Method for rebuilding the cache for the route and module tables.
 */
class CacheBuilder
{
    /**
     * DI container: `\Config\Modules\ModuleConfigInterface::configure`
     * implementations require this to build their modules.
     */
    private ContainerInterface $di;

    /**
     * Route table: once built, modules add their routes into this table.
     */
    private Router $router;

    /**
     * Redis client: the route and module tables are stored here.
     */
    private ClientInterface $redis;

    /**
     * Logger: various log messages will be sent here.
     *
     * If not set in constructor, will be a `NullLogger`.
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ContainerInterface   $di
     * @param Router               $router
     * @param ClientInterface      $redis
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ContainerInterface $di,
        Router $router,
        ClientInterface $redis,
        ?LoggerInterface $logger
    ) {
        $this->di     = $di;
        $this->router = $router;
        $this->redis  = $redis;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Reloads the cache.
     *
     * @param string $siteSchoolCode The school code to rebuild the cache for.
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function rebuild(string $siteSchoolCode): void
    {
        $this->logger->info(sprintf('Refreshing route and module cache for instance "%s"', INSTANCE_ID));

        // Before this runs: clear the route table and module registrations,
        // so that they may be built afresh.
        $this->router->setRoutes(new RouteCollection(new RouteFactory()));
        Registry::setRegistrations([]);

        $this->loadAllModules($siteSchoolCode);

        // The new route table and module registrations have been loaded:
        // dump them into Redis.
        $this->logger->info('Writing route and module configs to Redis cache');
        $this->redis->set(RouteCollection::MODULE_CACHE_KEY_PREFIX . INSTANCE_ID, serialize($this->router->getRoutes()));
        $this->redis->set(Registry::MODULE_CACHE_KEY_PREFIX . INSTANCE_ID, json_encode(Registry::getRegistrations()));
    }

    /**
     * Load all module configs which are relevant for this instance, then
     * loads each of its modules and performs its init steps.
     *
     * This builds the route table and module registrations, ready to be
     * cached.
     *
     * @param string $siteSchoolCode
     * @return void
     * @throws NotFoundExceptionInterface  raised if a module's config expects a dependency which is not in `$di`
     * @throws ContainerExceptionInterface if there's another issue with `$di`
     */
    private function loadAllModules(string $siteSchoolCode): void
    {
        $coreModulePath   = SITE_root . '/conf/modules/*.php';
        $customModulePath = SITE_root . sprintf('/conf/modules/Custom/%s/*.php', ucfirst(strtolower($siteSchoolCode)));
        $this->logger->info(sprintf('Loading core module config from path: %s', $coreModulePath));
        $this->logger->info(sprintf('Loading custom module config from path: %s', $customModulePath));
        $modulePaths = array_merge(
            glob($coreModulePath),
            glob($customModulePath),
        );

        // Load the module config for each module path
        foreach ($modulePaths as $routePath) {
            require_once $routePath;
            // Get a class name out of this path
            $path  = preg_replace('/^' . preg_quote(SITE_root . '/conf/modules/', '/') . '/', '', $routePath);
            $path  = preg_replace('/\\.php$/', '', $path);
            $path  = preg_replace('/\\//', '\\', $path);
            $class = 'Config\\Modules\\' . $path;

            $this->logger->debug(sprintf('Loading module config from file: %s', $routePath));
            if (class_exists($class, false)) {
                /** @var ModuleConfigInterface $obj */
                $obj = new $class();
                $obj->configure($this->di);
            }
        }
        $this->logger->info('Module configs loaded; loading routes from modules');

        // All modules have been registered: now build their routes
        foreach (Registry::getRegistrations() as $basename => $registration) {
            $this->logger->debug(sprintf('Loading routes from module: %s (%s)', $basename, $registration['class']));
            Registry::buildModuleFromRegistration($this->di, $basename);
        }
    }
}
