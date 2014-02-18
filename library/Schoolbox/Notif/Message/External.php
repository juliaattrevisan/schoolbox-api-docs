<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use DateTime;
use Schoolbox\Notif\Notif;

class External implements MessageInterface {

	/** @var Message  */
	protected $body;
	/** @var DateTime */
	protected $datetime;
	/** @var array */
	protected $params;

	/** @var Notif */
	protected $notif;
	/** @var array  */
	protected $topics;
	/** @var int  */
	protected $severity;
	/** @var array */
	protected $context = array();

	/**
	 * @param Notif $notif
	 * @param array $topics
	 * @param int $severity
	 * @param int $timestamp
	 * @param Message $message
	 * @param array $params
	 */
	public function __construct($notif, $topics, $severity, $timestamp, $message, $params) {
		$this->notif = $notif;
		$this->topics = $topics;
		$this->severity = $severity;
		$this->body = $message;
		$this->datetime = DateTime::createFromFormat('U', $timestamp);
		$this->params = $params;
	}

	/**
	 * @return bool
	 */
	public function send() {
		// cannot send if there is no notifier to pass through
		if(is_null($this->notif)) {
			return false;
		}

		foreach($this->topics as $topic) {
			$self = serialize($this);
			$this->notif->setTopic($topic)->log($this->severity, $self, $this->context);
		}
		return true;

	}


	/**
	 * Return the serialized form of this message.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->serialize();
	}


	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return json_encode(array(
			'body'      => $this->body->toArray(),
			'timestamp' => $this->datetime->format('U'),
			'params'    => $this->params
		));
	}


	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 */
	public function unserialize($serialized) {
		$data = json_decode($serialized, true);
		$body = $data['body'];
		$this->body = new Message($body['body'], $body['icon'], $body['href']);
		$this->datetime = DateTime::createFromFormat('U', $data['timestamp']);
		$this->params = $data['params'];

		// cannot resend a message that has been received and unserialized
		$this->severity = null;
		$this->topics = array();
		$this->notif = null;

	}

	/**
	 * @param array $context
	 * @return void
	 */
	public function setContext($context = array()) {
		$this->context = array_merge($this->context, $context);
	}


}