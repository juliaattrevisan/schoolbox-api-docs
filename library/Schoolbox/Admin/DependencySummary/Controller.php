<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DependencySummary;

use Alaress\Web\Controller\Base;
use Aura\Di\Container;

/**
 * A controller for viewing a list of services defined within Schoolbox's
 * dependency injection container.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Container $container
     */
    public function __construct(private Container $container)
    {
    }

    /**
     * GET action for viewing list of DI services
     *
     * @api   GET /adminv2/dependencies
     * @since 24.0
     */
    public function getDependencyList()
    {
        $dependencyNames = $this->container->getServices();
        natcasesort($dependencyNames);

        $dependencies = [];
        foreach ($dependencyNames as $dependencyName) {
            $dependency     = $this->container->get($dependencyName);
            $interfaces     = class_implements($dependency);
            natcasesort($interfaces);
            $dependencies[] = [
                'name'       => $dependencyName,
                'class'      => get_class($dependency),
                'interfaces' => $interfaces,
            ];
            unset($dependency);
        }

        $currentRoute   = $this->routeGenerator->generate('adminDependencySummary.getDependencyList');
        $this->response = $this->responseFromTemplate(
            '@adminDependencySummary/dependencyList.twig',
            [
                'pageCurrent'    => $currentRoute,
                'allBreadcrumbs' => [
                    ['Administration', '/adminv2/home'],
                    ['Dependency Summary', $currentRoute],
                ],
                'dependencies' => $dependencies,
            ],
            'Dependencies'
        );
    }
}
