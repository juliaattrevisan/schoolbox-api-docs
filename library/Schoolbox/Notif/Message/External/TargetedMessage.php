<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message\External;


use Schoolbox\Notif\Message\External;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Notif;

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


}