<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Event;

use Alaress\Scheduler\Trigger\TriggerInterface;
use ReflectionException;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactory;

/**
 * Class responsible for splitting a CRUD event into an array of triggers, ready for scheduling.
 *
 * @package Schoolbox\Realtime\Schedule
 */
class Splitter
{
    /**
     * @param NotifierInflector     $inflector
     */
    public function __construct(private NotifierInflector $inflector)
    {
    }


    /**
     * @param CrudEventInterface $event
     * @return TriggerInterface[]
     * @throws ReflectionException
     */
    public function generateTriggers(CrudEventInterface $event)
    {
        // notifier
        $eventModule = $this->inflector->getModule($event);

        if ($eventModule instanceof ModuleNotifierInterface) {
            return (new TriggerFactory($eventModule, $event))
                ->buildTriggers($event->getAction())
                ->getTriggers();
        }

        // There is no notifier, which is fine
        return [];
    }
}
