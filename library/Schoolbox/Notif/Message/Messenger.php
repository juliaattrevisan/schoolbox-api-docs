<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Schoolbox\Notif\Message\External\BroadcastMessage;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\NotifException;
use SplObjectStorage;

class Messenger {

	const TYPE_BROADCAST = 'broadcast';
	const TYPE_TARGETED  = 'targeted';

	/** @var SplObjectStorage */
	protected $splStore;


	public function __construct() {
		$this->splStore = new SplObjectStorage();
	}

	/**
	 * @param string|array $targets - a discreet list of intended recipients, in topic format (user.id, campus.id, etc)
	 * @param string|array $topics  - the topic or topics on which this is to be sent
	 * @param int $severity         - the severity at which this is to be sent
	 * @param Message $msg          - the message to be sent
	 * @param array $params         - the params to be bound to this message instance
	 * @param array $context        - any additional context to be supplied
	 */
	public function bindTargeted($targets, $topics, $severity, $msg, $params = array(), $context = array()) {
		$this->bind($msg, $severity, $topics, $targets, $params, $context);
	}

	/**
	 * Bind a message, ready for transmission to a user's queues.
	 *
	 * @param string|array $topics  - the topic or topics on which this is to be broadcast
	 * @param int $severity         - the severity at which this is to be broadcast
	 * @param Message $msg          - the message to be broadcast
	 * @param array $params         - the params to be bound to this message instance
	 * @param array $filter         - an optional filter for who is to receive this message
	 * @throws \Schoolbox\Notif\NotifException
	 */
	public function bindBroadcast($topics, $severity, $msg, $params = array(), $filter = array()) {
		$this->bind($msg, $severity, $topics, array(), $params, $filter);
	}

	/**
	 * @param Message $message
	 * @param int $severity
	 * @param array $topics
	 * @param array $targets
	 * @param array $params
	 * @param array $context
	 * @throws \Schoolbox\Notif\NotifException
	 */
	protected function bind($message, $severity, $topics = array(), $targets = array(), $params = array(), $context = array()) {
		if(count(array_diff(array_keys($params), $message->getTokenList()))) {
			throw new NotifException("Parameters supplied must exactly match the :tokens in the message string.");
		}

		// cast string to array if required
		$topics = (array)$topics;
		$targets = (array)$targets;
		$paramHash = sha1(json_encode($params));

		// insert the message object if it's not already in our collection
		if(!$this->splStore->contains($message)) {
			$this->splStore->attach($message, array());
		}

		// retrieve the instances that have already been set
		$instances = $this->splStore->offsetGet($message);

		// add this instance
		$instances[$paramHash] = array(
			'severity' => $severity,
			'params'   => $params,
			'topics'   => $topics,
			'context'  => $context,
			'targets'  => empty($targets) ? false : $targets
		);

		// reattach the message and its extended list of instances
		$this->splStore->attach($message, $instances);
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
		$externalMessages = array();

		foreach($this->splStore as $msg) {
			/** @var Message $msg */
			$instances = $this->splStore->offsetGet($msg);
			foreach($instances as $instance) {

				if($instance['targets'] === false) {
					$e = new BroadcastMessage($notif, $instance['topics'], $instance['severity'], $timestamp, $msg, $instance['params']);
				}
				else {
					$e = new TargetedMessage($notif, $instance['topics'], $instance['severity'], $timestamp, $msg, $instance['params']);
					$e->setTargets($instance['targets']);
				}
				$e->setContext($instance['context']);
				$externalMessages[] = $e;
			}
		}

		return $externalMessages;
	}

} 