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

use Aura\Router\Route;
use Aura\Router\Router;
use Config\Modules\ModuleConfigInterface;
use Schoolbox\Admin\RouteSummary\Module;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

// Initialise module configuration
require_once SITE_root . '/conf/modules/ModuleConfigInterface.php';
require_once SITE_root . '/conf/ModuleLoader.php';

// Intentionally does not run within the kernel?

$kernel = new CliTool\Kernel(
    new class extends CliTool\App {
        public function run(): void
        {
            $modulePaths = array_merge(
                glob(SITE_root . '/conf/modules/*.php'),
                glob(SITE_root . '/conf/modules/Custom/*/*.php')
            );

            $firstError = null;
            foreach ($modulePaths as $routePath) {
                require_once $routePath;

                // Get a route name out of this path
                $path = preg_replace('/^' . preg_quote(SITE_root . '/conf/modules/', '/') . '/', '', $routePath);
                $path = preg_replace('/\\.php$/', '', $path);
                $path = preg_replace('/\\//', '\\', $path);

                $class = 'Config\\Modules\\' . $path;

                if (class_exists($class, false)) {
                    /** @var ModuleConfigInterface $obj */
                    try {
                        $obj = new $class();
                        $obj->configure($this->getDi());

                    } catch (\Throwable $e) {
                        $firstError = $firstError ?? $e;
                    }
                }
            }

            $routeCatalog = [];

            // All routes are now loaded: interrogate them
            // (How do I figure if a route is custom?)
            /** @var Router $router */
            $router = $this->getDi()->get('router');
            foreach ($router->getRoutes() as $route) {
                /** @var Route $route */
                preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);
                $module = Module::retrieveInstalledModule($parsedName['controller']);
                $routeCatalog[$route->name] = [
                    'module'     => get_class($module),
                    'controller' => $parsedName['controller'],
                    'action'     => $parsedName['action'],
                    'path'       => $route->path,
                ];
            }

            var_export($routeCatalog);
        }
    }
);
$kernel->runApp();
