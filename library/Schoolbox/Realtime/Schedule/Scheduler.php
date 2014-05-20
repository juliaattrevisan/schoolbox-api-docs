<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\Cli\Debug;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\Scheduler\StompScheduler;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Interceptor\Interceptor;
use Schoolbox\Realtime\Schedule\Command\Commander;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SplObjectStorage;

/**
 * This class is meant to hold a list of notifications that are pending for broadcast, and at the appropriate time,
 * forward the message to RabbitMQ.
 *
 * Class Scheduler
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends StompScheduler {

	const MODULE_NS = 'Schoolbox\\Realtime\\Schedule\\Module\\';
	const QUEUE_SCHEDULE = '.sb.sched';
	const QUEUE_COMMAND  = '.sb.cmd.sched';


	/**
	 * Binds the queues that the Scheduler requires
	 */
	public static function bindQueues() {
		// make sure our endpoints exist
		$mqc = new Connector();
		$queues = array(
			INSTANCE_ID . Scheduler::QUEUE_SCHEDULE => '*.' . Notif::TOPIC_APPLICATION,
			INSTANCE_ID . self::QUEUE_COMMAND       => '*.' . Notif::TOPIC_CMD_SCHED
		);

		foreach($queues as $q => $route) {
			$mqc->declareQueue($q);
			$mqc->bindQueue($q, $route);
		}
	}


    /** @var Notif */
    protected $notif;

    /** @var SplObjectStorage */
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

	    // run construction
        parent::__construct($loop, $dispatchStrategy, INSTANCE_ID.self::QUEUE_SCHEDULE, INSTANCE_ID.self::QUEUE_COMMAND, $debug);
        $this->notif = new Notif();
	    $this->resolvers = new SplObjectStorage();
	    $this->interceptor = new Interceptor($this->debug, $this->loop);
	    self::bindQueues();
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
     * @param Exception $e
     * @return void
     */
    public function onException($e) {
	    $this->debug->v($e->getMessage(), "!");
        $this->notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert("Cannot bind to messaging system: ".$e->getMessage());
    }


    /**
     * @param Frame $frame
     * @param AckResolver $resolver
     * @return void
     */
    public function onMessage($frame, $resolver) {
	    $this->debug->v('Received message from '.INSTANCE_ID.'.'.self::QUEUE_SCHEDULE, '>');
	    try {

			$formatted = CompressedFormatter::uncompress($frame->body);
            $event = unserialize($formatted['message']);

            if(!$event instanceof Event) {
	            $truncated = substr($frame->body, 0, 64);
                $this->debug->v("Could not decode '$truncated...' to schedule event", '!');
                // this message is unparseable, so we ACK it to make it go away
                $resolver->ack();
                return;
            }

			// only a few Models actually have schedule strategies, failure is ok
			// each module strategy knows how to deal with its own events
		    $this->debug->v("Building trigger factory from ".get_class($event->getObject()));
			$triggerFactory = new TriggerFactory($this->getModule($event->getObject()), $event);

			// retrieve, populate, and schedule this bundle of triggers
		    $this->debug->v("Scheduling triggers");
			$this->scheduleNotifications($triggerFactory->buildTriggers($event->getAction())->getTriggers(), $resolver);
		    // notice that we don't ACK the resolver here before returning
			return;

		}
		catch(ScheduleException $e) {
			// we're happy to ignore this, it just means no scheduling strategy exists for this Model
			$this->debug->v($e->getMessage(), '*');
		}
		catch(CompressionException $e) {
			// this message is unparseable, so we'll eventually ACK it to make it go away
			$this->debug->v($e->getMessage(), '!');
		}

		$resolver->ack();
	}


	/**
	 * @param Frame $frame
	 */
	public function onCommand($frame) {
		$this->debug->v('Received command from '.INSTANCE_ID.'.'.self::QUEUE_SCHEDULE, '>');
		$cmdr = new Commander($this);
		$cmdr->exec($frame->body);
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
			$debug->v("Got a subscribed message for ".$user->getUsername());
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
		foreach(UserMapper::getInstance()->findAll() as $user) {
			/** @var User $user */
			$this->subscribeUser($user->getId());
		}
	}


    /**
     * @param TriggerInterface[] $triggers
     * @param AckResolver $resolver
     */
    public function scheduleNotifications($triggers, $resolver = null) {
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

		/** @var Messenger $msgr */
		$msgr = unserialize($data[0]);

		/* attempt some sort of type-safety */

		// method not implemented or nothing to send, this is fine
		if(is_null($msgr)) {
			$this->debug->v("No messages to broadcast");
			return;
		}

		// check that an array was returned
		if(!$msgr instanceof Messenger) {
			throw new ScheduleException(
				"Invalid callback response, must be instance of Schoolbox\\Notif\\Message\\Messenger, got "
				. (is_object($msgr) ? get_class($msgr) : gettype($msgr))
			);
		}

		// if there are no messages, just ignore this callback and move on
		if(!$msgr->hasMessages()) {
			$this->debug->v("No messages to broadcast");
			return;
		}

        $count = $msgr->send($this->notif, $trigger);

        if(is_null($count)) {
            $this->debug->d("No messages to broadcast");
            return;
        }

		$this->debug->v(sprintf("%s message%s broadcast", $count, $count == 1 ? '' : 's'), '<');
	}


    /**
     * Return the module to be working with, based on the model class (with additional safety checks)
     *
     * @param object|string $modelClass
     * @throws ScheduleException
     * @return ModuleNotifierInterface
     */
    public function getModule($modelClass) {
        $modelClass = is_object($modelClass) ? get_class($modelClass) : $modelClass;
        $class = $modelClass . "Notifier";
        if(!class_exists($class)) {
            throw new ScheduleException("No notifier for model '$modelClass'.");
        }
        $instance = new $class($this->conf);
        if(!$instance instanceof ModuleNotifierInterface) {
            throw new ScheduleException("Notifer class '$class' found, but not an instance of ModuleNotifierInterface.");
        }

        return $instance;
    }


    /**
     * @param AckResolver $ackResolver
     * @param TriggerInterface $trigger
     */
    protected function attachResolverTrigger($ackResolver, $trigger) {
        if($this->resolvers->contains($ackResolver)) {
            /** @var SplObjectStorage $triggers */
            $triggers = $this->resolvers->offsetGet($ackResolver);
        }
        else {
            $triggers = new SplObjectStorage();
        }
        $triggers->attach($trigger);
        $this->resolvers->attach($ackResolver, $triggers);
	    $this->debug->v("Attached trigger, resolver contains ".$triggers->count()." triggers", '+');
    }


    /**
     * @param TriggerInterface $trigger
     * @return int|null the number of triggers left on the associated resolver, or null if not found
     */
    protected function detachTrigger($trigger) {
        foreach($this->resolvers as $resolver) {
            $triggers = $this->resolvers[$resolver];

            /**
             * @var AckResolver $resolver
             * @var SplObjectStorage $triggers
             */
            if($triggers->contains($trigger)) {
                $this->debug->v("Detaching trigger from resolver", '-');
                $triggers->detach($trigger);
                if($triggers->count() == 0) {
                    // this resolver's triggers have all fired, ACK the message
                    $this->debug->v("Resolver empty, ACKnowledging", '-');
                    $resolver->ack();
                }
                else {
                    $this->debug->v('Resolver still contains ' . $triggers->count() . ' triggers');
                }
                return $triggers->count();
            }
        }
        $this->debug->v("Trigger ". $this->generateTriggerIdString($trigger) ." not on record, nothing to detach");

        return null;
    }


    /**
     * @param AckResolver $ackResolver
     * @return int|null
     */
    protected function checkResolver($ackResolver) {
        if($this->resolvers->contains($ackResolver)) {
            $triggers = $this->resolvers->offsetGet($ackResolver);
            return count($triggers);
        }

        return null;
    }


	/**
	 * @param TriggerInterface $trigger
	 * @return string
	 */
	protected function generateTriggerIdString($trigger) {
		return sprintf("%s:%s", get_class($trigger->getEvent()->getObject()), $trigger->getLabel());
	}

}