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
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactoryFactory;

/**
 * Class responsible for splitting a CRUD event into an array of triggers, ready for scheduling.
 *
 * @package Schoolbox\Realtime\Schedule
 */
class Splitter
{
    /** @var TriggerFactoryFactory */
    private $factory;

    /** @var NotifierInflector */
    private $inflector;


    /**
     * @param NotifierInflector     $inflector
     * @param TriggerFactoryFactory $factory
     */
    public function __construct(NotifierInflector $inflector, TriggerFactoryFactory $factory)
    {
        $this->factory  = $factory;
        $this->inflector = $inflector;
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
            return $this->factory
                ->spawn($eventModule, $event)
                ->buildTriggers($event->getAction())
                ->getTriggers();
        }

        // There is no notifier, which is fine
        return [];
    }
}
