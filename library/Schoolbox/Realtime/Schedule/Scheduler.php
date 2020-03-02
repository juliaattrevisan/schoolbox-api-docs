<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IBrokerException;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Broker\PayloadMissingException;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Async\Notification\Postman;
use Alaress\Date\IClock;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\Scheduler\Cache\ICache;
use Alaress\Scheduler\Stomp\ResolutionManager;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use ErrorException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\External;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Notif\Formatter\CompressionException;

/**
 * The scheduler is a consumer of brokered Triggers. It holds them until the time they are scheduled to fire (hence
 * the name) and then fires them. The notification bundles that result from that are then fired into the next part
 * of the notification chain (routing)
 *
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends AbstractScheduler implements IConsumer, EventListener
{
    /** @var ResolutionManager */
    private $resolvers;

    /** @var Postman */
    private $postman;

    /**
     * JIT handler to take care of unexpected shutdown
     *
     * @var ICache
     */
    private $jit;

    /** @var IConfigLoader */
    private $conf;


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IConfigLoader             $conf
     * @param IClock                    $clock
     * @param ResolutionManager         $resolutionManager
     * @param Postman                   $postman
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock,
        IConfigLoader $conf,
        ResolutionManager $resolutionManager,
        Postman $postman
    ) {
        parent::__construct($loop, $dispatchStrategy, $log, $clock);
        $this->conf      = $conf;
        $this->resolvers = $resolutionManager;
        $this->postman   = $postman;

        // register signal listeners
        $this->on(SIGHUP, [ $this, 'onHangup' ]);
        $this->on(SIGINT, [ $this, 'onInterrupt' ]);
        $this->on(SIGTERM, [ $this, 'onTerminate' ]);
    }


    /**
     * @param ICache $jit
     * @return static
     */
    public function setJitCache($jit)
    {
        $this->jit = $jit;
        return $this;
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

        $this->log->critical(sprintf('%s in %s:%s: "%s"', get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()), ['exception'=>$e]);
        // we need the daemon to crash if there's a database issue
        if ($e instanceof PDOException || $e instanceof External\Exception) {
            throw $e;
        }
    }


    /**
     * Handle the given redis event
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event)
    {
        if (!$event instanceof Message) {
            // ignore pings
            return;
        }

        // comes in the form 'digestd.vagrantx.reload'
        list (, , $action) = explode('.', $event->getChannel());

        if ($action === 'reload') {
            // this is the only action we currently know about
            $this->onHangup();
        }
    }


    /**
     * Callback executed when a payload is received from the Broker
     *
     * @param TriggerInterface $trigger
     * @param IResolver        $resolver
     * @param array|null       $context
     * @throws DuplicateResolutionException
     * @throws PDOException
     * @return void
     */
    public function onMessage($trigger, IResolver $resolver, ?array $context)
    {
        try {
            $this->log->info("Received trigger to schedule: {$trigger->getIdString()}", [ 'char' => '>' ]);

            // attempt to schedule this trigger - there are a fair few things that could go wrong...
            $this->scheduleNotification($trigger, $resolver);

            // notice that we don't acknowledge the resolver here before returning
            return;

        } catch (Deleted $e) {
            // this can be thrown from the hydration of the trigger
            $this->log->warning('Refusing to schedule notification, target object has been deleted.', [ 'exception'=>$e ]);

        } catch (InvalidArgumentException $e) {
            $this->log->error($e->getMessage(), [ 'exception'=>$e ]);

        } catch (PDOException $e) {
            // this must be thrown out!
            throw $e;

        } catch (RuntimeException $e) {
            $this->log->critical('There was a runtime issue: ' . $e->getMessage(), ['exception' => $e]);
            // this message is can't be parsed, so we ACK it to make it go away

        } catch (CompressionException $e) {
            $this->log->debug('Failed to inflate message: ' . $e->getMessage(), [ 'exception'=>$e ]);

        } catch (ErrorException $e) {
            $this->log->critical('Error Exception: '. $e->getMessage(), [ 'exception'=>$e ]);
        }

        $resolver->acknowledge();
    }


    /**
     * Callback executed when SIGHUP is received by the process
     */
    public function onHangup()
    {
        $this->log->notice('HUP received', [ 'char' => '>' ]);
        $this->log->debug('Invalidating config cache');
        $this->conf->dropCache();
        $this->log->debug('Releasing workers');
        $this->getDispatcher()->releaseWorkerPool(false);
    }


    /**
     * Callback executed when SIGINT is received by the process
     */
    public function onInterrupt()
    {
        $this->log->notice('Interrupting starfish!', [ 'char' => '>' ]);
        // for now, just treat as a termination request
        $this->onTerminate();
    }


    /**
     * Callback executed when SIGTERM is received by the process
     */
    public function onTerminate()
    {
        $this->log->error('Cleaning up and terminating');
        // die safely
        $this->getDispatcher()->releaseWorkerPool(false);

        // if possible, rescue the schedule queue from certain destruction
        $this->log->debug('Attempting save of queue');

        // if we have a just-in-time handler, use it
        if ($this->jit) {
            if ($this->jit->saveSchedule($this->resolvers)) {
                $this->log->debug('Queue save successful');

            } else {
                $this->log->alert('Failed to save queue!');
            }
        }

        $this->stop();
    }


    /**
     * @param TriggerInterface[] $triggers
     * @param IResolver          $resolver
     * @throws InvalidArgumentException
     * @throws DuplicateResolutionException
     * @return void
     */
    public function scheduleNotifications(array $triggers, IResolver $resolver = null)
    {
        $c = count($triggers);

        if ($c === 0) {
            $this->log->info('No triggers to schedule');

            if ($resolver instanceof IResolver) {
                $resolver->acknowledge();
                $this->log->debug('Resolver acknowledged');
            }

            return;
        }

        $this->log->info(sprintf('Scheduling %s trigger%s', $c, $c === 1 ? '' : 's'));

        foreach ($triggers as $trigger) {
            $this->scheduleNotification($trigger, $resolver);
        }
    }


    /**
     * @param TriggerInterface $trigger
     * @param IResolver        $resolver
     * @throws InvalidArgumentException
     */
    public function scheduleNotification(TriggerInterface $trigger, $resolver = null): void
    {
        // This should come first to clear existing resolver for the trigger
        parent::scheduleNotification($trigger);

        // if this is a cancel trigger, we're not going to even attempt attaching a resolver for it
        if (!$trigger instanceof CancelTrigger) {

            // if we have a resolver, attach it
            if ($resolver instanceof IResolver) {
                $this->attachResolverTrigger($resolver, $trigger);

            } else {
                // it's not the end of the world, but it might indicate a problem
                $this->log->warning('No resolver supplied for attaching trigger');
            }
        }

    }


    /**
     * @param TriggerInterface $trigger
     */
    public function dispatchTrigger(TriggerInterface $trigger)
    {
        if (count($trigger->getCallbacks()) === 0) {
            $this->log->warning("Trigger '{$trigger->getIdString()}' has no callbacks");
            $this->detachTrigger($trigger);
        } else {
            parent::dispatchTrigger($trigger);
        }
    }


    /**
     * @param TriggerInterface $trigger
     * @return bool
     */
    protected function unschedule(TriggerInterface $trigger): bool
    {
        // if a trigger was legitimately unscheduled, also attempt to detach it from its resolver
        if (parent::unschedule($trigger)) {
            $this->detachTrigger($trigger);
            return true;
        }
        return false;
    }


    /**
     * Handles a response from a Worker
     *
     * @param TriggerInterface $trigger - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string           $command - the command that was executed by the Worker
     * @param array            $data    - the data with which the Worker responded - array wrapping around (InstanceBundle or NULL, serialized)
     * @throws InvalidArgumentException
     * @throws ScheduleException
     */
    public function handleResponse($trigger, $command, $data)
    {
        $this->log->notice("Received worker response to handle: '$command'");

        // detach the trigger, which will ACK the message if that was the last trigger (even if it errors out)
        $this->processCompletedTrigger($trigger);

        // will throw RuntimeException if unserialization fails
        $bundle = unserialize($data[0]);

        // method not implemented or nothing to send, this is fine
        if (null === $bundle) {
            $this->log->debug('No messages to broadcast: bundle is null');
            return;
        }
        // worker died midflight and nothing valid was returned
        if ($bundle === false) {
            $this->log->error('No messages to broadcast: worker did not complete execution');
            return;
        }
        // check that a bundle was returned
        if (!$bundle instanceof InstanceBundle) {
            throw new ScheduleException(
                "Invalid callback response, must be instance of Alaress\\Async\\Notification\\InstanceBundle, got "
                . (is_object($bundle) ? get_class($bundle) : gettype($bundle))
            );
        }

        // if there are no messages, just ignore this callback and move on
        if ($bundle->isEmpty()) {
            $this->log->debug('No messages to broadcast: bundle is empty');
            return;
        }

        $count = $this->postman->deliver($bundle, [
            'user' => $trigger->getEvent()->getEffector()->getId(),
            Instance::EVENT_OBJECT_ID => $trigger->getEventObjectId()
        ]);

        if ($count === 0) {
            $this->log->debug('No messages broadcast');
            return;
        }

        $this->log->info(sprintf('%s message%s broadcast', $count, $count === 1 ? '' : 's'), [ 'char' => '<' ]);
    }


    /**
     * Attach a trigger to a resolver
     *
     * @param IResolver        $resolver
     * @param TriggerInterface $trigger
     */
    protected function attachResolverTrigger(IResolver $resolver, $trigger)
    {
        $this->resolvers->attachTriggerToResolver($resolver, $trigger);
        $resolverCount = count($this->resolvers->getAllResolvers());
        $this->log->debug("Attached trigger '{$trigger->getIdString()}' to resolver [Resolver count {$resolverCount}]");
    }


    /**
     * @param TriggerInterface $trigger
     * @return int the number of triggers left on the associated resolver, or -1 if not found
     */
    protected function detachTrigger($trigger)
    {
        try {
            $count = $this->resolvers->detachTrigger($trigger);

            switch ($count) {
                case -1:
                    $msg = 'resolver now empty; acknowledged';
                    break;
                case 0:
                    $msg = 'resolver now empty, but not acknowledged...?';
                    break;
                default:
                    $msg = "resolver still contains $count trigger" . ($count === 1 ? '' : 's');
                    break;
            }

            $resolverCount = count($this->resolvers->getAllResolvers());
            $this->log->debug("Trigger '{$trigger->getIdString()}' detached: $msg [Resolver count {$resolverCount}]");
            return $count;

        } catch (OutOfBoundsException $e) {
            $this->log->debug("Trigger '{$trigger->getIdString()}' not detached: " . $e->getMessage());
        }

        return -1;
    }


    /**
     * @param TriggerInterface $trigger
     * @throws InvalidArgumentException
     * @return int
     */
    protected function processCompletedTrigger(TriggerInterface $trigger)
    {
        // this is in the future; reschedule that!
        if ($trigger->getTimestamp() > $this->getClock()->time()) {
            $this->log->info('Trigger not complete, rescheduling');

            try {
                $this->schedule($trigger, $this->timerCallback($trigger), false);

                // don't detach, just return the count
                $resolver = $this->resolvers->getResolverByTrigger($trigger);
                return $this->resolvers->getTriggerCount($resolver);

            } catch (OutOfBoundsException $e) {
                // resolver was already detached; this isn't good, since a scheduler restart would lose state
                $this->log->warning("Resolver was already detached for trigger '{$trigger->getIdString()}'");
            }
        }

        return $this->detachTrigger($trigger);
    }


    /**
     * @return string
     */
    protected function getMethod()
    {
        return DefaultDispatchStrategy::TASK_PUBLISH;
    }


}
