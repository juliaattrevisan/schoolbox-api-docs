<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Exception;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Factory;
use React\Stomp\Protocol\Frame;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\CompressionException;
use Schoolbox\Notif\Message\Interceptor;
use Schoolbox\Notif\Message\Internal;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Schedule\Trigger\TriggerInterface;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * This class is meant to hold a list of notifications that are pending for broadcast, and at the appropriate time,
 * forward the message to RabbitMQ.
 *
 * Class Scheduler
 * @package Schoolbox\Realtime\Schedule
 */
class Scheduler extends AbstractScheduler {

	const MODULE_NS = 'Schoolbox\\Realtime\\Schedule\\Module\\';
	const QUEUE_SCHEDULE = '.sb.sched';

	/** @var array */
	protected static $knownModules = array(
		'\\Schoolbox\\Assessment\\Component\\SubmissionBox',
		 // register yo known module Notifiers here
	);


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
	 * Return the method that Workers will use to process this task
	 *
	 * @return string
	 */
	protected function getMethod() {
		return DefaultDispatchStrategy::TASK_PUBLISH;
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

} 