<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DependencySummary;

use Alaress\Web\Controller\Base;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
use Schoolbox\Application\WebRequest\Kernel;

/**
 * A controller for viewing a list of services defined within Schoolbox's
 * dependency injection container.
 */
class Controller extends Base
{
    /**
     * GET action for viewing list of DI services
     *
     * @api   GET /adminv2/dependencies{/kernel}
     * @since 24.0
     *
     * @param string|null $kernel Which kernel to show dependencies of?
     *                            One of: web (default), basic, cli, daemon
     */
    public function getDependencyList(?string $kernel)
    {
        $container = (new ContainerBuilder())->newInstance();
        match ($kernel) {
            'daemon' => \Schoolbox\Application\Daemon\Kernel::installDependencies($container),
            'basic'  => \Schoolbox\Application\BasicRequest\Kernel::installDependencies($container),
            'cli'    => \Schoolbox\Application\CliTool\Kernel::installDependencies($container),
            default  => Kernel::installDependencies($container),
        };
        // Install route generator manually:
        // the new router knows not about the routes already set up
        $container->set('router', $this->getDi()->get('router'));


        $dependencyNames = $container->getServices();
        natcasesort($dependencyNames);

        $dependencies = [];
        foreach ($dependencyNames as $dependencyName) {
            $dependency     = $container->get($dependencyName);
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
