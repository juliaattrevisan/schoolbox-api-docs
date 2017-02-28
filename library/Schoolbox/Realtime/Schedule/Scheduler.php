<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Async\Notification\Postman;
use Alaress\Date\IClock;
use Alaress\Scheduler\Cache\ICache;
use Alaress\Scheduler\Stomp\ResolutionManager;
use Alaress\Scheduler\StompScheduler;
use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use ErrorException;
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Redis;
use RuntimeException;
use Schoolbox\External;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\Notif;

/**
 * This class is meant to hold a list of notifications that are pending for broadcast, and at the appropriate time,
 * forward the message to RabbitMQ.
 *
 * Class Scheduler
 *
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends StompScheduler
{
    const COMPRESSION_LEVEL = 9;

    /** @var Notif */
    protected $notif;

    /** @var ResolutionManager */
    protected $resolvers;

    /**
     * JIT handler to take care of unexpected shutdown
     *
     * @var ICache
     */
    protected $jit;

    /** @var Redis|ClientInterface */
    protected $redis;

    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param string                    $scheduleQueue - don't forget to declare this in rabbit!
     * @param IConfigLoader             $conf
     * @param Notif                     $notif
     * @param ClientInterface           $redis
     * @param IClock                    $clock
     */
    public function __construct($loop, $dispatchStrategy, $log, $scheduleQueue, $conf, $notif, $redis, $clock = null)
    {
        $this->notif = $notif;
        $this->redis = $redis;

        // run construction
        parent::__construct($loop, $dispatchStrategy, $scheduleQueue, $log, $conf, $clock);
        $this->resolvers = new ResolutionManager();

        // register signal listeners
        $this->on(SIGHUP, array($this, 'onHangup'));
        $this->on(SIGINT, array($this, 'onInterrupt'));
        $this->on(SIGTERM, array($this, 'onTerminate'));
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
     * @return Client
     */
    protected function getStompConnection()
    {
        $stompFactory = new Factory($this->loop);
        return $stompFactory->createClient(array(
            'host'     => $this->getConfig()->get('amqp_host'),
            'port'     => $this->getConfig()->get('amqp_stomp_port'),
            'vhost'    => $this->getConfig()->get('amqp_vhost'),
            'login'    => $this->getConfig()->get('amqp_user'),
            'passcode' => $this->getConfig()->get('amqp_pass'),
        ));
    }


    /**
     * Callback executed when an exception bubbles to the top of the loop
     *
     * @param Exception $e
     * @throws PDOException
     * @throws External\Exception
     * @return void
     */
    public function onException($e)
    {
        $this->log->critical(sprintf('%s in %s:%s: "%s"', get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()));
        // we need the daemon to crash if there's a database issue
        if ($e instanceof PDOException || $e instanceof External\Exception) {
            throw $e;
        }
    }


    /**
     * Callback executed when a message is received from RabbitMQ on the schedule queue
     *
     * @param Frame       $frame
     * @param AckResolver $resolver
     * @throws PDOException
     * @return void
     */
    public function onMessage($frame, $resolver)
    {
        $this->log->info("Received event from $this->scheduleQueue", array('char' => '>'));
        try {
            // either it will be a compressed payload (event, deprecated), or a redis key (trigger, new format)
            if (preg_match('`:[0-9a-f]{40}$`', $frame->body)) {
                $trigger = $this->frame2trigger($this->redis, $frame);
                $this->scheduleNotification($trigger, $resolver);

                // notice that we don't ACK the resolver here before returning
                return;
            }

        } catch (InvalidArgumentException $e) {
            $this->log->error($e->getMessage(), [ $e ]);

        } catch (PDOException $e) {
            // this must be thrown out!
            throw $e;

        } catch (RuntimeException $e) {
            $truncated = substr($frame->body, 0, 64);
            $this->log->critical("Could not decode '$truncated...' to schedule event", array($e));
            // this message is can't be parsed, so we ACK it to make it go away

        } catch (CompressionException $e) {
            $this->log->debug('Failed to inflate message: ' . $e->getMessage(), array($e));

        } catch (ErrorException $e) {
            $this->log->critical('Error Exception: '. $e->getMessage(), array($e));
        }

        $resolver->ack();
    }


    /**
     * This is static so that safe-restart has access
     *
     * @param Redis|ClientInterface $redis
     * @param Frame                 $frame
     * @throws InvalidArgumentException
     * @throws ErrorException
     * @return TriggerInterface
     */
    public static function frame2trigger(ClientInterface $redis, Frame $frame)
    {
        $content = $redis->get($frame->body);

        if (strlen($content) == 0) {
            throw new InvalidArgumentException("Key `{$frame->body}` is not set");
        }

        // try to unserialize: depending on kernel, will either throw EE or just emit warning
        $trigger = unserialize($content);

        // verify it worked
        if ($trigger instanceof TriggerInterface) {
            return $trigger;
        }

        throw new InvalidArgumentException("Could not unserialize trigger");
    }


    /**
     * Callback executed when SIGHUP is received by the process
     */
    public function onHangup()
    {
        $this->log->notice("HUP received", array('char' => '>'));
        $this->log->debug("Invalidating config cache");
        $this->getConfig()->dropCache();
        $this->log->debug("Releasing workers");
        $this->dispatcher->releaseWorkerPool(false);
    }


    /**
     * Callback executed when SIGINT is received by the process
     */
    public function onInterrupt()
    {
        $this->log->notice("Interrupting starfish!", array('char' => '>'));
        // for now, just treat as a termination request
        $this->onTerminate();
    }


    /**
     * Callback executed when SIGTERM is received by the process
     */
    public function onTerminate()
    {
        $this->log->error("Cleaning up and terminating");
        // die safely
        $this->dispatcher->releaseWorkerPool(false);

        // if possible, rescue the schedule queue from certain destruction
        $this->log->debug("Attempting save of queue");

        // if we have a just-in-time handler, use it
        if ($this->jit) {
            if ($this->jit->saveSchedule($this->resolvers)) {
                $this->log->debug("Queue save successful");

            } else {
                $this->log->alert("Failed to save queue!");
            }
        }

        $this->loop->stop();
    }


    /**
     * @param TriggerInterface[] $triggers
     * @param AckResolver        $resolver
     */
    public function scheduleNotifications($triggers, $resolver = null)
    {
        $c = count($triggers);
        if ($c == 0) {
            $this->log->info("No triggers to schedule");
            if ($resolver instanceof AckResolver) {
                $resolver->ack();
                $this->log->debug("Resolver acknowledged");
            }
            return;
        }
        $this->log->info(sprintf('Scheduling %s trigger%s', $c, $c == 1 ? '' : 's'));
        foreach ($triggers as $trigger) {
            $this->scheduleNotification($trigger, $resolver);
        }
    }


    /**
     * @param TriggerInterface $trigger
     * @param AckResolver      $resolver
     */
    public function scheduleNotification($trigger, $resolver = null)
    {
        // if this is a cancel trigger, we're not going to even attempt attaching a resolver for it
        if (!$trigger instanceof CancelTrigger) {

            // if we have a resolver, attach it
            if ($resolver instanceof AckResolver) {
                $this->attachResolverTrigger($resolver, $trigger);

            } else {
                // it's not the end of the world, but it might indicate a problem
                $this->log->warning("No resolver supplied for attaching trigger");
            }
        }

        parent::scheduleNotification($trigger);
    }


    /**
     * @param TriggerInterface $trigger
     */
    public function dispatchTrigger($trigger)
    {
        if (count($trigger->getCallbacks()) == 0) {
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
    protected function unschedule($trigger)
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
     * @throws ScheduleException
     * @throws RuntimeException
     * @return void                       - this method does not return any value
     */
    public function handleResponse($trigger, $command, $data)
    {
        $this->log->notice("Received worker response to handle: '$command'");

        // detach the trigger, which will ACK the message if that was the last trigger (even if it errors out)
        $this->processCompletedTrigger($trigger);

        // will throw RuntimeException if unserialization fails
        $bundle = unserialize($data[0]);

        // method not implemented or nothing to send, this is fine
        if (is_null($bundle)) {
            $this->log->debug("No messages to broadcast");
            return;
        }
        // worker died midflight and nothing valid was returned
        if ($bundle === false) {
            $this->log->error("No messages to broadcast: worker did not complete execution");
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
            $this->log->debug("No messages to broadcast");
            return;
        }

        $postman = new Postman($this->notif, $this->log);

        $count = $postman->deliver($bundle, array(
            'user' => $trigger->getEvent()->getEffector()->getId()
        ));

        if (is_null($count)) {
            $this->log->debug("No messages to broadcast");
            return;
        }

        $this->log->info(sprintf("%s message%s broadcast", $count, $count == 1 ? '' : 's'), array('char' => '<'));
    }


    /**
     * Attach a trigger to a resolver
     *
     * @param AckResolver      $ackResolver
     * @param TriggerInterface $trigger
     */
    protected function attachResolverTrigger($ackResolver, $trigger)
    {
        $this->resolvers->attachTriggerToResolver($ackResolver, $trigger);
        $this->log->debug("Attached trigger '{$trigger->getIdString()}' to resolver");
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
                    $msg = "resolver now empty; acknowledged";
                    break;
                case 0:
                    $msg = "resolver now empty, but not acknowledged...?";
                    break;
                default:
                    $msg = "resolver still contains $count trigger" . ($count == 1 ? '' : 's');
                    break;
            }

            $this->log->debug("Trigger '{$trigger->getIdString()}' detached: $msg");
            return $count;

        } catch (OutOfBoundsException $e) {
            $this->log->debug("Trigger '{$trigger->getIdString()}' not detached: " . $e->getMessage());
        }

        return -1;
    }


    /**
     * @param TriggerInterface $trigger
     * @return int
     */
    protected function processCompletedTrigger(TriggerInterface $trigger)
    {
        // this is in the future; reschedule that!
        if ($trigger->getTimestamp() > $this->clock->time()) {
            $this->log->info("Trigger not complete, rescheduling");

            try {
                $this->schedule($trigger, $this->timerCallback(), false);

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
     * @return IConfigLoader
     */
    protected function getConfig()
    {
        /** @var IConfigLoader $c */
        $c = $this->conf;
        return $c;
    }
}
