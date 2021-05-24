<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Event;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ReflectionFailure;
use Schoolbox\Di\Configurator;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\ScheduleException;
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
     * @throws ReflectionFailure
     */
    public function __construct(NotifierInflector $inflector, TriggerFactoryFactory $factory)
    {
        $this->factory = $factory;

        $this->inflector = $inflector;

        // pre-set these configurations
        $this->inflector->configureClass(AbstractModuleNotifier::class);
    }


    /**
     * @param CrudEventInterface $event
     * @return TriggerInterface[]
     * @throws ReflectionFailure
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
