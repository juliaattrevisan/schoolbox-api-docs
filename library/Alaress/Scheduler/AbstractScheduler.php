<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Cli\Debug;
use Alaress\Schedule\Trigger\CancelTrigger;
use Alaress\Schedule\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

abstract class AbstractScheduler implements SchedulerInterface, TaskResponseHandlerInterface {

    /** @var LoopInterface */
    protected $loop;

    /** @var Debug */
    public $debug;

    /** @var array */
    protected $timers;

    /** @var DispatcherInterface */
    protected $dispatcher;

    /** @var \Schoolbox\IConfigLoader */
    protected $conf;

    /** @var string */
    protected $method = '';


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param \Alaress\Cli\Debug $cli
     */
    public function __construct($loop, $dispatchStrategy, $cli) {
        $this->loop = $loop;
        $this->debug = $cli;
        $this->dispatcher = new Dispatcher($loop, $this, $cli, $dispatchStrategy);
    }


    /**
     * Convenience method to schedule an array of triggers
     *
     * @param TriggerInterface[] $triggers
     */
    public function scheduleNotifications($triggers) {
        foreach($triggers as $trigger) {
            $this->scheduleNotification($trigger);
        }
    }


    /**
     * Schedule a trigger for deferred execution
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    public function scheduleNotification($trigger) {
        if($trigger instanceof CancelTrigger) {
            $this->unschedule($trigger);
            return;
        }
        $interval = $trigger->getTimestamp() - time();

        if($interval <= self::IMMEDIATELY) {
            $this->dispatchTrigger($trigger);
            return;
        }

        $self = $this;

        $this->schedule($trigger, function($timer) use ($self) {
            /** @var TimerInterface $timer */
            if(!$timer->getData() instanceof TriggerInterface) {
                throw new InvalidArgumentException("Trigger must be provided to publish data");
            }
            $self->dispatchTrigger($timer->getData());
        });
    }


    /**
     * @return DispatcherInterface
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }


    /**
     * @param TriggerInterface $trigger
     */
    public function dispatchTrigger($trigger) {
        foreach($trigger->getCallbacks() as $callback) {
            $this->getDispatcher()->enqueue($trigger, $this->method, serialize(array(
                $callback, $trigger->getModel(), $trigger->getUser(), $trigger->getDiff()
            )));
        }
    }


    /**
     * Schedules a trigger for deferred execution. Returns success or failure.
     *
     * @param TriggerInterface $trigger
     * @param callable $callable
     * @return bool
     */
    protected function schedule($trigger, $callable) {
        if($trigger instanceof CancelTrigger) {
            return false;
        }
        // firstly, remove any existing timer for this trigger
        $this->unschedule($trigger);

        $model = $trigger->getModel();
        $module = get_class($trigger->getModuleNotifier());
        $label = $trigger->getLabel();

        /** @var TimerInterface $timer */
        $timer = $this->loop->addTimer($trigger->getTimestamp() - time(), $callable);
        $timer->setData($trigger);
        $this->putTimer($module, $label, $model, $timer);

        $this->debug->v("Scheduling trigger '$module::$label'", '+');

        return true;
    }


    /**
     * Unschedules a trigger that was previously set for deferred execution.
     *
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function unschedule($trigger) {
        $model = $trigger->getModel();
        $module = get_class($trigger->getModuleNotifier());
        $label = $trigger->getLabel();

        if($this->hasTimer($module, $label, $model)) {
            $timer = $this->getTimer($module, $label, $model);
            $this->debug->v("Unscheduling trigger '$module::$label'", '-');
            $this->loop->cancelTimer($timer);
            return true;
        }
        return false;

    }


    /**
     * @param string $module
     * @param string $label
     * @param object $model
     * @return TimerInterface|null
     */
    protected function getTimer($module, $label, $model) {
        return $this->hasTimer($module, $label, $model)
            ? $this->timers[$module][$label][$this->hashObject($model)]
            : null;
    }


    /**
     * @param string $module
     * @param string $label
     * @param object $model
     * @param TimerInterface $timer
     */
    protected function putTimer($module, $label, $model, $timer) {
        $hash = $this->hashObject($model);
        $this->timers[$module][$label][$hash] = $timer;
    }


    /**
     * @param string $module
     * @param string $label
     * @param object $model
     * @return bool
     */
    protected function hasTimer($module, $label, $model) {
        $hash = $this->hashObject($model);
        return isset($this->timers[$module][$label][$hash]);
    }


    /**
     * @param string $module
     * @param string $label
     * @param object $model
     */
    public function removeTimer($module, $label, $model) {
        $hash = $this->hashObject($model);
        unset($this->timers[$module][$label][$hash]);
    }


    /**
     * Returns a hash of the given object
     *
     * @param object $object
     * @return string
     */
    public function hashObject($object) {
        return sha1(json_encode($object));
    }

} 