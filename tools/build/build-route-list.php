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

use Alaress\Module\Module as BaseModule;
use Alaress\Module\Registry;
use Alaress\Web\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Config\Modules\ModuleConfigInterface;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

// Initialise module configuration
require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
require_once SITE_root . '/conf/ModuleLoader.php';


$kernel = new CliTool\Kernel(
    new class extends CliTool\App {

        private const ROUTE_CACHE_FILE = SITE_root . '/conf/routeCache';

        private const MODULE_CACHE_FILE = SITE_root . '/conf/moduleCache';

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
            // dump their registration tables
            file_put_contents(self::ROUTE_CACHE_FILE, serialize($router->getRoutes()));
            file_put_contents(self::MODULE_CACHE_FILE, json_encode(Registry::getRegistrations()));
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
            $siteSchoolCode = $this->getConfig()->get('site_school_code');
            $modulePaths    = array_merge(
                glob(SITE_root . '/conf/modules/*.php'),
                glob(SITE_root . sprintf('/conf/modules/Custom/%s/*.php', $siteSchoolCode)),
            );

            foreach ($modulePaths as $routePath) {
                require_once $routePath;

                // Get a route name out of this path
                $path = preg_replace('/^' . preg_quote(SITE_root . '/conf/modules/', '/') . '/', '', $routePath);
                $path = preg_replace('/\\.php$/', '', $path);
                $path = preg_replace('/\\//', '\\', $path);

                $class = 'Config\\Modules\\' . $path;

                if (class_exists($class, false)) {
                    /** @var ModuleConfigInterface $obj */
                    $obj = new $class();
                    $obj->configure($this->getDi());
                }
            }

            // All modules have been registered: now init them
            foreach (Registry::getRegistrations() as $basename => $registration) {
                Registry::buildModuleFromRegistration($this->getDi(), $basename)->init($registration['basePath']);
            }
        }
    }
);
$kernel->runApp();
