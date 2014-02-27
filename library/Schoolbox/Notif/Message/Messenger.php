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
use Schoolbox\Notif\NotifException;
use SplObjectStorage;

class Messenger {

	/** @var SplObjectStorage */
	protected $splStore;


	public function __construct() {
		$this->splStore = new SplObjectStorage();
	}


	/**
	 * Bind a message, ready for transmission to a user's queues.
	 *
	 * @param string|array $topics
	 * @param int $severity
	 * @param Message $msg
	 * @param array $params
	 * @throws \Schoolbox\Notif\NotifException
	 */
	public function bind($topics, $severity, $msg, $params = array()) {
		if(count(array_diff(array_keys($params), $msg->getTokenList()))) {
			throw new NotifException("Parameters supplied must exactly match the :tokens in the message string.");
		}

		// cast string to array if required
		$topics = (array)$topics;
		$paramHash = sha1(json_encode($params));

		// insert the message object if it's not already in our collection
		if(!$this->splStore->contains($msg)) {
			$this->splStore->attach($msg, array());
		}

		// retrieve the instances that have already been set
		$instances = $this->splStore->offsetGet($msg);

		// add this instance
		$instances[$paramHash] = array(
			'severity' => $severity,
			'params'   => $params,
			'topics'   => $topics
		);

		// reattach the message and its extended list of instances
		$this->splStore->attach($msg, $instances);
	}


	/**
	 * Whether or not this collection currently contains messages
	 *
	 * @return bool
	 */
	public function hasMessages() {
		return !!$this->splStore->count();
	}


	/**
	 * @param Notif $notif
	 * @param int $timestamp
	 * @return External[]
	 */
	public function generateExternalMessages($notif, $timestamp) {
		// don't even try if there's nothing there
		if(!$this->hasMessages()) {
			return array();
		}

		// array that we'll be returning
		$exts = array();

		foreach($this->splStore as $msg) {
			/** @var Message $msg */
			$instances = $this->splStore->offsetGet($msg);
			foreach($instances as $instance) {
				$exts[] = new External(
					$notif, $instance['topics'], $instance['severity'], $timestamp, $msg, $instance['params']
				);
			}
		}

		return $exts;
	}

} 