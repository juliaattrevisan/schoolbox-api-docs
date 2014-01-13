<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Ratchet\ConnectionInterface;
use Schoolbox\User;
use Symfony\Component\HttpFoundation\Session\Session;


/**
 * This class encapsulates a Socket (pipe) and its associated information
 *
 * Class Pipe
 * @package Schoolbox\Realtime
 */
class Pipe {

	/**
	 * @param ConnectionInterface $conn
	 * @return string
	 */
	public static function getSocketKeyForConnection($conn) {
		/** @var EntityEnclosingRequest $rq */
		$rq = $conn->WebSocket->request;
		$token = reset($rq->getHeader('sec-websocket-key')->toArray());
		return (string)$token;

	}

	/** @var \Ratchet\ConnectionInterface */
	protected $socketConnection;

	/** @var User */
	protected $user;

	/**
	 * @param ConnectionInterface $conn
	 */
	public function __construct($conn) {
		$this->socketConnection = $conn;
		$this->user = null;
	}

	/**
	 * Returns the Sec-Websocket-Key header of this websocket (unique connection identifier)
	 * @return string
	 */
	public function getSocketKey() {
		return self::getSocketKeyForConnection($this->socketConnection);
	}

	/**
	 * Returns the session id of this websocket, if it has one.
	 *
	 * @return string
	 */
	public function getSession() {
		if(isset($this->socketConnection->Session)) {
			/** @var Session $session */
			$session = $this->socketConnection->Session;

			// this line is (somehow, inexplicably) necessary to cache the sessionId... -_-
			$session->all();
			return $session->getId() ?: '';
		}

		return '';

	}

	/**
	 * Returns the remote address of the connection of this pipe.
	 *
	 * @return string
	 */
	public function getRemoteAddress() {
		return $this->socketConnection->remoteAddress ?: '0.0.0.0';
	}

	public function close() {
		$this->socketConnection->close();
	}

	/**
	 * @param string $data
	 * @return self
	 */
	public function send($data) {
		$this->socketConnection->send($data);
		return $this;
	}


	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @param User $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

} 