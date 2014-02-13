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

class MessageCollection {

	protected $messages = array();

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

		$msgHash   = sha1($msg->getMessageString());
		$paramHash = sha1(json_encode($params));


		if(!isset($this->messages[$msgHash])) {
			$this->messages[$msgHash] = array(
				'message' => $msg
			);
		}

		$this->messages[$msgHash]['instances'][$paramHash] = array(
			'severity' => $severity,
			'params'   => $params,
			'topics'   => $topics
		);
	}

	/**
	 * Remove a specific topic from the collection.
	 *
	 * @param $topic
	 * @return bool
	 */
	public function unbindTopic($topic) {
		$existed = isset($this->messages[$topic]);
		unset($this->messages[$topic]);
		return $existed;
	}

	/**
	 * Whether or not this collection currently contains messages
	 *
	 * @return bool
	 */
	public function hasMessages() {
		return !!count($this->messages);
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

		foreach($this->messages as $msg) {
			foreach($msg['instances'] as $instance) {
				$exts[] = new External(
					$notif, $instance['topics'], $instance['severity'], $timestamp, $msg['message'], $instance['params']
				);
			}
		}

		return $exts;

	}


} 