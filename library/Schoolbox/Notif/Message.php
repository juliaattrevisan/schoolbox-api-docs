<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Schoolbox\Notif\Queue\AbstractMessage;

class Message {

	protected $msg;

	/**
	 * @param AbstractMessage $queueMessage
	 */
	public function __construct($queueMessage) {
		$this->msg = $queueMessage;
	}

	public function getId() {
		return $this->msg->getId();
	}

	public function getIcon() {
		$body = $this->msg->getBody();
		return $body['context']['icon'];
	}

	public function getText() {
		$body = $this->msg->getBody();
		return $body['message'];
	}

	public function getHref() {
		$body = $this->msg->getBody();
		return $body['context']['href'];
	}

	public function getTimestamp() {
		return $this->msg->getTimestamp();
	}

	public function toArray($detailed = false) {
		return $this->msg->toArray($detailed);
	}
} 