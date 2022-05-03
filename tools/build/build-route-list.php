#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Build a list of all routes, and their corresponding module/path/etc.
 */

use Alaress\Module\Registry;
use Alaress\Web\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Config\Modules\ModuleConfigInterface;
use Predis\Client;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

// Initialise module configuration
require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
require_once SITE_root . '/conf/ModuleLoader.php';

$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {
        /**
         * @inheritdoc
         */
        public function run(): void
        {
            /** @var Router $router */
            $router = $this->getDi()->get('router');

            // Before this runs: clear the route and module registrations,
            // so that new registration tables may be built
            $router->setRoutes(new RouteCollection(new RouteFactory()));
            Registry::setRegistrations([]);

            $this->loadAllModules();

            // The new route and module registrations have been loaded:
            // dump their registration tables into Redis
            /** @var Client $redis */
            $redis = $this->getDi()->get('redis.storage');
            $this->getLogger()->info('Writing route and module configs to Redis cache');
            $redis->set(RouteCollection::MODULE_CACHE_KEY_PREFIX . INSTANCE_ID, serialize($router->getRoutes()));
            $redis->set(Registry::MODULE_CACHE_KEY_PREFIX . INSTANCE_ID, json_encode(Registry::getRegistrations()));
        }

        /**
         * Load all module configs which are relevant for this instance, then
         * loads each of its modules and performs its init steps.
         *
         * This builds the route and module tables, ready to be cached.
         *
         * @return void
         */
        private function loadAllModules(): void
        {
            $siteSchoolCode   = ucfirst(strtolower($this->getConfig()->get('site_school_code')));
            $coreModulePath   = SITE_root . '/conf/modules/*.php';
            $customModulePath = SITE_root . sprintf('/conf/modules/Custom/%s/*.php', $siteSchoolCode);
            $this->getLogger()->info(sprintf('Loading core module config from path: %s', $coreModulePath));
            $this->getLogger()->info(sprintf('Loading custom module config from path: %s', $customModulePath));
            $modulePaths    = array_merge(
                glob($coreModulePath),
                glob($customModulePath),
            );

            foreach ($modulePaths as $routePath) {
                require_once $routePath;
                $this->getLogger()->debug(sprintf('Loading module config: %s', $routePath));

                // Get a route name out of this path
                $path = preg_replace('/^' . preg_quote(SITE_root . '/conf/modules/', '/') . '/', '', $routePath);
                $path = preg_replace('/\\.php$/', '', $path);
                $path = preg_replace('/\\//', '\\', $path);

                $class = 'Config\\Modules\\' . $path;

                $this->getLogger()->debug(sprintf('Loading module config:  modules from config: %s', $class));
                if (class_exists($class, false)) {
                    /** @var ModuleConfigInterface $obj */
                    $obj = new $class();
                    $obj->configure($this->getDi());
                }
            }
            $this->getLogger()->info('Module configs loaded; loading routes from modules');

            // All modules have been registered: now build their routes
            foreach (Registry::getRegistrations() as $basename => $registration) {
                $this->getLogger()->debug(sprintf('Loading routes from module: %s (%s)', $basename, $registration['class']));
                Registry::buildModuleFromRegistration($this->getDi(), $basename);
            }
        }
    },
    [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO],
);
$kernel->runApp();
