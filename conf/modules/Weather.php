<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;

class Weather extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, \Schoolbox\Weather\Component\Dashboard\Module::class, "/weather/component/dashboard");
        $this->loadModule($di, \Schoolbox\Weather\Module::class, "/weather");
    }
}
