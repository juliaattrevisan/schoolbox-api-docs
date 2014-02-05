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
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Message\CompressionException;
use Schoolbox\Notif\Message\Internal;
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

						// give me a list of things to schedule
						$self->scheduleNotifications($instance->generateTriggers($message));
					}
					catch(ScheduleException $e) {
						// we're happy to ignore this, it just means no scheduling strategy exists for this Model
						$v->v($e->getMessage(), '*');
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
				$this->verboser->v("Spooling triggers for $module from database");
				foreach($instance->spoolTriggersFromDatabase() as $trigger) {
					$this->scheduleNotification($trigger);
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
		 * This closure will be called from the EventLoop at the appointed time
		 *
		 * @param TimerInterface $timer
		 */
		$publish = function($timer) use (&$notif, $trigger, $v) {

			foreach($trigger->getCallbacks() as $callback) {
				/*
				 * dynamic message construction for broadcast.
				 * different people can receive different messages from the one event
				 * $callback will be a trigger method from an implementation of ModuleInterface
				 */

				$broadcast = call_user_func($callback, $trigger->getModel(), $trigger->getUser(), $trigger->getDiff());

				if(!count($broadcast)) {
					continue;
				}

				$context = array('user' => $trigger->getUser()->getId());

				$count = 0;

				foreach($broadcast as $msg => $topics) {
					foreach($topics as $topic) {

						// TODO figure out what level this is meant to be logged at
						$notif->setTopic($topic)->log(Logger::NOTICE, $msg, $context);
						$count++;

					}
				}

				$v->v(sprintf("%s message%s broadcast", $count, $count == 1 ? '' : 's'));
			}
		};

		// publish and return immediately if the event is now or in the past
		if($interval <= ModuleNotifierInterface::IMMEDIATELY) {
			$publish(null);
			return;
		}

		$this->schedule($module, $label, $model, $interval, $publish);

	}


	/**
	 * Check whether or not a timer is already set for this event and model, and if not, set one for the appointed time
	 *
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @param int $interval
	 * @param callable $publish
	 */
	protected function schedule($module, $label, $model, $interval, $publish) {
		// check whether or not a timer has already been set for this event
		if($this->isScheduled($module, $label, $model)) {
			return;
		}

		// if not, register a timer
		$timer = $this->loop->addTimer($interval, $publish);
		$this->timers[$module][$label][sha1(serialize($model))] = $timer;

	}


	/**
	 * Return whether or not a timer is set for this event for this model
	 *
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return bool
	 */
	protected function isScheduled($module, $label, $model) {
		return isset($this->timers[$module][$label][sha1(serialize($model))]);
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

} 