<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Alaress_Date_Format;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Message\Instance\Instance;

/**
 * Helper class to assist with extracting information in an easily-digestible way from a TargetedMessage
 *
 * Class Decorator
 * @package Schoolbox\Notif\Message
 */
class Decorator {

	/** @var Message */
	protected $message;

	/** @var Instance */
	protected $instance;

	/** @var int */
	protected $timestamp;

	/**
	 * @param TargetedMessage $targetedMessage
	 */
	public function __construct($targetedMessage) {
		if($targetedMessage instanceof TargetedMessage) {
			$this->setMessage($targetedMessage);
		}
	}

	/**
	 * @param TargetedMessage $targetedMessage
	 */
	public function setMessage($targetedMessage) {
		$this->message = $targetedMessage->getBody();
		$this->instance = $targetedMessage->getInstance();
		$this->timestamp = $targetedMessage->getTimestamp();
	}

	/**
	 * @return Alaress_Date_Format
	 */
	public function getAlaressDateFormat() {
		return new Alaress_Date_Format($this->timestamp, 'U');
	}

	/**
	 * @return string
	 */
	public function formatHtml() {
		$params = $this->instance->getParams();
		$msgString = $this->message->getMessageString();

		// no params, raw text
		if(!(is_array($params) && count($params))) {
			return $msgString;
		}

		foreach($params as $token => $value) {
			// text and href
			if(is_array($value) && count($value) == 2) {
				$value = array_values($value);
				$a = sprintf('<a href="%s">%s</a>', $value[1], $value[0]);
			}
			// just text
			elseif(is_string($value)) {
				$a = sprintf('<a>%s</a>', $value);
			}
			// nothing we recognise: skip dat!
			else {
				continue;
			}

			$match = ":$token";
			$msgString = str_replace($match, $a, $msgString);
		}

		return $msgString;
	}

	/**
	 * @param bool $unread
	 * @return string
	 */
	public function formatJson($unread = false) {
		return json_encode($this->formatArray($unread));
	}

	/**
	 * @param bool $unread
	 * @return array
	 */
	public function formatArray($unread = false) {
		return array(
			'body'      => $this->message->toArray(),
			'params'    => $this->instance->getParams(),
			'timestamp' => $this->timestamp,
			'status'    => $unread ? 'unread' : 'read'
		);
	}

	/**
	 * @return string
	 */
	public function getHref() {
		$array = $this->message->toArray();
		return $array['href'];
	}

	/**
	 * @return string
	 */
	public function getIcon() {
		$array = $this->message->toArray();
		return $array['icon'];
	}
}