<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Calendar\Event\Attendance\Module;

class EventAttendance implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $namespace = 'calendarEventAttendance';

        $module = new Module($kernel->getDependencyInjector());
        $module->init('/calendar/event/attendance');
    }
}
