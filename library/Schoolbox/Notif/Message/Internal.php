<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Schoolbox\Notif\Notif;
use Schoolbox\User;

class Internal implements MessageInterface {


	protected $notif;
	protected $topics;
	protected $severity;

	protected $object;
	protected $diff;
	protected $method;
	protected $initiator;


	/**
	 * @param object $object
	 * @param array $diff
	 * @param User $initiator
	 * @param string $method
	 * @param int $severity
	 * @param array $topics
	 * @param Notif $notif
	 */
	public function __construct($object, $diff, $initiator, $method, $severity, $topics, $notif) {
		// publishing details
		$this->notif = $notif;
		$this->topics = $topics;
		$this->severity = $severity;

		// action details
		$this->object = $object;
		$this->diff = $diff;
		$this->method = $method;
		$this->initiator = $initiator;
	}

	/**
	 * Send messages.
	 *
	 * @return bool
	 */
	public function send() {
		// cannot send if there is no notifier to pass through
		if(is_null($this->notif)) {
			return false;
		}
		foreach($this->topics as $topic) {
			$self = serialize($this);
			$this->notif->setTopic($topic)->log($this->severity, $self);
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
		return serialize(array(
			'o' => $this->object,
			'd' => $this->diff,
			'm' => $this->method,
			'i' => $this->initiator,
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
		$array = unserialize($serialized);

		$this->object    = $array['o'];
		$this->diff      = $array['d'];
		$this->method    = $array['m'];
		$this->initiator = $array['i'];

		// cannot resend a message that has been received and unserialized
		$this->severity = null;
		$this->topics = array();
		$this->notif = null;
	}


	/* Get necessary details */
	public function getObject() {
		return $this->object;
	}

	public function getDiff() {
		return $this->diff;
	}

	public function getMethod() {
		return strtolower($this->method);
	}

	public function getInitiator() {
		return $this->initiator;
	}

} 