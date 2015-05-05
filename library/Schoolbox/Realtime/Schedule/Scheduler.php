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
use Alaress\Scheduler\Cache\ICache;
use Alaress\Scheduler\Stomp\ResolutionManager;
use Alaress\Scheduler\StompScheduler;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use OutOfBoundsException;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use RuntimeException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactory;

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

    const MODULE_NS      = 'Schoolbox\\Realtime\\Schedule\\Module\\';

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


    /**
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param string                    $scheduleQueue - don't forget to declare this in rabbit!
     * @param IConfigLoader             $conf
     */
    public function __construct($loop, $dispatchStrategy, $log, $scheduleQueue, $conf = null)
    {
        // cast optional param
        $this->conf  = $conf ? : ConfigMapper::getInstance();
        $this->notif = new Notif();

        // run construction
        parent::__construct($loop, $dispatchStrategy, $scheduleQueue, $log);
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
            'host'     => $this->conf->get('amqp_host'),
            'port'     => $this->conf->get('amqp_stomp_port'),
            'vhost'    => $this->conf->get('amqp_vhost'),
            'login'    => $this->conf->get('amqp_user'),
            'passcode' => $this->conf->get('amqp_pass'),
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
            $formatted = CompressedFormatter::uncompress($frame->body);
            $event     = unserialize($formatted['message']);

            // only a few Models actually have schedule strategies, failure is ok
            // each module strategy knows how to deal with its own events
            $this->log->debug("Building trigger factory for " . get_class($event->getObject()));
            $triggerFactory = new TriggerFactory($this->getModule($event), $event, self::COMPRESSION_LEVEL);

            // retrieve, populate, and schedule this bundle of triggers
            $this->log->debug("Retrieving triggers");
            $triggers = $triggerFactory->buildTriggers($event->getAction())->getTriggers();
            $this->scheduleNotifications($triggers, $resolver);
            $triggerFactory->cleanup($triggers);

            // notice that we don't ACK the resolver here before returning
            return;
        } catch(MapperException $e) {
            // TODO Mappers shouldn't even be being used from within this block @see SB-5250
            $this->log->critical("Mapper exception: " . $e->getMessage());
        } catch(PDOException $e) {
            // this must be thrown out!
            throw $e;
        } catch(RuntimeException $e) {
            $truncated = substr($frame->body, 0, 64);
            $this->log->critical("Could not decode '$truncated...' to schedule event", array($e));
            // this message is can't be parsed, so we ACK it to make it go away
        } catch(ScheduleException $e) {
            // we're happy to ignore this, it just means no scheduling strategy exists for this Model
            $this->log->debug('Schedule Exception: ' . $e->getMessage());
        } catch(CompressionException $e) {
            // this message is unparseable, so we'll eventually ACK it to make it go away
            $this->log->critical('Compression Exception: ' . $e->getMessage(), array($e));
        } catch(\ErrorException $e) {
            $this->log->critical('Error Exception: '. $e->getMessage(), array($e));
        }

        $resolver->ack();
    }


    /**
     * Callback executed when SIGHUP is received by the process
     */
    public function onHangup()
    {
        $this->log->notice("HUP received", array('char' => '>'));
        $this->log->debug("Invalidating config cache");
        $this->conf->dropCache();
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
        if ($resolver instanceof AckResolver) {
            $this->attachResolverTrigger($resolver, $trigger);
        } else {
            $this->log->warning("No resolver supplied for attaching trigger");
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
        $this->detachTrigger($trigger);

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
     * Return the module to be working with, based on the model class (with additional safety checks)
     *
     * @param Event $event
     *   Currently there are two strategies for finding the notifier class:
     *   * Add "Notifier" to the name of the model class
     *   (so "\\Schoolbox\\User" becomes "\\Schoolbox\\UserNotifier")
     *   * Replace last word in namespace with "Notifier"
     *   (so "\\Schoolbox\\User\\Model" becomes "\\Schoolbox\\User\\Notifier")
     *
     * @param Event $event
     * @throws ScheduleException
     * @return ModuleNotifierInterface
     */
    public function getModule($event)
    {
        // setup for ifs
        $modelClass = get_class($event->getObject());
        $pregClass  = preg_replace('/([_\\\\])[^_\\\\]+$/', '$1Notifier', $modelClass);

        // first try: does the event have a mapper, and if so, does it have a modulenotifier declared?
        if ($event->getMapper() instanceof Mapper && $event->getMapper()->getModuleNotifier()) {
            $classname = $event->getMapper()->getModuleNotifier();
        } // next, try for '\Schoolbox\User' => '\Schoolbox\UserNotifier'
        elseif (class_exists($modelClass . 'Notifier')) {
            $classname = $modelClass . 'Notifier';
        } // then, try for '\Schoolbox\User' => '\Schoolbox\Notifier'
        elseif (class_exists($pregClass)) {
            $classname = $pregClass;
        } else {
            throw new ScheduleException("No notifier for model '$modelClass'.");
        }

        $instance = new $classname($this->conf);
        if (!$instance instanceof ModuleNotifierInterface) {
            throw new ScheduleException("Notifer class '$classname' must be instance of ModuleNotifierInterface.");
        }

        return $instance;
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

}
