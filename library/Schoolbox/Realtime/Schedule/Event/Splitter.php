<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Event;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ReflectionFailure;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
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

    /** @var IConfigLoader */
    private $conf;

    /** @var ContainerInterface */
    private $di;

    /** @var TriggerFactoryFactory */
    private $factory;


    /**
     * @param IConfigLoader         $conf
     * @param ContainerInterface    $di
     * @param TriggerFactoryFactory $factory
     */
    public function __construct(IConfigLoader $conf, ContainerInterface $di, TriggerFactoryFactory $factory)
    {
        $this->conf    = $conf;
        $this->di      = $di;
        $this->factory = $factory;
    }


    /**
     * Return the module to be working with, based on the model class (with additional safety checks)
     *
     * @param CrudEventInterface $event
     *   Currently there are two strategies for finding the notifier class:
     *   * Add "Notifier" to the name of the model class
     *   (so "\\Schoolbox\\User" becomes "\\Schoolbox\\UserNotifier")
     *   * Replace last word in namespace with "Notifier"
     *   (so "\\Schoolbox\\User\\Model" becomes "\\Schoolbox\\User\\Notifier")
     *
     * @throws ScheduleException
     * @return ModuleNotifierInterface
     */
    private function getModule(CrudEventInterface $event)
    {
        // setup for ifs
        $modelClass = get_class($event->getObject());
        $pregClass  = preg_replace('/([_\\\\])[^_\\\\]+$/', '$1Notifier', $modelClass);

        if ($event->getMapper() instanceof Mapper && $event->getMapper()->getModuleNotifier()) {
            // first try: does the event have a mapper, and if so, does it have a modulenotifier declared?
            $classname = $event->getMapper()->getModuleNotifier();

        } elseif (class_exists($modelClass . 'Notifier')) {
            // next, try for '\Schoolbox\User' => '\Schoolbox\UserNotifier'
            $classname = $modelClass . 'Notifier';

        } elseif (class_exists($pregClass)) {
            // then, try for '\Schoolbox\User' => '\Schoolbox\Notifier'
            $classname = $pregClass;

        } else {
            return null;
        }

        try {
            return $this->newInstance($classname);

        } catch (ReflectionFailure $e) {
            // cast reflection failure to schedule exception
            throw new ScheduleException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Instantiate ModuleNotifier via DI
     *
     * @param string $classname
     * @throws ReflectionFailure
     * @return ModuleNotifierInterface
     */
    private function newInstance($classname)
    {
        $instance = $this->di->newInstance($classname, [ 'configLoader' => $this->conf ]);

        if (!$instance instanceof ModuleNotifierInterface) {
            throw new ReflectionFailure("Notifer class '$classname' must be instance of ModuleNotifierInterface.");
        }

        return $instance;
    }


    /**
     * @param CrudEventInterface $event
     * @return TriggerInterface[]
     */
    public function generateTriggers(CrudEventInterface $event)
    {
        $eventModule = $this->getModule($event);

        if($eventModule != null) {
            return $this->factory
                ->spawn($eventModule, $event)
                ->buildTriggers($event->getAction())
                ->getTriggers();
        } else {
            // There is no notifier, which is fine
            return [];
        }
    }

}