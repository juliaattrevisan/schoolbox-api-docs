<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;

use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\Header;
use Ratchet\ConnectionInterface;


/**
 * This class encapsulates a Socket (pipe) and its associated information
 *
 * Class Pipe
 * @package Schoolbox\Realtime
 */
class Pipe implements PipeInterface {

	/**
	 * @param ConnectionInterface $conn
	 * @return string
	 */
	public static function getSocketKeyForConnection($conn) {
		return static::getHeader($conn, 'sec-websocket-key');
	}

	/** @var \Ratchet\ConnectionInterface */
	protected $socketConnection;

	/** @var int */
	protected $userId;

	/** @var string */
	protected $sessionId;

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

		// only do this once
		if(!$this->sessionId) {
			$cookieString = static::getHeader($this->socketConnection, 'cookie');

			preg_match_all('`([A-Za-z]+)=([^;]+)`', $cookieString, $matches);

			$cookies = array_combine($matches[1], $matches[2]);

			if(isset($cookies[PipeManager::PHP_SESSION_PREFIX])) {
				$this->sessionId = $cookies[PipeManager::PHP_SESSION_PREFIX];
			}
		}
		return $this->sessionId;
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
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * @param int $id
	 */
	public function setUserId($id) {
		$this->userId = $id;
	}

	protected static function getHeader($conn, $header) {
		/** @var EntityEnclosingRequest $rq */
		$rq = $conn->WebSocket->request;
		if(($value = $rq->getHeader($header)) instanceof Header) {
			$token = reset($value->toArray());
			return (string)$token;
		}
		// otherwise header not found
		return '';
	}

} 