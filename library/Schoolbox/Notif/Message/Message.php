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
use Serializable;

class Message implements Serializable {

	/** @var MessageBody */
	protected $body;

	/** @var string */
	protected $icon;

	/** @var string */
	protected $href;


	/**
	 * @param string $text
	 * @param string $icon
	 * @param string $href
	 */
	public function __construct($text, $icon, $href) {
		$this->body = new MessageBody($text);
		$this->icon = $icon;
		$this->href = $href;
	}

	public function getMessageString() {
		return $this->body->getMessageString();
	}

	public function getTokenList() {
		return $this->body->getTokenList();
	}

	/**
	 * String representation of object
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return json_encode($this->toArray());
	}

	public function toArray() {
		return array(
			'body' => $this->body->getMessageString(),
			'icon' => $this->icon,
			'href' => $this->href,
		);
	}


	/**
	 * Constructs the object
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized) {
		$data = json_decode($serialized, true);
		$this->body = new MessageBody($data['body']);
		$this->icon = $data['icon'];
		$this->href = $data['href'];
		$this->time = DateTime::createFromFormat('U', $data['time']);
	}


}