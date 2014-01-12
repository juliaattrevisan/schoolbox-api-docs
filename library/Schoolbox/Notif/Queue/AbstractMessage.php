<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use PhpAmqpLib\Message\AMQPMessage;

abstract class AbstractMessage {

	protected $msg;

	/**
	 * @param AMQPMessage $amqpMessage
	 */
	public function __construct($amqpMessage) {
		$this->msg = $amqpMessage;
	}

	/**
	 * Retieve a property of the AMQPMessage object.
	 *
	 * @param $property
	 * @return int
	 */
	protected function get($property) {
		return $this->msg->has($property) ? $this->msg->get($property) : 0;
	}

	/**
	 * The unix timestamp of when this message was published.
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return $this->get('timestamp');
	}

	/**
	 * The sha1 hash ID of this message.
	 *
	 * @return int
	 */
	public function getId() {
		return $this->get('message_id');
	}

	/**
	 * The unix timestamp of when this message will automatically dequeue.
	 *
	 * @return int
	 */
	public function getExpiry() {
		return $this->get('timestamp') + ($this->get('expiration') / 1000);
	}

	/**
	 * The body text of this message.
	 *
	 * @return string
	 */
	public function getBody() {
		return $this->msg->body;
	}

	/**
	 * The AMQPMessage object that this class encapsulates.
	 *
	 * @return AMQPMessage
	 */
	public function getAmqpMessage() {
		return $this->msg;
	}


	public function toArray() {
		return array(
			'id' => $this->getId(),
			'timestamp' => (int)$this->getTimestamp(),
			'expiry' => (int)$this->getExpiry(),
			'body' => json_decode($this->getBody(), true),
			'status' => $this->isRead() ? 'read' : 'unread'
		);
	}

	/**
	 * Returns whether or not this message is marked as read.
	 *
	 * @return bool
	 */
	public abstract function isRead();

} 