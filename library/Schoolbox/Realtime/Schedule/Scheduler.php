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
use Alaress\Cli\Debug;
use Alaress\Scheduler\Stomp\ResolutionManager;
use Alaress\Scheduler\CommandableInterface;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\Scheduler\StompScheduler;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ProcessingException;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Interceptor\Interceptor;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * This class is meant to hold a list of notifications that are pending for broadcast, and at the appropriate time,
 * forward the message to RabbitMQ.
 *
 * Class Scheduler
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends StompScheduler implements CommandableInterface {

	const MODULE_NS = 'Schoolbox\\Realtime\\Schedule\\Module\\';
	const QUEUE_SCHEDULE = '.sb.sched';
	const QUEUE_COMMAND  = '.sb.cmd.sched';


	/**
	 * Binds the queues that the Scheduler requires
	 */
	public static function bindQueues() {
		// make sure our endpoints exist
		$mqc = Connector::getInstance();
		$queues = array(
			INSTANCE_ID . self::QUEUE_SCHEDULE => '*.' . Notif::TOPIC_APPLICATION,
			INSTANCE_ID . self::QUEUE_COMMAND  => '*.' . Notif::TOPIC_CMD_SCHED
		);

		foreach($queues as $q => $route) {
			$mqc
				->declareQueue($q)
				->bindQueue($q, $route);
		}
	}


    /** @var Notif */
    protected $notif;

    /** @var ResolutionManager */
    protected $resolvers;

	/** @var Interceptor */
	protected $interceptor;


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param Debug $debug
     * @param IConfigLoader $conf
     */
    public function __construct($loop, $dispatchStrategy, $debug, $conf = null) {
	    // cast optional param
	    $this->conf = $conf ?: ConfigMapper::getInstance();
	    $this->notif = new Notif();

	    // run construction
        parent::__construct($loop, $dispatchStrategy, INSTANCE_ID.self::QUEUE_SCHEDULE, INSTANCE_ID.self::QUEUE_COMMAND, $debug);
	    $this->resolvers = new ResolutionManager();
	    $this->interceptor = new Interceptor($this->debug, $this->loop);

	    self::bindQueues();

	    // register signal listeners
	    $this->on(SIGHUP,  array($this, 'onHangup'));
	    $this->on(SIGINT,  array($this, 'onInterrupt'));
	    $this->on(SIGTERM, array($this, 'onTerminate'));
    }


    /**
     * @return Client
     */
    protected function getStompConnection() {
        $stompFactory = new Factory($this->loop);
        return $stompFactory->createClient(array(
            'host'      => $this->conf->get('amqp_host'),
            'port'      => $this->conf->get('amqp_stomp_port'),
            'vhost'     => '/',
            'login'     => $this->conf->get('amqp_user'),
            'passcode'  => $this->conf->get('amqp_pass'),
        ));
    }


    /**
     * Callback executed when the STOMP client connects
     */
    public function onConnect() {
	    // perform the subscription
        parent::onConnect();

	    // technically in this context it's only "load", but the outcome is the same
		$this->reload();
    }


    /**
     * Callback executed when an exception bubbles to the top of the loop
     *
     * @param Exception $e
     * @return void
     */
    public function onException($e) {
	    $this->debug->v($e->getMessage(), "!");
        $this->notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert("Cannot bind to messaging system: ".$e->getMessage());
    }


    /**
     * Callback executed when a message is received from RabbitMQ on the schedule queue
     *
     * @param Frame $frame
     * @param AckResolver $resolver
     * @return void
     */
    public function onMessage($frame, $resolver) {
		$this->debug->v('Received message from '.INSTANCE_ID.self::QUEUE_SCHEDULE, '>');
		try {
			$formatted = CompressedFormatter::uncompress($frame->body);
			$event = unserialize($formatted['message']);

			// only a few Models actually have schedule strategies, failure is ok
			// each module strategy knows how to deal with its own events
			$this->debug->v("Building trigger factory for ".get_class($event->getObject()));
			$triggerFactory = new TriggerFactory($this->getModule($event), $event);

			// retrieve, populate, and schedule this bundle of triggers
			$this->debug->v("Retrieving triggers");
			$this->scheduleNotifications($triggerFactory->buildTriggers($event->getAction())->getTriggers(), $resolver);

			// notice that we don't ACK the resolver here before returning
			return;
		}
		catch(MapperException $e) {
			// TODO Mappers shouldn't even be being used from within this block @see SB-5250
			$this->debug->v("Mapper exception: ".$e->getMessage(), '!');
		}
	    catch(RuntimeException $e) {
		    $truncated = substr($frame->body, 0, 64);
		    $this->debug->v("Could not decode '$truncated...' to schedule event", '!');
		    // this message is can't be parsed, so we ACK it to make it go away
	    }
		catch(ScheduleException $e) {
			// we're happy to ignore this, it just means no scheduling strategy exists for this Model
			$this->debug->v('Schedule Exception: ' . $e->getMessage(), '*');
		}
		catch(CompressionException $e) {
			// this message is unparseable, so we'll eventually ACK it to make it go away
			$this->debug->v('Compression Exception: ' . $e->getMessage(), '!');
		}

		$resolver->ack();
	}


	/**
	 * Callback executed when a command is received from RabbitMQ on the command queue
	 *
	 * @param Frame $frame
	 */
	public function onCommand($frame) {
		$this->debug->v('Received command from '.INSTANCE_ID.self::QUEUE_COMMAND, '>');
		$cmdr = new SchedulerCommander();
		$body = CompressedFormatter::uncompress($frame->body);
		$result = $cmdr->exec($this, $body['message']);
		$this->debug->v("Command executed ".($result ? 'successfully' : 'unsuccessfully'), $result ? ' ' : '*');
	}


	/**
	 * Callback executed when SIGHUP is received by the process
	 */
	public function onHangup() {
		$this->debug->v("HUP received: reloading user queue subscriptions", ">");
		$this->reload();
	}


	/**
	 * Callback executed when SIGINT is received by the process
	 */
	public function onInterrupt() {
		$this->debug->v("Interrupting starfish!", '>');
		// for now, just treat as a termination request
		$this->onTerminate();
	}


	/**
	 * Callback executed when SIGTERM is received by the process
	 */
	public function onTerminate() {
		$this->debug->v("Cleaning up and terminating", '*');
		// die safely
		unset($this->dispatcher);
		exit;
	}


	/**
	 * @param int $userId
	 * @return bool
	 */
	public function subscribeUser($userId) {
		/** @var User $user - unfortunately necessary self hydration */
		$user = UserMapper::getInstance()->findById($userId);
		$queue = UserQueue::getUserToSortQueueName($userId);
		$debug = $this->debug;
		$int = $this->interceptor;
		return $this->subscribe("/amq/queue/$queue", function($frame) use ($int, $user, $debug) {
			// this closure is executed every time a message is sent to the client based on the given subscription
			$int->process($user, $frame);
		});
	}


	/**
	 * @param int $userId
	 * @return bool
	 */
	public function unsubscribeUser($userId) {
		$queue = UserQueue::getUserToSortQueueName($userId);
		return $this->unsubscribe($queue);
	}


	/**
	 * Drops all user subscriptions currently held, and then reloads all from DB
	 */
	public function reload() {
		// detach user subscriptions
		foreach(array_keys($this->subscriptions) as $queue) {
			// don't unsubscribe from the master queues
			if($queue == $this->scheduleQueue || $queue == $this->commandQueue) {
				continue;
			}
			$this->unsubscribe($queue);
		}

		// reattach user subscriptions
		// subscribe to every (every!) user's 'to sort' queue, and run those messages through the Interceptor
		/** @var User[]|Collection $users */
		$users = UserMapper::getInstance()->findAll();
		foreach($users as $user) {
			try {
				$this->subscribeUser($user->getId());
			}
			catch(ProcessingException $e) {
				$this->debug->v("Could not subscribe user {$user->getId()}", '*');
			}
		}
		$this->debug->v("Subscribed to {$users->count()} user queues");
	}


    /**
     * @param TriggerInterface[] $triggers
     * @param AckResolver $resolver
     */
    public function scheduleNotifications($triggers, $resolver = null) {
        $c = count($triggers);
	    if($c == 0) {
	        $this->debug->v("No triggers to schedule");
	        return;
        }
	    $this->debug->v(sprintf('Scheduling %s trigger%s', $c, $c == 1 ? '' : 's'));
	    foreach($triggers as $trigger) {
            $this->scheduleNotification($trigger, $resolver);
        }
    }


    /**
     * @param TriggerInterface $trigger
     * @param AckResolver $resolver
     */
    public function scheduleNotification($trigger, $resolver = null) {
        parent::scheduleNotification($trigger);
        if($resolver instanceof AckResolver) {
	        $this->debug->v("Attaching resolver");
            $this->attachResolverTrigger($resolver, $trigger);
        }
	    else {
		    $this->debug->v("No resolver supplied for attaching trigger", '*');
	    }
    }


    /**
	 * Handles a response from a Worker
	 *
	 * @param TriggerInterface $trigger   - the identifier passed as the first argument to Dispatcher::enqueue()
	 * @param string $command             - the command that was executed by the Worker
	 * @param array  $data                - the data with which the Worker responded
	 * @throws ScheduleException
	 * @return void                       - this method does not return any value
	 */
	public function handleResponse($trigger, $command, $data) {
		$this->debug->v("Received worker response to handle: '$command' -> ".substr(json_encode($data), 0, 64)."...");

		// detach the trigger, which will ACK the message if that was the last trigger (even if it errors out)
		$this->detachTrigger($trigger);

		// will throw RuntimeException if unserialization fails
		$bundle = unserialize($data[0]);

		// method not implemented or nothing to send, this is fine
		if(is_null($bundle)) {
			$this->debug->v("No messages to broadcast");
			return;
		}
		// check that a bundle was returned
		if(!$bundle instanceof InstanceBundle) {
			throw new ScheduleException(
				"Invalid callback response, must be instance of Alaress\\Async\\Notification\\InstanceBundle, got "
				. (is_object($bundle) ? get_class($bundle) : gettype($bundle))
			);
		}

		// if there are no messages, just ignore this callback and move on
		if($bundle->isEmpty()) {
			$this->debug->v("No messages to broadcast");
			return;
		}

		$postman = new Postman($this->notif, $this->debug);

		$count = $postman->deliver($bundle, array('user' => $trigger->getEvent()->getEffector()->getId()));

        if(is_null($count)) {
            $this->debug->d("No messages to broadcast");
            return;
        }

		$this->debug->v(sprintf("%s message%s broadcast", $count, $count == 1 ? '' : 's'), '<');
	}


    /**
     * Return the module to be working with, based on the model class (with additional safety checks)
     *
     * @param Event $event
     * Currently there are two strategies for finding the notifier class:
     * * Add "Notifier" to the name of the model class
     *   (so "\\Schoolbox\\User" becomes "\\Schoolbox\\UserNotifier")
     * * Replace last word in namespace with "Notifier"
     *   (so "\\Schoolbox\\User\\Model" becomes "\\Schoolbox\\User\\Notifier")
     *
     * @param Event $event
     * @throws ScheduleException
     * @return ModuleNotifierInterface
     */
    public function getModule($event) {
	    // setup for ifs
	    $modelClass = get_class($event->getObject());
		$pregClass = preg_replace('/([_\\\\])[^_\\\\]+$/', '$1Notifier', $modelClass);

	    // first try: does the event have a mapper, and if so, does it have a modulenotifier declared?
		if($event->getMapper() instanceof Mapper && $event->getMapper()->getModuleNotifier()) {
			$classname = $event->getMapper()->getModuleNotifier();
		}
		// next, try for '\Schoolbox\User' => '\Schoolbox\UserNotifier'
	    elseif(class_exists($modelClass.'Notifier')) {
		    $classname = $modelClass.'Notifier';
	    }
		// then, try for '\Schoolbox\User' => '\Schoolbox\Notifier'
	    elseif(class_exists($pregClass)) {
		    $classname = $pregClass;
	    }
	    else {
		    throw new ScheduleException("No notifier for model '$modelClass'.");
	    }

        $instance = new $classname($this->conf);
        if(!$instance instanceof ModuleNotifierInterface) {
            throw new ScheduleException("Notifer class '$classname' must be instance of ModuleNotifierInterface.");
        }

        return $instance;
    }


    /**
     * Attach a trigger to a resolver
     *
     * @param AckResolver $ackResolver
     * @param TriggerInterface $trigger
     */
    protected function attachResolverTrigger($ackResolver, $trigger) {
	    $this->resolvers->attachTriggerToResolver($ackResolver, $trigger);
	    $relTime = $trigger->getTimestamp() - time();
	    $this->debug->v("Attached trigger [{$trigger->getLabel()}, {$trigger->getEvent()->getAction()} : T-$relTime], resolver contains ".$this->resolvers->getTriggerCount($ackResolver)." triggers", '+');
    }


    /**
     * @param TriggerInterface $trigger
     * @return int the number of triggers left on the associated resolver, or -1 if not found
     */
    protected function detachTrigger($trigger) {
		$count = $this->resolvers->detachTrigger($trigger);

	    switch($count) {
		    case -1:
			    $msg = "resolver now empty; detached";
			    break;
		    case 0:
			    $msg = "resolver now empty, but not detached...?";
			    break;
		    default:
			    $msg = "resolver still contains $count trigger" . ($count == 1 ? '' : 's');
			    break;
	    }

	    $this->debug->v("Detaching trigger: $msg", '-');

	    return $count;
    }


	/**
	 * @param TriggerInterface $trigger
	 * @return string
	 */
	protected function generateTriggerIdString($trigger) {
		return sprintf("%s:%s", get_class($trigger->getEvent()->getObject()), $trigger->getLabel());
	}

} 