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
use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Schoolbox\External;
use Schoolbox\Realtime\Memory;

abstract class AbstractScheduler extends SignalEmitter implements TaskResponseHandlerInterface
{
    /**
     * A list of timers which have been scheduled for a trigger.
     *
     * @var array<string, array<string, array<string, TimerInterface>>> keyed by trigger's model class, then its label,
     *                                                                  then its model's hash
     */
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
     * @param int|null                  $maxFutureTriggerTime The max seconds in the future to trigger a notification in
     *                                                        `scheduleNotification`:
     *                                                        if the notification is further in the future than this: it
     *                                                        is ignored, and will presumably be sent later.
     */
    public function __construct(
        private LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        protected LoggerInterface $log,
        private IClock $clock,
        private ?int $maxFutureTriggerTime = null,
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

        $time     = $this->clock->time();
        $interval = $trigger->getTimestamp() - $time;

        if ($interval <= 0) {
            $this->log->info("[AbstractScheduler] Immediately dispatching trigger: {$trigger->getEventObjectId()} {$trigger->getLabel()}");
            $previousTriggerExisted = $this->unschedule($trigger);
            $this->log->debug($previousTriggerExisted ? 'Cleared previous trigger' : 'No previous trigger was set', ['char' => '-']);

            $this->dispatchTrigger($trigger);
            $resolver?->acknowledge();
            return;
        }

        // Don't schedule this trigger if it's more than a configured amount of
        // time in advance.
        if (isset($this->maxFutureTriggerTime) && $interval > $this->maxFutureTriggerTime) {
            $this->log->info(
                sprintf(
                    "[AbstractScheduler] Trigger with timestamp too far in the future, not dispatching: %s %s (timestamp: %s, max time: %s)",
                    $trigger->getEventObjectId(),
                    $trigger->getLabel(),
                    Alaress_Date_Format::newFromTimestamp($trigger->getTimestamp())->toRfc3339String(),
                    Alaress_Date_Format::newFromTimestamp($time + $this->maxFutureTriggerTime)->toRfc3339String(),
                )
            );

            // Need to still cache this trigger in Redis, even if it is not scheduled
            $this->cacheTrigger($trigger);
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

        $interval = $interval ?: ($trigger->getTimestamp() - $this->clock->time());
        $timer    = $this->loop->addTimer($interval, $callable);
        $this->putTimer($trigger, $timer);
        $this->cacheTrigger($trigger);

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
        if ($this->hasTimer($trigger)) {
            $timer = $this->getTimer($trigger);
            $this->log->info("[AbstractScheduler] Canceling existing timer for '{$trigger->getEventObjectId()} - {$trigger->getLabel()}'", ['char' => '-']);

            if ($timer instanceof TimerInterface) {
                $this->loop->cancelTimer($timer);
            } else {
                $this->log->debug("[AbstractScheduler] Timer is not set properly for '{$trigger->getEventObjectId()} - {$trigger->getLabel()}'", ['char' => '-']);
            }
            $this->removeTimer($trigger);

            return true;
        }

        return false;
    }


    /**
     * Get the timer for this trigger, if there is one.
     *
     * @param TriggerInterface $trigger
     * @return TimerInterface|null
     */
    private function getTimer(TriggerInterface $trigger): ?TimerInterface
    {
        $model = $trigger->getEvent()->getObject();
        $label = $trigger->getLabel();
        $class = get_class($model);
        return $this->hasTimer($trigger)
            ? $this->timers[$class][$label][$this->hashObject($model)]
            : null;
    }


    /**
     * Store the timer for this trigger, so it may be fetched by `getTrigger`.
     *
     * @param TriggerInterface $trigger
     * @param TimerInterface   $timer
     * @return void
     */
    private function putTimer(TriggerInterface $trigger, TimerInterface $timer): void
    {
        $model = $trigger->getEvent()->getObject();
        $class = get_class($model);
        $label = $trigger->getLabel();
        $hash  = $this->hashObject($model);
        $this->timers[$class][$label][$hash] = $timer;
    }

    /**
     * Store this object's trigger, so that it can be restored after the
     * scheduler process restarts.
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    private function cacheTrigger(TriggerInterface $trigger): void
    {
        if ($this->instanceId && $this->jit) {
            $model = $trigger->getEvent()->getObject();
            $class = get_class($model);
            $label = $trigger->getLabel();
            $hash  = $this->hashObject($model);
            [$storageKey,] = RedisQueue::getStorage($trigger, $this->instanceId);
            $this->redisKeys[$class][$label][$hash] = $storageKey;
            $this->jit->storeCache($trigger);
        }
    }

    /**
     * Does `$trigger` have a timer?
     *
     * @param TriggerInterface $trigger
     * @return bool
     */
    private function hasTimer(TriggerInterface $trigger): bool
    {
        $model = $trigger->getEvent()->getObject();
        $class = get_class($model);
        $label = $trigger->getLabel();
        $hash  = $this->hashObject($model);
        return isset($this->timers[$class][$label][$hash]);
    }


    /**
     * Remove the trigger associated with `$trigger`, if any.
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    private function removeTimer(TriggerInterface $trigger): void
    {
        $model = $trigger->getEvent()->getObject();
        $class = get_class($model);
        $label = $trigger->getLabel();
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
     * @return array
     */
    public function getStats(): array
    {
        $pid             = getmypid();
        $dispatcherStats = $this->dispatcher->getStatus();
        $poolStats       = $this->dispatcher->getPoolStatus();
        return [
            'memory'     => $pid ? Memory::getUsage($pid) : null,
            'dispatcher' => $dispatcherStats,
            'pool'       => $poolStats,
        ];
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
