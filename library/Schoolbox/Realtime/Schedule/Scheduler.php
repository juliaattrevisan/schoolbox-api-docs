<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Async\Notification\Postman;
use Alaress\Date\IClock;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use ErrorException;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\User;

/**
 * The scheduler is a consumer of brokered Triggers. It holds them until the time they are scheduled to fire (hence
 * the name) and then fires them. The notification bundles that result from that are then fired into the next part
 * of the notification chain (routing)
 *
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends AbstractScheduler implements IConsumer, EventListener
{
    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     * @param IConfigLoader             $conf
     * @param Postman                   $postman
     * @param string                    $instanceId
     * @param int|null                  $maxFutureTriggerTime
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock,
        private IConfigLoader $conf,
        private Postman $postman,
        string $instanceId,
        ?int $maxFutureTriggerTime = null,
    ) {
        parent::__construct($loop, $dispatchStrategy, $log, $clock, $maxFutureTriggerTime);
        $this->instanceId = $instanceId;

        // register signal listeners
        $this->on(SIGHUP, [ $this, 'onHangup' ]);
        $this->on(SIGINT, [ $this, 'onInterrupt' ]);
        $this->on(SIGTERM, [ $this, 'onTerminate' ]);
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
        [, , $action] = explode('.', $event->getChannel());

        if ($action === 'reload') {
            $this->log->debug('Reloading subscription cache to router');
            // this is the only action we currently know about
            $this->postman->reloadRouter();
            $this->onHangup();
            return;
        }

        if ($action === 'refreshConfiguration') {
            $this->log->debug('Dropping config cache');
            $this->conf->dropCache();
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
        if (!$trigger instanceof TriggerInterface) {
            $this->log->error('[Scheduler] trigger received is broken');
            $resolver->reject();
            return;
        }

        try {
            $this->log->info("[Scheduler] Received trigger to schedule: {$trigger->getEventObjectId()} - {$trigger->getLabel()}", [ 'char' => '>' ]);

            $effector = $trigger->getEvent()->getEffector();
            if ($effector instanceof User) {
                $this->log->debug('[Scheduler] triggered by user.' . $effector->getId());
            }

            // attempt to schedule this trigger - there are a fair few things that could go wrong...
            // This uses the scheduler's configuration:
            // getMaxQueueLength, getWarningQueueLength, getQueueDelay only AFAICT,
            // but also protocol and supported actions?
            $this->scheduleNotification($trigger, $resolver);

            // notice that we don't acknowledge the resolver here before returning
            return;
        } catch (Deleted $e) {
            // this can be thrown from the hydration of the trigger
            $this->log->warning('Refusing to schedule notification, target object has been deleted.', [ 'exception' => $e ]);
        } catch (InvalidArgumentException $e) {
            $this->log->error($e->getMessage(), [ 'exception' => $e ]);
        } catch (PDOException $e) {
            // this must be thrown out!
            throw $e;
        } catch (RuntimeException $e) {
            $this->log->critical('There was a runtime issue: ' . $e->getMessage(), ['exception' => $e]);
            // this message is can't be parsed, so we ACK it to make it go away
        } catch (CompressionException $e) {
            $this->log->debug('Failed to inflate message: ' . $e->getMessage(), [ 'exception' => $e ]);
        } catch (ErrorException $e) {
            $this->log->critical('Error Exception: ' . $e->getMessage(), [ 'exception' => $e ]);
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
        $this->dispatcher->releaseWorkerPool(false);
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
        $this->dispatcher->releaseWorkerPool(false);

        // if possible, rescue the schedule queue from certain destruction
        $this->log->debug('Attempting save of queue');
        $this->stop();
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
        $this->log->debug("Received worker response to handle: '$command'");

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
     * @return string
     */
    protected function getMethod()
    {
        return DispatchStrategy::TASK_PUBLISH;
    }
}
