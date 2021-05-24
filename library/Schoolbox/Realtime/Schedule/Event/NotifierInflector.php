<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Event;

use Aura\Di\Exception\ReflectionFailure;
use Schoolbox\Di\Configurator;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;

class NotifierInflector
{
    /** @var Configurator */
    private $configurator;


    /**
     * @param Configurator $configurator
     */
    public function __construct(Configurator $configurator)
    {
        $this->configurator = $configurator;
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
     * @throws ReflectionFailure
     * @return ModuleNotifierInterface
     */
    public function getModule(CrudEventInterface $event): ?ModuleNotifierInterface
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

        return $this->newInstance($classname);
    }


    /**
     * Passthru to the configurator beneath
     *
     * @param string $classname
     * @throws ReflectionFailure
     */
    public function configureClass(string $classname): void
    {
        $this->configurator->configureClass($classname);
    }


    /**
     * Instantiate ModuleNotifier via DI
     *
     * @param string $classname
     * @throws ReflectionFailure
     * @return ModuleNotifierInterface
     */
    private function newInstance(string $classname): ModuleNotifierInterface
    {
        try {
            $this->configurator->configureClass($classname);
        } catch (ReflectionFailure $e) {
            // noop (for now, this is ok)
        }

        try {
            /** @var ModuleNotifierInterface $notifier */
            $notifier = $this->configurator->newInstance($classname);
        } catch (\Aura\Di\Exception $e) {
            throw new ReflectionFailure($e->getMessage(), $e->getCode(), $e);
        }

        return $notifier;
    }
}
