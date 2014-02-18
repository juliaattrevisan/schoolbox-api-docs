<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\CompressionException;
use Schoolbox\Notif\Message\External;
use Schoolbox\Notif\Message\Internal;
use Schoolbox\Notif\Message\MessageCollection;
use Schoolbox\Notif\Message\MessageFactory;
use Schoolbox\Notif\Message\MessageInterface;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Verboser;

/**
 * This class is meant to hold a list of notifications that are pending for broadcast, and at the appropriate time,
 * forward the message to RabbitMQ.
 *
 * Class Scheduler
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler {

	const MODULE_NS = 'Schoolbox\\Realtime\\Schedule\\Module\\';
	const QUEUE_SCHEDULE = 'sb.sched';

	/** @var array */
	protected $knownModules = array(
		'\\Schoolbox\\Assessment\\Component\\SubmissionBox',
		 // register yo known module Notifiers here
	);

	/** @var LoopInterface */
	protected $loop;

	/** @var Verboser */
	protected $verboser;

	/** @var Connector */
	protected $mqc;

	/** @var Client */
	protected $stompClient;

	/** @var Notif */
	protected $notif;

	/** @var array */
	protected $timers;

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
		$this->notif = new Notif();
		$this->conf = $configLoader;

		// create a stomp client for listening to Mapper messages coming in
		$stompFactory = new Factory($loop);
		$this->stompClient = $stompFactory->createClient();

		if($initialise) {
			$this->initialise();
		}
	}

	/**
	 * Retrieve all the messages that need to be scheduled for delivery at a later time.
	 */
	protected function initialise() {

		$v = $this->verboser;
		$self = $this;
		$fMsg = new MessageFactory(null);

		/** Connect to stomp via the provided loop */
		$this->stompClient->connect()->then(function($client) use ($v, $self, $fMsg) {
			// this closure is called when stomp successfully connects
			/** @var Client $client */
			$v->v("Schedule connected to STOMP, checking for messages");

			// make sure our endpoint exists
			$mqc = new Connector();
			$mqc->declareQueue(Scheduler::QUEUE_SCHEDULE);
			$mqc->bindQueue(Scheduler::QUEUE_SCHEDULE, '*.' . Notif::TOPIC_APPLICATION);

			/** Subscribe to the queue set aside for the scheduler */
			$client->subscribeWithAck('/amq/queue/'.Scheduler::QUEUE_SCHEDULE, 'client',
				function($frame, $ackResolver) use ($v, $self, $fMsg) {
					// this closure is called when a message is received in the defined queue
					/**
					 * @var Frame $frame
					 * @var AckResolver $ackResolver
					 */

					try {

						$message = $fMsg->reconstitute($frame->body);

						// we can't do squat if the Mapper didn't send us a message in the right format
						if(!$message instanceof Internal) {
							$v->v("Could not decode '$frame->body' to schedule event", '!');
							// this message is unparseable, so we ACK it to make it go away
							$ackResolver->ack();
							return;
						}

						// only a few Models actually have schedule strategies, failure is ok
						// each module strategy knows how to deal with its own events
						$instance = $self->getModule(get_class($message->getObject()));

						$fTrigger = new TriggerFactory(
							$instance, $message->getObject(), $message->getInitiator(), $message->getDiff()
						);

						// ask the instance for a list of events for which to create triggers
						$events = $instance->generateTriggers($message->getMethod(), $message->getObject());

						// build a list of triggers out of the list of events, and schedule them
						$self->scheduleNotifications($fTrigger->build($events));

					}
					catch(ScheduleException $e) {
						// we're happy to ignore this, it just means no scheduling strategy exists for this Model
//						$v->v($e->getMessage(), '*');
						// this message is unparseable, so we'll eventually ACK it to make it go away
					}
					catch(CompressionException $e) {
						$v->v($e->getMessage(), '!');
					}

					// handled. away with you.
					$ackResolver->ack();
				}
			);

		});

		foreach($this->knownModules as $module) {
			try {
				$instance = $this->getModule($module);
				// ok, we have a moduleinterface now
				$this->verboser->v("Spooling triggers for '$module' from database");

				$pretriggers = $instance->spoolTriggersFromDatabase();
				// iterate across the events as attached to their respective Model objects
				foreach($pretriggers as $pretrigger) {

					/** @var PreTrigger $pretrigger */
					$events = $pretriggers->offsetGet($pretrigger);

					try {
						// construct the trigger factory on a case-by-case
						$fTrigger = new TriggerFactory(
							$instance, $pretrigger->getObject(), $pretrigger->getUser(), null
						);
						// construct triggers and schedule them, disregarding any expired triggers
						$this->scheduleNotifications($fTrigger->build($events, true));
					}
					catch(ScheduleException $e) {

					}
				}
			}
			catch(ScheduleException $e) {
				$this->verboser->v($e->getMessage(), '*');
			}
		}

	}

	public function run() {
		$this->loop->run();
	}

	public function stop() {
		$this->loop->stop();
	}


	/**
	 * Convenience method for scheduling notifications for multiple triggers.
	 *
	 * @param Trigger[] $triggers
	 */
	public function scheduleNotifications($triggers) {
		foreach($triggers as $trigger) {
			$this->scheduleNotification($trigger);
		}
	}


	/**
	 * Schedule a notification based on the given trigger.
	 * Idempotent method.
	 *
	 * @param Trigger $trigger
	 */
	public function scheduleNotification($trigger) {

		$timestamp = $trigger->getTimestamp();
		$model = $trigger->getModel();
		$module = get_class($trigger->getModuleNotifier());
		$label = $trigger->getLabel();

		$interval = $timestamp - time();
		$notif = $this->notif;
		$v = $this->verboser;

		/**
		 * This closure will be called from the EventLoop at the appointed time, or immediately if the time has elapsed.
		 *
		 * TODO put this into a fork
		 *
		 * @param TimerInterface $timer
		 * @throws \Exception
		 */
		$publish = function($timer) use (&$notif, $trigger, $v) {

			foreach($trigger->getCallbacks() as $callback) {
				/*
				 * dynamic message construction for broadcast.
				 * different people can receive different messages from the one event
				 * $callback will be a trigger method from an implementation of ModuleInterface
				 *    - by default, is in the form "notify{Roletype}{Trigger}"
				 */

				try {

					/** @var MessageCollection $mCollection */
					$mCollection = call_user_func($callback,
						$trigger->getModel(), $trigger->getUser(), $trigger->getDiff()
					);

					/* attempt some sort of type-safety */

					// method not implemented or nothing to send, this is fine
					if(is_null($mCollection)) {
						unset($mCollection);
						continue;
					}

					// check that an array was returned
					if(!$mCollection instanceof MessageCollection) {
						$v->v("Invalid callback response, must be instance of Schoolbox\\Notif\\Message\\MessageCollection", '!');
						unset($mCollection);
						continue;
					}

					// if there are no messages, just ignore this callback and move on
					if(!$mCollection->hasMessages()) {
						unset($mCollection);
						continue;
					}

					$count = 0;

					// iterate across the messages supplied and send them
					foreach($mCollection->generateExternalMessages($notif, $trigger->getTimestamp()) as $message) {
						// send them all!
						$message->setContext(array('user' => $trigger->getUser()->getId()));
						$sent = $message->send();
						$count += $sent ? 1 :0;
					}

					unset($mCollection);

					$v->v(sprintf("%s message%s broadcast", $count, $count == 1 ? '' : 's'), '<');

				}
				catch(\Exception $e) {
					// don't know what exceptions will be thrown here, so for now, acknowledge and rethrow
					$v->v(sprintf("%s -> %s",get_class($e), $e->getMessage()), '!');
					throw $e;
				}
			}
		};

		// an explicitly null interval is a request to unset that trigger (regardless of whether or not it exists)
		// TODO fix this
		if(is_null($interval)) {
			$this->unschedule($module, $label, $model);
			return;
		}

		// publish and return immediately if the event is now or in the past
		if($interval <= ModuleNotifierInterface::IMMEDIATELY) {
			$this->verboser->v("Immediately firing trigger '$module::$label'");
			$publish(null);
			return;
		}

		// schedule the future event
		$this->schedule($module, $label, $model, $interval, $publish);

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
		$instance = new $class($this->conf, new MessageFactory($this->notif));
		if(!$instance instanceof ModuleNotifierInterface) {
			throw new ScheduleException("Notifer class '$class' found, but not an instance of ModuleNotifierInterface.");
		}
		return $instance;

	}

} 