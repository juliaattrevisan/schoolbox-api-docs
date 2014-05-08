<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\InstantTrigger;

class Factory {

	/** @var string */
	protected $msgBody = '';
	/** @var array */
	protected $params = array();
	/** @var string  */
	protected $icon = '';
	/** @var string  */
	protected $href = '';
	/** @var int  */
	protected $timestamp = 0;
	/** @var array */
	protected $meta = array();
	/** @var AbstractModuleNotifier */
	protected $notifier = null;

	/**
	 * Construct a dummy message. It has no topic, target, severity or context, so it won't go anywhere.
	 *
	 * @return External\TargetedMessage
	 */
	public function build() {
		if($this->notifier) {
			return new External\TargetedMessage(
				null,
				new Message(
					$this->msgBody, $this->icon, $this->href, $this->meta
				),
				new Instance\Instance(
					$this->params, 0, array()
				),
				new InstantTrigger(
					'', $this->timestamp, null, $this->notifier
				)
			);
		}
		else {
			return new External\TargetedMessage(
				null,
				new Message(
					$this->msgBody, $this->icon, $this->href, $this->meta
				),
				new Instance\Instance(
					$this->params, 0, array()
				)
			);
		}

	}


	/**
	 * @param string $href
	 * @return self
	 */
	public function setHref($href) {
		$this->href = $href;
		return $this;
	}

	/**
	 * @param string $icon
	 * @return self
	 */
	public function setIcon($icon) {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * @param string $msgBody
	 * @param bool $clear
	 * @return self
	 */
	public function setMsgBody($msgBody, $clear = true) {
		$this->msgBody = $msgBody;
		if($clear) {
			// reset fields
			$this->params = array();
			$this->meta = array();
		}
		return $this;
	}

	/**
	 * @param string $field
	 * @param string $content
	 * @return self
	 */
	public function setMeta($field, $content) {
		$this->meta[$field] = $content;
		return $this;
	}

	/**
	 * @param \Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier $notifier
	 * @return self
	 */
	public function setNotifier($notifier) {
		$this->notifier = $notifier;
		return $this;
	}

	/**
	 * @param array $params
	 * @return self
	 */
	public function setParams($params) {
		$this->params = $params;
		return $this;
	}

	/**
	 * @param $name
	 * @param $text
	 * @param string $href
	 * @return self
	 */
	public function bindParam($name, $text, $href = '') {
		$this->params[$name] = $href ? array($text, $href) : $text;
		return $this;
	}

	/**
	 * @param int $timestamp
	 * @return self
	 */
	public function setTimestamp($timestamp) {
		$this->timestamp = $timestamp;
		return $this;
	}

} 