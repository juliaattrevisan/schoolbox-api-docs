<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use Alaress\Async\AMQP\AMQPResponseHandlerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use RuntimeException;

class Subscription {

    const STOMP_QUEUE_PREFIX = '/amq/queue/';

	/** @var AMQPResponseHandlerInterface */
	public $handler;

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

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param int                          $userId
     * @param string                       $destination
     * @param Client                       $client
     * @param AMQPResponseHandlerInterface $handler
     * @param LoggerInterface              $log
     */
    public function __construct($userId, $destination, Client $client, AMQPResponseHandlerInterface $handler, LoggerInterface $log)
    {
		$this->messages = array();
		$this->client = $client;
		$this->handler = $handler;
		$this->userId = $userId;
        $this->log = $log;
		$self = $this;

		$this->destination = $destination;

		// subscribe to the user's unread queue and push these immediately to the Pipe Manager
		$this->id = $this->client->subscribeWithAck($this->destination, 'client-individual',
			function ($frame, $ackResolver) use ($self, $handler, $userId, $log) {
				// this closure is executed every time a message is sent to the client based on the given subscription
				/** @var AckResolver $ackResolver */

				$log->info("Received message to push to user $userId", array('char' => '+'));

				if($handler->onAMQP($self->frame2amqp($frame), $userId)) {
					$self->addMessage($frame, $ackResolver);
				}
			}, array('persistent' => true)
		);

	}


    /**
     * Destroy this subscription
     */
    public function __destruct() {
        $this->unsubscribe();
    }

    /**
     * Close this subscription
     */
    public function unsubscribe() {
        if($this->client instanceof Client) {
            $this->client->unsubscribe($this->id);
        }

        // unload
        unset($this->pipeManager);
        unset($this->id);
        unset($this->userId);
        unset($this->client);
        unset($this->messages);
        unset($this->destination);
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
    public function addMessage($frame, $ackResolver)
    {
        $msg = new Message($frame, $ackResolver);

        $amqpMessageId = $msg->getAmqpId();

        $this->messages[$amqpMessageId] = $msg;

        $this->log->debug("Attached resolver for $amqpMessageId");
    }


	/**
	 * Push all the notifications currently UNACK'd by this subscription to the user
	 */
	public function push() {
		foreach($this->messages as $stompMsg) {
			// these will be buffered and forwarded to all clients for this user
			$this->handler->onAMQP($this->frame2amqp($stompMsg->frame), $this->userId);
		}
	}


	/**
	 * ACK messages
	 *
	 * As often as possible, an array of message IDs should be passed to this method rather than a single message.
	 *
	 * Returns the array of IDs that were SUCCESSFULLY acknowledged
	 *
	 * @param string|array $amqpMessageIds
	 * @return array
	 */
	public function acceptMessages($amqpMessageIds) {

		$success = array();

		if(is_string($amqpMessageIds)) {
			$amqpMessageIds = array($amqpMessageIds);
		}

		foreach($this->messages as $amqpMessageId => $stompMessage) {
			try {
				if(in_array($amqpMessageId, $amqpMessageIds)) {
					$this->log->debug("Attempting ACK of $amqpMessageId");
					$stompMessage->ack();
					$success[$amqpMessageId] = true;
				}
			}
			catch(RuntimeException $e) {
				// the message in question has already been [N]ACK'd
				$this->log->error("Caught Runtime Exception, message '$amqpMessageId' already N/ACK'd");
			}
		}

        $accepted = array_keys($success);

		if(count($accepted)) {
			foreach($accepted as $amqpMessageId) {
				unset($this->messages[$amqpMessageId]);
				$this->log->info("Accepted $amqpMessageId");
			}
			return $accepted;
		}
		else {
			$this->log->critical("Did not find any matching messages");
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