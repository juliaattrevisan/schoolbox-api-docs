<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;

class Geosnapshot extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, \Schoolbox\Geosnapshot\Component\Dashboard\Module::class, "/geosnapshot/component/dashboard");
        $this->loadModule($di, \Schoolbox\Geosnapshot\Component\Homepage\Module::class, "/geosnapshot/component/homepage");
        $this->loadModule($di, \Schoolbox\Geosnapshot\Module::class, "/geosnapshot");
    }
}
