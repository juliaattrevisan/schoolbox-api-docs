<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\CompressionException;
use Schoolbox\Notif\Message\Interceptor;
use Schoolbox\Notif\Message\Internal;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\CancelTrigger;
use Schoolbox\Realtime\Schedule\Trigger\TriggerInterface;
use Schoolbox\Realtime\Verboser;
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
class Scheduler implements SchedulingInterface, TaskResponseHandlerInterface {

	const MODULE_NS = 'Schoolbox\\Realtime\\Schedule\\Module\\';
	const QUEUE_SCHEDULE = '.sb.sched';

	/** @var array */
	protected static $knownModules = array(
		'\\Schoolbox\\Assessment\\Component\\SubmissionBox',
		 // register yo known module Notifiers here
	);

	/** @var LoopInterface */
	protected $loop;

	/** @var Verboser */
	public $verboser;

	/** @var array */
	protected $timers;

	/** @var Notif */
	protected $notif;

	/** @var DispatcherInterface */
	protected $dispatcher;

	/** @var SplObjectStorage */
	protected $resolvers;

	/**
	 * @param LoopInterface $loop
	 * @param Verboser $verboser
	 * @param IConfigLoader $configLoader
	 * @param bool $initialise
	 */
	public function __construct($loop, $verboser, $configLoader = null, $initialise = false) {
		$this->loop = $loop;
		$this->verboser = $verboser;
		$this->mqc = new Connector();
		$this->conf = $configLoader;
		$this->notif = new Notif();
		$this->dispatcher = new Dispatcher($loop, $this, $verboser, new SchedulerDispatchStrategy());
		$this->resolvers = new SplObjectStorage();

		if($initialise) {
			$this->initialise();
		}
	}

	/**
	 * Retrieve all the messages that need to be scheduled for delivery at a later time.
	 */
	protected function initialise() {

		// create a stomp client for listening to Mapper messages coming in
		$stompFactory = new Factory($this->loop);
		$interceptor  = new Interceptor($this->verboser, $this->loop);

		try {
			$stompClient = $stompFactory->createClient(array(
				'host'      => $this->conf->get('amqp_host'),
				'port'      => $this->conf->get('amqp_stomp_port'),
				'vhost'     => '/',
				'login'     => $this->conf->get('amqp_user'),
				'passcode'  => $this->conf->get('amqp_pass'),
			));

			$v = $this->verboser;
			$self = $this;

			/** Connect to stomp via the provided loop */
			$stompClient->connect()->then(function($client) use ($v, $self, $interceptor) {
				// this closure is called when stomp successfully connects
				/** @var Client $client */
				$v->v("Scheduler connected to STOMP", '+');

				// make sure our endpoint exists
				$mqc = new Connector();
				$mqc->declareQueue(INSTANCE_ID . Scheduler::QUEUE_SCHEDULE);
				$mqc->bindQueue(INSTANCE_ID . Scheduler::QUEUE_SCHEDULE, '*.' . Notif::TOPIC_APPLICATION);

				/** Subscribe to the queue set aside for the scheduler */
				$client->subscribeWithAck('/amq/queue/' . INSTANCE_ID . Scheduler::QUEUE_SCHEDULE, 'client', array($self, 'onAMQP'));

				// subscribe to every (every!) user's 'to sort' queue, and run those messages through the Interceptor
				foreach(UserMapper::getInstance()->findAll() as $user) {
					/** @var User $user - self hydration */
					$user = UserMapper::getInstance()->findById($user->getId());
					$subn = '/amq/queue/'.UserQueue::getUserToSortQueueName($user->getId());
					$client->subscribe($subn, function($frame) use ($interceptor, $user) {
						// this closure is executed every time a message is sent to the client based on the given subscription
						$interceptor->process($user, $frame);
					});
				}
			});
		}
		catch(ConnectionException $e) {
			$notif = new Notif(Notif::TOPIC_NOTIFICATION);
			$notif->alert("Cannot bind to messaging system: check your STOMP bindings and that RabbitMQ is running.");
			$this->verboser->v('Rabbitmq is offline or STOMP misconfigured: '. $e->getMessage(), '!');
		}

	}


	/**
	 * Handles a STOMP frame.
	 *
	 * @param Frame $frame
	 * @param AckResolver $ackResolver
	 */
	public function onAMQP($frame, $ackResolver) {
		try {
			$message = Internal::reconstitute($frame->body);

			// we can't do squat if the Mapper didn't send us a message in the right format
			if(!$message instanceof Internal) {
				$this->verboser->v("Could not decode '$frame->body' to schedule event", '!');
				// this message is unparseable, so we ACK it to make it go away
				$ackResolver->ack();
				return;
			}

			// only a few Models actually have schedule strategies, failure is ok
			// each module strategy knows how to deal with its own events
			$triggerFactory = new TriggerFactory($this->getModule($message->getObjectClass()), $message);

			// retrieve, populate, and schedule this bundle of triggers
			$this->scheduleNotifications($triggerFactory->buildTriggers($message->getMethod())->getTriggers(), $ackResolver);
			return;

		}
		catch(ScheduleException $e) {
			// we're happy to ignore this, it just means no scheduling strategy exists for this Model
			$this->verboser->v($e->getMessage(), '*');
		}
		catch(CompressionException $e) {
			// this message is unparseable, so we'll eventually ACK it to make it go away
			$this->verboser->v($e->getMessage(), '!');
		}

		$ackResolver->ack();
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

		$this->verboser->v("Received worker response to handle: '$command' -> ".substr(json_encode($data), 0, 64)."...");

		// detach the trigger, which will ACK the message if that was the last trigger (even if it errors out)
		$this->detachTrigger($trigger);

		/** @var Messenger $msgr */
		$msgr = unserialize($data[0]);

		/* attempt some sort of type-safety */

		// method not implemented or nothing to send, this is fine
		if(is_null($msgr)) {
			$this->verboser->v("No messages to broadcast");
			return;
		}

		// check that an array was returned
		if(!$msgr instanceof Messenger) {
			throw new ScheduleException("Invalid callback response, must be instance of Schoolbox\\Notif\\Message\\MessageCollection");
		}

		// if there are no messages, just ignore this callback and move on
		if(!$msgr->hasMessages()) {
			$this->verboser->v("No messages to broadcast");
			return;
		}

		$count = 0;
		$topicArray = array();

		// iterate across the messages supplied and send them
		foreach($msgr->generateExternalMessages($this->notif, $trigger->getTimestamp()) as $message) {
			// send them all!
			$message->setContext(array('user' => $trigger->getUser()->getId()));
			$sent = $message->send();
			$count += $sent ? 1 :0;
			$topicArray = array_merge($topicArray, $message->getTopics());
		}

		$topics = implode("\n    - ", $topicArray);

		$this->verboser->v(sprintf("%s message%s broadcast to %s", $count, $count == 1 ? '' : 's', $topics), '<');

	}


	public static function getKnownModules() {
		return self::$knownModules;
	}

	/**
	 * @return DispatcherInterface
	 */
	public function getDispatcher() {
		return $this->dispatcher;
	}

	/**
	 * Convenience method for scheduling notifications for multiple triggers.
	 *
	 * @param TriggerInterface[] $triggers
	 * @param AckResolver $ackResolver
	 */
	public function scheduleNotifications($triggers, $ackResolver = null) {
		foreach($triggers as $trigger) {
			$this->scheduleNotification($trigger, $ackResolver);
		}
	}


	/**
	 * Schedule a notification based on the given trigger.
	 * Idempotent method.
	 *
	 * @param TriggerInterface $trigger
	 * @param AckResolver $ackResolver
	 */
	public function scheduleNotification($trigger, $ackResolver = null) {

		$model = $trigger->getModel();
		$moduleNotifierClass = get_class($trigger->getModuleNotifier());
		$triggerLabel = $trigger->getLabel();

		// fail-fast for cancelling a scheduled notification
		if($trigger instanceof CancelTrigger) {
			if(!$this->unschedule($moduleNotifierClass, $triggerLabel, $model)) {
				$this->verboser->v("Couldn't unschedule '$triggerLabel' - was it set?", '*');
				if(!$this->checkResolver($ackResolver)) {
					$ackResolver->ack();
					$this->verboser->v("No triggers attached to resolver, ACKnowledging", '-');
				}
			}
			return;
		}

		if($ackResolver instanceof AckResolver) {
			$this->attachResolverTrigger($ackResolver, $trigger);
		}

		$v = $this->verboser;
		$dispatch = $this->getDispatcher();

		/**
		 * This closure will be called from the EventLoop at the appointed time, or immediately if the time has elapsed.
		 *
		 * @param TimerInterface $timer
		 * @throws Exception
		 */
		$publish = function($timer) use ($trigger, $v, $dispatch) {

			foreach($trigger->getCallbacks() as $callback) {
				/*
				 * dynamic message construction for broadcast.
				 * different people can receive different messages from the one event
				 * $callback will be a trigger method from an implementation of ModuleInterface
				 *    - by default, is in the form "notify{Roletype}{Trigger}"
				 */

				try {

					if(!is_callable($callback)) {
						throw new ScheduleException("Uncallable callback supplied");
					}

					$dispatch->enqueue($trigger, 'publish', serialize(array(
						$callback, $trigger->getModel(), $trigger->getUser(), $trigger->getDiff()
					)));

				}
				catch(ScheduleException $e) {
					$v->v($e->getMessage(), '!');
				}
				catch(Exception $e) {
					// don't know what other exceptions will be thrown here, so for now, acknowledge and rethrow
					$v->v(sprintf("%s -> %s",get_class($e), $e->getMessage()), '!');
					throw $e;
				}

			}
		};

		$interval = $trigger->getTimestamp() - time();

		// publish and return immediately if the event is now or in the past
		if($interval <= ModuleNotifierInterface::IMMEDIATELY) {
			$this->verboser->v("Immediately firing trigger '$moduleNotifierClass::$triggerLabel'");
			$publish(null);
			return;
		}

		// schedule the future event
		$this->schedule($moduleNotifierClass, $triggerLabel, $model, $interval, $publish);

	}


	/**
	 * Set a timer for the appointed method at the appointed time, cancelling a previous timer if it exists.
	 *
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @param int $interval
	 * @param callable $publish
	 */
	protected function schedule($module, $label, $model, $interval, $publish) {
		// if a timer has already been set for this event on this object, we need to cancel the old and set the new
		$this->unschedule($module, $label, $model);

		// register our timer
		$timer = $this->loop->addTimer($interval, $publish);
		$this->putTimer($module, $label, $model, $timer);

		$this->verboser->v("Scheduling trigger '$module::$label'", '+');
	}


	/**
	 * Unschedule an existing timer, if it has been set.
	 *
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return bool
	 */
	protected function unschedule($module, $label, $model) {
		if($this->hasTimer($module, $label, $model)) {
			$timer = $this->getTimer($module, $label, $model);
			$this->verboser->v("Unscheduling trigger '$module::$label'", '-');
			$this->loop->cancelTimer($timer);
			return true;
		}
		return false;
	}


	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return Timer|null
	 */
	protected function getTimer($module, $label, $model) {
		return $this->hasTimer($module, $label, $model)
			? $this->timers[$module][$label][$this->hashModel($model)]
			: null;
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @param Timer $timer
	 */
	protected function putTimer($module, $label, $model, $timer) {
		$hash = $this->hashModel($model);
		$this->timers[$module][$label][$hash] = $timer;
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return bool
	 */
	protected function hasTimer($module, $label, $model) {
		$hash = $this->hashModel($model);
		return isset($this->timers[$module][$label][$hash]);
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 */
	public function removeTimer($module, $label, $model) {
		$hash = $this->hashModel($model);
		unset($this->timers[$module][$label][$hash]);
	}

	/**
	 * @param object $model
	 * @return string
	 */
	protected function hashModel($model) {
		$modelClass = get_class($model);
		$mapClass = $modelClass . "Mapper";

		if(class_exists($mapClass)) {
			$getId = $mapClass::getInstance()->getGetIdentifierMethodName();
			$hash = sha1("$modelClass-".(string)$model->$getId());
		}
		else {
			$hash = sha1(print_r($model, true));
		}
		return $hash;
	}

	/**
	 * Return the module to be working with, based on the model class (with additional safety checks)
	 *
	 * @param string $modelClass
	 * @throws ScheduleException
	 * @return ModuleNotifierInterface
	 */
	public function getModule($modelClass) {
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
				$this->verboser->v("Detaching trigger from resolver", '-');
				$triggers->detach($trigger);
				if($triggers->count() == 0) {
					// this resolver's triggers have all fired, ACK the message
					$this->verboser->v("Resolver empty, ACKnowledging", '-');
					$resolver->ack();
				}
				else {
					$this->verboser->v('Resolver still contains ' . $triggers->count() . ' triggers');
				}
				return $triggers->count();
			}
		}
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
} 