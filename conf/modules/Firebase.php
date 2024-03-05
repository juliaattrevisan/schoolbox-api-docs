<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;

/**
 * Firebase module loader.
 */
class Firebase extends ModuleLoader
{
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, \Schoolbox\Application\Firebase\ServiceWorker\Module::class, '/');
    }
}
