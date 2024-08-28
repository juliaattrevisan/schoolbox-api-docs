<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IBrokerException;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\SignalEmitter;
use Alaress\Date\IClock;
use Alaress\Scheduler\Cache\ICache;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Schoolbox\External;

abstract class AbstractScheduler extends SignalEmitter implements TaskResponseHandlerInterface
{
    /** @var array */
    private array $timers;

    /**
     * Redis storage keys for future triggers
     * This is referred when deleting canceled trigger from RedisCache
     * @var array
     */
    private array $redisKeys;

    /** @var DispatcherInterface */
    protected DispatcherInterface $dispatcher;

    /** @var int Retry time in seconds to reschedule */
    private const RETRY_TIME = 300;

    /**
     * JIT handler to take care of unexpected shutdown
     *
     * @var ICache|null
     */
    protected ?ICache $jit = null;

    /**
     * @var string|null Instance id
     */
    protected ?string $instanceId = null;

    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     */
    public function __construct(
        private LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        protected LoggerInterface $log,
        private IClock $clock
    ) {
        parent::__construct($loop);

        $this->dispatcher = new Dispatcher($loop, $this, $log, $dispatchStrategy); // TODO IoC
    }


    /**
     * Callback executed when an exception bubbles to the top of the loop
     *
     * @param \Exception $e
     * @throws PDOException
     * @throws External\Exception
     * @return void
     */
    public function onException(\Exception $e)
    {
        if ($e instanceof IBrokerException) {
            $msg = $e->getMessage();
            if ($e->getPrevious()) {
                $msg .= " - {$e->getPrevious()->getMessage()}";
            }

            $this->log->warning("Could not schedule: {$msg}. Dropping message.");

            try {
                // drop the message
                $e->getResolver()->acknowledge();
            } catch (DuplicateResolutionException $e) {
                // do nothing, this message was just double broken.
            }
            return;
        }

        $this->log->critical(sprintf('%s in %s:%s: "%s"', get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()), ['exception' => $e]);
        // we need the daemon to crash if there's a database issue
        if ($e instanceof PDOException || $e instanceof External\Exception) {
            throw $e;
        }
    }


    /**
     * @param ICache $jit
     * @return static
     */
    public function setJitCache(ICache $jit): self
    {
        $this->jit = $jit;
        return $this;
    }

    /**
     * Schedule a trigger for deferred execution
     *
     * @param TriggerInterface $trigger
     * @param IResolver|null   $resolver
     * @return void
     */
    protected function scheduleNotification(TriggerInterface $trigger, ?IResolver $resolver): void
    {
        if ($trigger instanceof CancelTrigger) { // FIREFIGHT
            $this->unschedule($trigger);
            $resolver?->acknowledge();
            return;
        }

        if ($trigger->getTimestamp() < 0) {
            $this->log->info("[AbstractScheduler] Trigger with negative timestamp received: {$trigger->getEventObjectId()} {$trigger->getLabel()}"); // FIREFIGHT
            $this->unschedule($trigger);
            $resolver?->acknowledge();
            return;
        }

        $interval = $trigger->getTimestamp() - $this->clock->time();

        if ($interval <= 0) {
            $this->log->info("[AbstractScheduler] Immediately dispatching trigger: {$trigger->getEventObjectId()} {$trigger->getLabel()}");
            $previousTriggerExisted = $this->unschedule($trigger);
            $this->log->debug($previousTriggerExisted ? 'Cleared previous trigger' : 'No previous trigger was set', ['char' => '-']);

            $this->dispatchTrigger($trigger);
            $resolver?->acknowledge();
            return;
        }

        $this->schedule($trigger, $this->timerCallback($trigger));
        $resolver?->acknowledge();
    }


    /**
     * @param TriggerInterface $trigger
     * @return callable
     */
    protected function timerCallback(TriggerInterface $trigger): callable
    {
        return function () use ($trigger): void {
            $this->dispatchTrigger($trigger);
        };
    }


    /**
     * @param TriggerInterface $trigger
     */
    protected function dispatchTrigger(TriggerInterface $trigger): void
    {
        $event = $trigger->getEvent();
        foreach ($trigger->getCallbacks() as $callback) {
            $this->dispatcher->enqueue(
                $trigger,
                $this->getMethod(),
                serialize(
                    [
                        $callback,
                        $event->getObject(),
                        $event->getEffector(),
                        $event->getDiff(),
                    ]
                )
            );
            // remove from backup
            if ($this->jit) {
                [$itemKey,] = RedisQueue::getStorage($trigger, $this->instanceId);
                $this->jit->removeCache($itemKey);
            }
        }
        if (count($trigger->getCallbacks()) === 0) {
            $this->log->warning("Trigger for '{$trigger->getEventObjectId()} {$trigger->getLabel()}' has no callbacks");
        }
    }


    /**
     * Schedules a trigger for deferred execution. Returns success or failure.
     *
     * @param TriggerInterface $trigger
     * @param callable         $callable
     * @param bool             $cancelPrevious
     * @param null|int         $interval in seconds
     * @return bool
     */
    protected function schedule(TriggerInterface $trigger, callable $callable, bool $cancelPrevious = true, ?int $interval = null): bool
    {
        // can't schedule a cancel trigger
        if ($trigger instanceof CancelTrigger) {
            $this->log->debug('[AbstractScheduler] Cannot schedule CancelTrigger');
            return false;
        }

        // firstly, remove any existing timer for this trigger
        if ($cancelPrevious) {
            $this->unschedule($trigger);
        }

        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();

        $interval = $interval ?: ($trigger->getTimestamp() - $this->clock->time());
        $timer    = $this->loop->addTimer($interval, $callable);
        $this->putTimer($model, $label, $timer, $trigger);

        $this->log->info(
            sprintf(
                "[AbstractScheduler] Scheduling trigger '%s:%s' for %s in %s seconds",
                $trigger->getEventObjectId(),
                $trigger->getLabel(),
                date('Y-m-d H:i:s', $trigger->getTimestamp()),
                $interval
            ),
            ['char' => '+']
        );

        return true;
    }


    /**
     * Unschedules a trigger that was previously set for deferred execution.
     *
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function unschedule(TriggerInterface $trigger): bool
    {
        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();

        if ($this->hasTimer($model, $label)) {
            $timer = $this->getTimer($model, $label);
            $this->log->info("[AbstractScheduler] Canceling existing timer for '{$trigger->getEventObjectId()} - {$trigger->getLabel()}'", ['char' => '-']);

            if ($timer instanceof TimerInterface) {
                $this->loop->cancelTimer($timer);
            } else {
                $this->log->debug("[AbstractScheduler] Timer is not set properly for '{$trigger->getEventObjectId()} - {$trigger->getLabel()}'", ['char' => '-']);
            }
            $this->removeTimer($model, $label);

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
     * @param object           $model
     * @param string           $label
     * @param TimerInterface   $timer
     * @param TriggerInterface $trigger
     */
    public function putTimer(object $model, string $label, TimerInterface $timer, TriggerInterface $trigger): void
    {
        $class = get_class($model);
        $hash  = $this->hashObject($model);

        $this->timers[$class][$label][$hash] = $timer;

        // Store scheduled trigger key, so that it can be referred when canceled
        if ($this->instanceId && $this->jit) {
            [$storageKey,] = RedisQueue::getStorage($trigger, $this->instanceId);
            $this->redisKeys[$class][$label][$hash] = $storageKey;
            $this->jit->storeCache($trigger);
        }
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
    public function removeTimer(object $model, string $label): void
    {
        $class = get_class($model);
        $hash  = $this->hashObject($model);
        unset($this->timers[$class][$label][$hash]);
        // Removed scheduled trigger from RedisCache
        if ($this->jit) {
            $storageKey = $this->redisKeys[$class][$label][$hash];
            $this->jit->removeCache($storageKey);
        }
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
        $this->log->warning("[AbstractScheduler] Received worker error for '$command'");

        // If this is handling ExternalException from a worker, most likely that this is caused by
        // SIS connector outage. Hence, rescheduling it.
        if ($identifier instanceof TriggerInterface && $command === DefaultDispatchStrategy::TASK_PUBLISH) {
            $this->log->warning("[AbstractScheduler] Rescheduling '{$identifier->getEventObjectId()} - {$identifier->getLabel()}' in " . self::RETRY_TIME, ['char' => '-']);
            $this->schedule($identifier, $this->timerCallback($identifier), true, self::RETRY_TIME);
        }
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
    abstract protected function getMethod();
}
