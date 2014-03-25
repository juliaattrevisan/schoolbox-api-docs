<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message\External;


use DateTime;
use Schoolbox\Notif\Message\External;
use Schoolbox\Notif\Message\Message;

class TargetedMessage extends External {

	protected $targets = array();

	/**
	 * Set the targets of this message.
	 *
	 * @param $array
	 */
	public function setTargets($array) {
		$this->targets = $array;
	}

	public function getTargets() {
		return $this->targets;
	}

	/**
	 * @return bool
	 */
	public function send() {
		// cannot send if there is no notifier to pass through
		if(is_null($this->notif)) {
			return false;
		}

		foreach($this->targets as $target) {
			foreach($this->topics as $topic) {
				$self = serialize($this);
				$topicString = sprintf('%s%s.%s', $this->topicPrepend, $target, $topic);
				$this->notif->setTopic($topicString)->log($this->severity, $self, $this->context);
			}
		}

		return true;
	}



	/**
	 * Return the array representation of this message.
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'body'      => $this->body->toArray(),
			'timestamp' => $this->datetime->format('U'),
			'params'    => $this->params,
			'targets'   => $this->targets,
		);
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
		$this->targets = $data['targets'];

		// cannot resend a message that has been received and unserialized
		$this->severity = null;
		$this->topics = array();
		$this->notif = null;

	}


}