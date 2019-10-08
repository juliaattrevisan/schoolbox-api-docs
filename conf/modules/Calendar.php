<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Calendar\Module;

class Calendar extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $this->loadModule($kernel, Module::class, "/calendar");
        $this->loadModule($kernel, \Schoolbox\Calendar\Event\Module::class, "/calendar/event");
    }
}
