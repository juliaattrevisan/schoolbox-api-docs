<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Serializable;

/**
 * Class Message
 *
 * @deprecated this class continues to work, but use Alaress\Async\Notification\Instance for more flexibility
 * @package Schoolbox\Notif\Message
 */
class Message implements Serializable {

	/** @var MessageBody */
	protected $body;

	/** @var string */
	protected $icon;

	/** @var string */
	protected $href;

	/** @var string */
	protected $meta;


	/**
	 * @deprecated this class continues to work, but use Alaress\Async\Notification\Instance for more flexibility
	 * @param string $text
	 * @param string $icon
	 * @param string $href
	 * @param array  $meta
	 */
	public function __construct($text, $icon, $href, $meta = array()) {
		$this->body = new MessageBody($text);
		$this->icon = $icon;
		$this->href = $href;
		$this->meta = $meta;
	}

	public function getMessageString() {
		return $this->body->getMessageString();
	}

	public function getTokenList() {
		return $this->body->getTokenList();
	}

	public function getIcon() {
		return $this->icon;
	}

	public function getHref() {
		return $this->href;
	}

	/**
	 * Return metadata from this message
	 *
	 * @param string $field
	 * @return string
	 */
	public function getMeta($field = '') {
		if($field) {
			return isset($this->meta[$field]) ? $this->meta[$field] : '';
		}
		return $this->meta;
	}

	/**
	 * String representation of object
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return json_encode($this->toArray());
	}

	/**
	 * Provide the array representation of this message
	 *
	 * @return array
	 */
	public function toArray() {
		return array(
			'body' => $this->body->getMessageString(),
			'icon' => $this->icon,
			'href' => $this->href,
			'meta' => $this->meta
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
		$this->meta = isset($data['meta']) ? $data['meta'] : array();
	}

}
