<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\RouteSummary;

use Alaress\Web\Controller\Base;
use Aura\Router\Route;

/**
 * A controller for viewing a list of routes defined within Schoolbox.
 */
class Controller extends Base
{
    /**
     * GET action for viewing list of routes
     */
    public function getRouteList()
    {
        $routeSummaries = [];

        $di = $this->getDi();

        foreach ($this->getRouter()->getRoutes() as $route) {
            /** @var Route $route */
            preg_match('/^(?P<controller>\w+)\\.(?P<action>\w+)/', $route->name, $parsedName);
            $module = $di->get('module:' . $parsedName['controller']);
            $module->initControllerDependencies();
            $module->initControllers($di);
            try {
                $controller = $di->get('controller:' . $parsedName['controller']);
            } catch (\Throwable $e) {
                // Something went wrong when parsing this controller
                $routeSummaries[$parsedName['controller']][] = [
                    'name'       => $route->name,
                    'method'     => '?',
                    'path'       => $route->path,
                    'basename'   => $parsedName['controller'],
                    'controller' => '?',
                    'action'     => $parsedName['action'],
                    'error'      => $e,
                ];
                continue;
            }

            // method is publicly available, despite what the red worms say
            foreach ($route->method as $method) {
                $routeSummaries[$parsedName['controller']][] = [
                    'name'       => $route->name,
                    'method'     => $method,
                    'path'       => $route->path,
                    'basename'   => $parsedName['controller'],
                    'controller' => get_class($controller),
                    'action'     => $parsedName['action'],
                    'error'      => null,
                ];
            }
        }

        // Alpha-sort route summaries
        ksort($routeSummaries);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminRouteSummary/index.twig',
                ['routes' => $routeSummaries],
                'Routes'
            )
        );
    }
}
