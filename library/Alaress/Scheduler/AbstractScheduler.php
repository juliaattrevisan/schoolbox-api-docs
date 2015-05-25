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
use Alaress\Date\Clock;
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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

abstract class AbstractScheduler extends SignalEmitter implements SchedulerInterface, TaskResponseHandlerInterface
{

    /** @var LoopInterface */
    protected $loop;

    /** @var LoggerInterface */
    public $log;

    /** @var array */
    protected $timers;

    /** @var DispatcherInterface */
    protected $dispatcher;

    /** @var IClock */
    protected $clock;

    // TODO release this dependency on the Schoolbox domain
    /** @var \Schoolbox\IConfigLoader */
    protected $conf;

    /** @var string */
    protected $method = '';


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IConfigLoader             $conf
     * @param IClock                    $clock
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IConfigLoader $conf = null,
        IClock $clock = null
    ) {
        parent::__construct($loop);

        $this->loop       = $loop;
        $this->log        = $log;
        $this->dispatcher = new Dispatcher($loop, $this, $log, $dispatchStrategy);
        $this->conf       = $conf ? : ConfigMapper::getInstance();
        $this->clock      = $clock ?: new Clock();
    }


    /**
     * Convenience method to schedule an array of triggers
     *
     * @param TriggerInterface[] $triggers
     */
    public function scheduleNotifications($triggers)
    {
        foreach ($triggers as $trigger) {
            $this->scheduleNotification($trigger);
        }
    }


    /**
     * Schedule a trigger for deferred execution
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    public function scheduleNotification($trigger)
    {
        if ($trigger instanceof CancelTrigger) { // FIREFIGHT
            $this->log->info("Unscheduling CancelTrigger"); // FIREFIGHT
            $this->unschedule($trigger);
            return;
        }
        $interval = $trigger->getTimestamp() - $this->clock->time();

        if ($interval <= self::IMMEDIATELY) {
            $this->log->info("Immediately dispatching trigger");
            $this->dispatchTrigger($trigger);
            return;
        }

        $self = $this;

        $this->schedule(
             $trigger,
             function ($timer) use ($self) {
                 /** @var TimerInterface $timer */
                 if (!$timer->getData() instanceof TriggerInterface) {
                     throw new InvalidArgumentException("Trigger must be provided to publish data");
                 }
                 $self->dispatchTrigger($timer->getData());
             }
        );
    }


    /**
     * @return DispatcherInterface
     */
    public function getDispatcher()
    {
        if (isset($this->dispatcher)) {
            return $this->dispatcher;
        }
        return null;
    }


    /**
     * @param TriggerInterface $trigger
     */
    public function dispatchTrigger($trigger)
    {
        $event = $trigger->getEvent();
        foreach ($trigger->getCallbacks() as $callback) {
            $this->getDispatcher()->enqueue(
                $trigger,
                $this->method,
                serialize(array(
                    $callback,
                    $event->getObject(),
                    $event->getEffector(),
                    $event->getDiff()
                ))
            );
        }
        if (count($trigger->getCallbacks()) == 0) {
            $this->log->warning("Trigger for '{$trigger->getIdString()}' has no callbacks");
        }
    }


    /**
     * Schedules a trigger for deferred execution. Returns success or failure.
     *
     * @param TriggerInterface $trigger
     * @param callable         $callable
     * @return bool
     */
    protected function schedule($trigger, $callable)
    {
        // can't schedule a cancel trigger
        if ($trigger instanceof CancelTrigger) {
            $this->log->debug("Cannot schedule CancelTrigger");
            return false;
        }

        // firstly, remove any existing timer for this trigger
        $this->unschedule($trigger);

        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();

        /** @var TimerInterface $timer */
        $timer = $this->loop->addTimer($trigger->getTimestamp() - $this->clock->time(), $callable);
        $timer->setData($trigger);
        $this->putTimer($model, $label, $timer);

        $this->log->info(sprintf("Scheduling trigger '%s' for %s", $trigger->getIdString(), date('Y-m-d H:i:s', $trigger->getTimestamp())),
            array('char' => '+')
        );

        return true;
    }


    /**
     * Unschedules a trigger that was previously set for deferred execution.
     *
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function unschedule($trigger)
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
    protected function getTimer($model, $label)
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
    protected function putTimer($model, $label, $timer)
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
    protected function hasTimer($model, $label)
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
    public function hashObject($object)
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

} 