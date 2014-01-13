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
use Schoolbox\Notif\Queue\UnreadMessage;
use Schoolbox\Realtime\Verboser;

class Subscription {


	/** @var Verboser */
	public $verboser;

	/** @var int */
	protected $id;

	/** @var Client */
	protected $client;

	/** @var Message[] */
	protected $messages;


	/**
	 * @param string $destination
	 * @param Client $client
	 * @param Verboser $verboser
	 */
	public function __construct($destination, $client, $verboser) {

		$this->messages = array();
		$this->client = $client;
		$this->verboser = $verboser;
		$self = $this;

		$this->id = $this->client->subscribeWithAck($destination, 'client',
			function ($frame, $ackResolver) use ($self) {
				// this closure is executed every time a message is sent to the client based on the given subscription
				$self->addMessage($frame, $ackResolver);
			});
	}

	public function getId() {
		return $this->id;
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
		$this->verboser->v("Attached resolver for $amqpMessageId");
	}

	/**
	 * ACK messages
	 *
	 * To acknowledge a message, you have to nacknowledge every other message that is sitting unacknowledged.
	 * As often as possible, an array of message IDs should be passed to this method rather than a single message.
	 *
	 * @param array $amqpMessageIds
	 * @return bool
	 */
	public function acceptMessages($amqpMessageIds) {

		$success = array();

		if(is_string($amqpMessageIds)) {
			$amqpMessageIds = array($amqpMessageIds);
		}

		foreach($this->messages as $msgId => $stompMessage) {

			if(in_array($msgId, $amqpMessageIds)) {
				$stompMessage->ack();
				$success[$msgId] = true;
			}
			else {
				$stompMessage->nack();
			}
		}

		if(count($success)) {
			foreach(array_keys($success) as $amqpMessageId) {
				unset($this->messages[$amqpMessageId]);
				$this->verboser->v("Accepted $amqpMessageId");
			}
			return true;
		}
		else {
			$this->verboser->v("Did not find any matching messages", '!');
			return false;
		}
	}


	/**
	 * Returns the list of messages currently UNACK'd in this subscription.
	 *
	 * @return UnreadMessage[]
	 */
	public function getMessages() {
		$messages = array();

		foreach($this->messages as $amqpMessageId => $stompMessage) {

			$properties = $stompMessage->frame->headers;
			// STOMP likes to do things its own way...
			$properties['message_id'] = $properties['amqp-message-id'];

			$messages[$amqpMessageId] = new UnreadMessage(new AMQPMessage(
				$stompMessage->frame->body, $properties
			));
		}

		return $messages;
	}

} 