<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DependencySummary;

use Alaress\Web\Controller\Base;
use Aura\Di\ContainerBuilder;
use Schoolbox\Application;

/**
 * A controller for viewing a list of services defined within Schoolbox's
 * dependency injection container.
 */
class Controller extends Base
{
    private const KERNEL_DAEMON    = 'daemon';
    private const KERNEL_BASIC_WEB = 'basic';
    private const KERNEL_CLI       = 'cli';
    private const KERNEL_WEB       = 'web';
    private const KERNELS = [
        self::KERNEL_DAEMON,
        self::KERNEL_BASIC_WEB,
        self::KERNEL_CLI,
        self::KERNEL_WEB,
    ];

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
        if ($kernel === null) {
            $kernel = self::KERNEL_WEB;
        }
        if (!in_array($kernel, self::KERNELS, true)) {
            throw new Application\Exception\NotFound();
        }

        // Build a new container
        $container = (new ContainerBuilder())->newInstance();
        match ($kernel) {
            'daemon' => Application\Daemon\Kernel::installDependencies($container),
            'basic'  => Application\BasicRequest\Kernel::installDependencies($container),
            'cli'    => Application\CliTool\Kernel::installDependencies($container),
            default  => Application\WebRequest\Kernel::installDependencies($container),
        };

        // Install router manually:
        // the new container's router knows not about the routes already set up
        $container->set('router', $this->getRouter());

        // And build the list of dependencies
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
                'kernels'      => [
                    [
                        'name' => self::KERNEL_WEB,
                        'label' => 'Web Request (Standard)',
                    ],
                    [
                        'name' => self::KERNEL_BASIC_WEB,
                        'label' => 'Web Request (Basic)',
                    ],
                    [
                        'name' => self::KERNEL_CLI,
                        'label' => 'Command Line (Tool)',
                    ],
                    [
                        'name' => self::KERNEL_DAEMON,
                        'label' => 'Command Line (Daemon)',
                    ],
                ],
                'currentKernel' => $kernel,
            ],
            'Dependencies'
        );
    }
}
