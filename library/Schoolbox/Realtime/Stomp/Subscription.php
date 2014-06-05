<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use PhpAmqpLib\Message\AMQPMessage;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use RuntimeException;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Verboser;

class Subscription {


	/** @var Verboser */
	public $pipeManager;

	/** @var int */
	protected $id;

	/** @var int */
	protected $userId;

	/** @var Client */
	protected $client;

	/** @var Message[] */
	protected $messages;

	/** @var string */
	protected $destination;

	/**
	 * @param Client $client
	 * @param int $userId
	 * @param PipeManager $pipeManager
	 */
	public function __construct($client, $userId, $pipeManager) {

		$this->messages = array();
		$this->client = $client;
		$this->pipeManager = $pipeManager;
		$this->userId = $userId;
		$self = $this;

		$this->destination = "/amq/queue/".UserQueue::getUserUnreadQueueName($userId);

		// subscribe to the user's unread queue and push these immediately to the Pipe Manager
		$this->id = $this->client->subscribeWithAck($this->destination, 'client-individual',
			function ($frame, $ackResolver) use ($self, $pipeManager, $userId) {
				// this closure is executed every time a message is sent to the client based on the given subscription
				/** @var AckResolver $ackResolver */

				if($pipeManager->onAMQP($self->frame2amqp($frame), $userId)) {
					$self->addMessage($frame, $ackResolver);
				}
			}, array('persistent' => true)
		);

	}


	/**
	 * Returns the STOMP ID of this subscription
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}


	/**
	 * Returns the endpoint to which this subscription is attached
	 *
	 * @return string
	 */
	public function getDestination() {
		return $this->destination;
	}


	/**
	 * Register a message that we can acknowledge later
	 *
	 * @param Frame $frame
	 * @param AckResolver $ackResolver
	 */
	public function addMessage($frame, $ackResolver) {
		$amqpMessageId = $frame->getHeader('amqp-message-id');
		$this->messages[$amqpMessageId] = new Message($frame, $ackResolver);
		$this->pipeManager->v("Attached resolver for $amqpMessageId");
	}


	/**
	 * Push all the notifications currently UNACK'd by this subscription to the user
	 */
	public function push() {
		foreach($this->messages as $stompMsg) {
			// these will be buffered and forwarded to all clients for this user
			$this->pipeManager->onAMQP($this->frame2amqp($stompMsg->frame), $this->userId);
		}
	}


	/**
	 * ACK messages
	 *
	 * To acknowledge a message, you have to nacknowledge every other message that is sitting unacknowledged.
	 * As often as possible, an array of message IDs should be passed to this method rather than a single message.
	 *
	 * Returns the array of IDs that were SUCCESSFULLY acknowledged
	 *
	 * @param array $amqpMessageIds
	 * @return array
	 */
	public function acceptMessages($amqpMessageIds) {

		$success = array();

		if(is_string($amqpMessageIds)) {
			$amqpMessageIds = array($amqpMessageIds);
		}

		foreach($this->messages as $msgId => $stompMessage) {
			try {
				if(in_array($msgId, $amqpMessageIds)) {
					$this->pipeManager->v("Attempting ACK of $msgId");
					$stompMessage->ack();
					$success[$msgId] = true;
				}
				else {
					$this->pipeManager->v("Attempting NACK of $msgId");
					$stompMessage->nack();
				}
			}
			catch(RuntimeException $e) {
				// the message in question has already been [N]ACK'd
				$this->pipeManager->v("Caught Runtime Exception, message '$msgId' already N/ACK'd", '*');
			}
		}

		if(count($success)) {
			foreach(array_keys($success) as $amqpMessageId) {
				unset($this->messages[$amqpMessageId]);
				$this->pipeManager->v("Accepted $amqpMessageId");
			}
			return array_keys($success);
		}
		else {
			$this->pipeManager->v("Did not find any matching messages", '!');
			return array();
		}
	}


	/**
	 * @param Frame $stompFrame
	 * @return AMQPMessage
	 */
	public function frame2amqp($stompFrame) {
		$properties = $stompFrame->headers;
		// STOMP likes to do things its own way...
		$properties['message_id'] = $properties['amqp-message-id'];
		return new AMQPMessage($stompFrame->body, $properties);
	}


	/**
	 * Returns the list of messages currently UNACK'd in this subscription.
	 *
	 * @return AMQPMessage[]
	 */
	public function getMessages() {
		$messages = array();

		foreach($this->messages as $amqpMessageId => $stompMessage) {
			$messages[$amqpMessageId] = $this->frame2amqp($stompMessage->frame);
		}

		return $messages;
	}

} 