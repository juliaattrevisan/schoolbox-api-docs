<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\MQ;


use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use Schoolbox\Notif\NotifException;

class Connector {

	const EXCHANGE = 'sb_notifier';
	const TYPE     = 'topic';

	const PASSIVE     = false;
	const DURABLE     = true;
	const AUTO_DELETE = false;
	const INTERNAL    = false;
	const EXCLUSIVE   = false;

	const NO_LOCAL    = false;
	const NO_ACK      = false;
	const NO_WAIT     = false;

	/**
	 * @var AMQPConnection
	 */
	protected $conn;

	/**
	 * @var AMQPChannel[]
	 */
	protected $channels;

	public function __construct() {
		// will implicitly load a connection and set up an exchange-declared channel
		$this->getChannel();
	}

	/**
	 * Establishes a new AMQP connection if it doesn't already exist.
	 * @return AMQPConnection
	 */
	protected function connection() {
		if(!$this->conn instanceof AMQPConnection) {
			$this->conn = new AMQPConnection('localhost', 5672, 'guest', 'guest');
		}
		return $this->conn;
	}

	/**
	 * Closes this AMQP connection.
	 */
	public function closeConnection() {
		$this->conn->close();
	}

	/**
	 * Retrieves the channel of this connector, optionally by an ID.
	 *
	 * @param string $id
	 * @return AMQPChannel
	 */
	public function getChannel($id = null) {

		// can't use null as a key
		$idkey = $id ?: '_';

		if(!$this->channels[$idkey] instanceof AMQPChannel) {
			$this->channels[$idkey] = $this->connection()->channel($id);

			$this->channels[$idkey]->exchange_declare(
				self::EXCHANGE, self::TYPE, self::PASSIVE, self::DURABLE, self::AUTO_DELETE, self::INTERNAL
			);
		}
		return $this->channels[$idkey];
	}

	/**
	 * Declares a queue for use (which will create it if it does not already exist).
	 *
	 * @param string $name
	 * @param string $channelId
	 * @return AMQPChannel
	 * @throws NotifException
	 */
	public function declareQueue($name, $channelId = null) {

		$c = $this->getChannel($channelId);

		try {
			list($queueName, $messageCount, $consumerCount) = $c->queue_declare(
				$name, self::PASSIVE, self::DURABLE, self::EXCLUSIVE, self::AUTO_DELETE
			);
			if(!$queueName) {
				throw new NotifException("Unable to declare queue '$name'.");
			}
			return $c;
		}
		catch(AMQPProtocolChannelException $e) {
			throw new NotifException("Unable to declare queue '$name'.", 0, $e);
		}
	}

	/**
	 * Bind a routing key to a particular queue.
	 *
	 * @param $queueName
	 * @param $routingKey
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function bindQueue($queueName, $routingKey, $channelId = null) {
		$this->getChannel($channelId)->queue_bind($queueName, self::EXCHANGE, $routingKey);
		return $this->getChannel($channelId);
	}


	/**
	 * Unbind a routing key from a particular queue.
	 *
	 * @param $queueName
	 * @param $routingKey
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function unbindQueue($queueName, $routingKey, $channelId = null) {
		$this->getChannel($channelId)->queue_unbind($queueName, self::EXCHANGE, $routingKey);
		return $this->getChannel($channelId);
	}

	/**
	 * Set the callback for a particular queue.
	 *
	 * @param $queueName
	 * @param $callable
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function setConsumerCallback($queueName, $callable, $channelId = null) {
		$this->getChannel($channelId)->basic_consume(
			$queueName, '', self::NO_LOCAL, self::NO_ACK, self::EXCLUSIVE, self::NO_WAIT, $callable
		);
		return $this->getChannel($channelId);
	}

	/**
	 * Publish a message to a particular routing key.
	 *
	 * @param AMQPMessage|string $message
	 * @param string $routingKey
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function publish($message, $routingKey, $channelId = null) {
		// wrap message in object if it's not already
		if(!$message instanceof AMQPMessage) {
			$message = new AMQPMessage($message);
		}
		$this->getChannel($channelId)->basic_publish($message, self::EXCHANGE, $routingKey);
		return $this->getChannel($channelId);
	}

	/**
	 * Internal function for returning the top message from a queue
	 *
	 * @param string $queueName
	 * @param bool $markAsRead
	 * @param string $channelId
	 * @return AMQPMessage
	 */
	public function readQueue($queueName, $markAsRead, $channelId = null) {
		return $this->getChannel($channelId)->basic_get($queueName, !$markAsRead);
	}

	/**
	 * Retrieve the top message from a queue without dequeuing it
	 *
	 * @param string $queueName
	 * @param string $channelId
	 * @return AMQPMessage
	 */
	public function peekQueue($queueName, $channelId = null) {
		return $this->readQueue($queueName, false, $channelId);
	}

	/**
	 * Retrieve the top message from a queue and dequeue it
	 *
	 * @param $queueName
	 * @param null $channelId
	 * @return AMQPMessage
	 */
	public function popQueue($queueName, $channelId = null) {
		return $this->readQueue($queueName, true, $channelId);
	}

} 