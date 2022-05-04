<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Calendar\Event\Attendance\Module;

class EventAttendance implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'calendarEventAttendance';

        $module = new Module($di);
        $module->registerModule('/calendar/event/attendance');
    }
}
