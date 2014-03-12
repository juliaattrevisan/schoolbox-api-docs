<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\MQ;


use EOTC\ConfigMapper;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\NotifException;

class Connector {

	const PRIMARY_EXCHANGE      = 'sb.%s.tx';
	const PRIMARY_TYPE          = 'topic';

	const SECONDARY_NAME_PREFIX = 'sb.%s.ux.';
	const SECONDARY_TYPE        = 'fanout';

	const PASSIVE     = false;
	const DURABLE     = true;  // exchanges and queues survive server restart
	const AUTO_DELETE = false; // exchanges and queues are not deleted when final client disconnects
	const INTERNAL    = false;
	const EXCLUSIVE   = false; // more than one connection allowed per queue

	const NO_LOCAL    = false;
	const NO_ACK      = false;
	const NO_WAIT     = false;

	const MESSAGE_PERSIST = 2; // save to disk
	const MESSAGE_EXPIRY  = '1209600000'; // 1000 * 60 * 60 * 24 * 14 = 2 weeks in millis

	/**
	 * @var AMQPConnection
	 */
	protected $conn;

	/**
	 * @var AMQPChannel[]
	 */
	protected $channels;

	/** @var IConfigLoader */
	protected $conf;

	public function __construct() {
		// will implicitly load a connection and set up an exchange-declared channel
		$this->getChannel();
		$this->conf = ConfigMapper::getInstance();
	}

	/**
	 * Establishes a new AMQP connection if it doesn't already exist.
	 * @return AMQPConnection
	 */
	protected function connection() {
		if(!$this->conn instanceof AMQPConnection) {
			$this->conn = new AMQPConnection(
				$this->conf->get('amqp_host'),
				$this->conf->get('amqp_port'),
				$this->conf->get('amqp_user'),
				$this->conf->get('amqp_pass')
			);
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
				self::getPrimaryExchange(), self::PRIMARY_TYPE, self::PASSIVE, self::DURABLE, self::AUTO_DELETE, self::INTERNAL
			);
		}
		return $this->channels[$idkey];
	}

	/**
	 * Declare an exchange
	 *
	 * @param string $name
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function declareExchange($name, $channelId = null) {

		return $this->getChannel($channelId)->exchange_declare(
			$name, self::SECONDARY_TYPE, self::PASSIVE, self::DURABLE, self::AUTO_DELETE, self::INTERNAL
		);
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
	 * @param string $exchangeName
	 * @param string $routingKey
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function bindExchange($exchangeName, $routingKey, $channelId = null) {
		return $this->getChannel($channelId)->exchange_bind($exchangeName, self::getPrimaryExchange(), $routingKey);
	}


	/**
	 * Bind a routing key to a particular queue.
	 *
	 * @param $queueName
	 * @param $routingKey
	 * @param $exchange
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function bindQueue($queueName, $routingKey, $exchange = '', $channelId = null) {
		$exchange = $exchange ?: self::getPrimaryExchange();
		$this->getChannel($channelId)->queue_bind($queueName, $exchange, $routingKey);
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
		$this->getChannel($channelId)->queue_unbind($queueName, self::getPrimaryExchange(), $routingKey);
		return $this->getChannel($channelId);
	}

	/**
	 * Unbind a routing key from a particular exchange.
	 *
	 * @param string $exchangeName
	 * @param string $routingKey
	 * @param string $channelId
	 * @return AMQPChannel
	 */
	public function unbindExchange($exchangeName, $routingKey, $channelId = null) {
		$this->getChannel($channelId)->exchange_unbind(self::getPrimaryExchange(), $exchangeName, $routingKey);
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
	 * Sit. and. Listen.
	 *
	 * @param string $channelId
	 */
	public function listen($channelId = null) {
		$c = $this->getChannel($channelId);
		while(count($c->callbacks)) {
			$c->wait();
		}
		$c->close();
	}

	/**
	 * Mark the message identified by the given delivery tag as ACK.
	 *
	 * @param AMQPMessage $message
	 * @param null $channelId
	 */
	public function acceptMessage($message, $channelId = null) {
		$this->getChannel($channelId)->basic_ack($message->delivery_info['delivery_tag']);
	}

	/**
	 * @param AMQPMessage[] $messages
	 * @param string $channelId
	 */
	public function acceptMessages($messages, $channelId = null) {
		foreach($messages as $m) {
			$this->acceptMessage($m, $channelId);
		}
	}

	/**
	 * @param AMQPMessage[] $messages
	 * @param bool $requeue
	 * @param string $channelId
	 */
	public function rejectMessages($messages, $requeue = true, $channelId = null) {
		foreach($messages as $m) {
			$this->rejectMessage($m, $requeue, $channelId);
		}
	}


	/**
	 * Mark the message identified by the given delivery tag as rejected.
	 *
	 * @param AMQPMessage $message
	 * @param bool $requeue
	 * @param string $channelId
	 */
	public function rejectMessage($message, $requeue = true, $channelId = null) {
		$this->getChannel($channelId)->basic_reject($message->delivery_info['delivery_tag'], $requeue);
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

			$properties = array(
				'message_id'    => sha1(sprintf('msg-%s-%s-%s', $routingKey, time(), rand())),
				'delivery_mode' => self::MESSAGE_PERSIST,
				'timestamp'     => time(),
				'expiration'    => self::MESSAGE_EXPIRY
			);

			$message = new AMQPMessage($message, $properties);
		}
		$this->getChannel($channelId)->basic_publish($message, self::getPrimaryExchange(), $routingKey);
		return $this->getChannel($channelId);
	}

	/**
	 * Internal function for returning the top message from a queue. Messages are set to unacknowledged.
	 *
	 * @param string $queueName
	 * @param string $channelId
	 * @return AMQPMessage
	 */
	public function readFromQueue($queueName, $channelId = null) {
		return $this->getChannel($channelId)->basic_get($queueName, false);
	}

	public static function getPrimaryExchange() {
		return sprintf(self::PRIMARY_EXCHANGE, INSTANCE_ID);
	}

	public static function getSecondaryExchangePrefix() {
		return sprintf(self::SECONDARY_NAME_PREFIX, INSTANCE_ID);
	}

} 