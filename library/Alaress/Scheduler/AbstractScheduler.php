<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Async\SignalEmitter;
use Alaress\Date\IClock;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;


abstract class AbstractScheduler extends SignalEmitter implements TaskResponseHandlerInterface
{

    /** @var LoggerInterface */
    protected $log;

    /** @var LoopInterface */
    private $loop;

    /** @var array */
    private $timers;

    /** @var DispatcherInterface */
    private $dispatcher;

    /** @var IClock */
    private $clock;


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock
    ) {
        parent::__construct($loop);

        $this->loop       = $loop;
        $this->log        = $log;
        $this->dispatcher = new Dispatcher($loop, $this, $log, $dispatchStrategy); // TODO IoC
        $this->clock      = $clock;
    }


    /**
     * Convenience method to schedule an array of triggers
     *
     * @param TriggerInterface[] $triggers
     * @throws InvalidArgumentException
     */
    protected function scheduleNotifications(array $triggers)
    {
        foreach ($triggers as $trigger) {
            $this->scheduleNotification($trigger);
        }
    }


    /**
     * Schedule a trigger for deferred execution
     *
     * @param TriggerInterface $trigger
     * @throws InvalidArgumentException
     * @return void
     */
    protected function scheduleNotification(TriggerInterface $trigger)
    {
        if ($trigger instanceof CancelTrigger) { // FIREFIGHT
            $this->log->info('Unscheduling CancelTrigger'); // FIREFIGHT
            $this->unschedule($trigger);
            return;
        }

        $interval = $trigger->getTimestamp() - $this->clock->time();

        if ($interval <= 0) {
            $this->log->info('Immediately dispatching trigger');
            $previousTriggerExisted = $this->unschedule($trigger);
            $this->log->debug($previousTriggerExisted ? 'Cleared previous trigger' : 'No previous trigger was set', [ 'char' => '-' ]);

            $this->dispatchTrigger($trigger);

            return;
        }

        $this->schedule($trigger, $this->timerCallback());
    }


    /**
     * @throws InvalidArgumentException
     * @return callable
     */
    protected function timerCallback()
    {
        return function (TimerInterface $timer) {
            if (!$timer->getData() instanceof TriggerInterface) {
                throw new InvalidArgumentException('Trigger must be provided to publish data');
            }
            $this->dispatchTrigger($timer->getData());
        };
    }


    /**
     * @return DispatcherInterface
     */
    public function getDispatcher()
    {
        return !isset($this->dispatcher) ? null : $this->dispatcher;
    }


    /**
     * @param TriggerInterface $trigger
     */
    protected function dispatchTrigger(TriggerInterface $trigger)
    {
        $event = $trigger->getEvent();
        foreach ($trigger->getCallbacks() as $callback) {
            $this->getDispatcher()->enqueue(
                $trigger,
                $this->getMethod(),
                serialize([
                    $callback,
                    $event->getObject(),
                    $event->getEffector(),
                    $event->getDiff()
                ])
            );
        }
        if (count($trigger->getCallbacks()) === 0) {
            $this->log->warning("Trigger for '{$trigger->getIdString()}' has no callbacks");
        }
    }


    /**
     * Schedules a trigger for deferred execution. Returns success or failure.
     *
     * @param TriggerInterface $trigger
     * @param callable         $callable
     * @param bool             $cancelPrevious
     * @return bool
     */
    protected function schedule(TriggerInterface $trigger, callable $callable, $cancelPrevious = true)
    {
        // can't schedule a cancel trigger
        if ($trigger instanceof CancelTrigger) {
            $this->log->debug('Cannot schedule CancelTrigger');
            return false;
        }

        // firstly, remove any existing timer for this trigger
        if ($cancelPrevious) {
            $this->unschedule($trigger);
        }

        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();

        /** @var TimerInterface $timer */
        $timer = $this->loop->addTimer($trigger->getTimestamp() - $this->clock->time(), $callable);
        $timer->setData($trigger);
        $this->putTimer($model, $label, $timer);

        $this->log->info(sprintf("Scheduling trigger '%s' for %s", $trigger->getIdString(), date('Y-m-d H:i:s', $trigger->getTimestamp())),
            [ 'char' => '+' ]
        );

        return true;
    }


    /**
     * Unschedules a trigger that was previously set for deferred execution.
     *
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function unschedule(TriggerInterface $trigger)
    {
        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();

        if ($this->hasTimer($model, $label)) {
            $timer = $this->getTimer($model, $label);
            $this->log->info("Unscheduling trigger '{$trigger->getIdString()}'", array('char' => '-'));
            $this->loop->cancelTimer($timer);
            return true;
        }

        return false;
    }


    /**
     * @param object $model
     * @param string $label
     * @return TimerInterface|null
     */
    private function getTimer($model, $label)
    {
        $class = get_class($model);
        return $this->hasTimer($model, $label)
            ? $this->timers[$class][$label][$this->hashObject($model)]
            : null;
    }


    /**
     * @param object         $model
     * @param string         $label
     * @param TimerInterface $timer
     */
    private function putTimer($model, $label, TimerInterface $timer)
    {
        $class = get_class($model);
        $hash  = $this->hashObject($model);

        $this->timers[$class][$label][$hash] = $timer;
    }


    /**
     * @param object $model
     * @param string $label
     * @return bool
     */
    private function hasTimer($model, $label)
    {
        $class = get_class($model);
        $hash  = $this->hashObject($model);
        return isset($this->timers[$class][$label][$hash]);
    }


    /**
     * @param object $model
     * @param string $label
     */
    public function removeTimer($model, $label)
    {
        $class = get_class($model);
        $hash  = $this->hashObject($model);
        unset($this->timers[$class][$label][$hash]);
    }


    /**
     * Returns a hash of the given object
     *
     * @param object $object
     * @return string
     */
    protected function hashObject($object)
    {
        // use primary model identifier if possible.
        // something like "instanceof {Trait}" would be amazing, buuuuut this is PHP...
        if (method_exists($object, 'getId')) {
            $string = get_class($object) . ':' . $object->getId();

        } else {
            // fall back to using the SPL hash if there's no available ID
            $string = spl_object_hash($object);
        }

        return sha1($string);
    }


    /**
     * Handles an error from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleError($identifier, $command, $data)
    {
        $this->log->notice("Received worker error for '$command': $data");
    }


    /**
     * @return void
     */
    protected function stop()
    {
        $this->loop->stop();
    }


    /**
     * @return IClock
     */
    protected function getClock()
    {
        return $this->clock;
    }


    /**
     * @return string
     */
    protected abstract function getMethod();

}
