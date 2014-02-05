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
	 * @return array
	 */
	public function getBody() {
		$body = json_decode($this->msg->body, true);
		$body['status'] = $this->isRead() ? 'read' : 'unread';
		return $body;
	}

	/**
	 * The AMQPMessage object that this class encapsulates.
	 *
	 * @return AMQPMessage
	 */
	public function getAmqpMessage() {
		return $this->msg;
	}


	/**
	 * Convert this message to an array
	 *
	 * @param bool $detailed
	 * @return array
	 */
	public function toArray($detailed = false) {
		$body = json_decode($this->getBody(), true);
		$relative = new \Alaress_Date_Relative($body['datetime']['date']);

		$self = array(
			'id' => $this->getId(),
			'timestamp' => (int)$this->getTimestamp(),
			'body' => array(
				'message' => $body['message'],
				'context' => array(
					'icon' => isset($body['context']['icon']) ? $body['context']['icon'] : '',
					'href' => isset($body['context']['href']) ? $body['context']['href'] : '',
				),
			),
			'status' => $this->isRead() ? 'read' : 'unread'
		);

		if($detailed) {
			$self['expiry'] = (int)$this->getExpiry();
			// just include everything in the body
			$self['body'] = $body;
		}

		// relative time here regardless
		$self['body']['datetime']['date'] = $relative->getRelativeTime();

		return $self;
	}

	/**
	 * Returns whether or not this message is marked as read.
	 *
	 * @return bool
	 */
	public abstract function isRead();

} 