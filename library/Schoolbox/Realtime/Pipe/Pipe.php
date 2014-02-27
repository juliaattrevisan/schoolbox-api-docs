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
use Ratchet\ConnectionInterface;
use Symfony\Component\HttpFoundation\Session\Session;


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
		$cookies = static::getHeader($this->socketConnection, 'cookie');

		// got session
		if(($i = strpos($cookies, PipeManager::PHP_SESSION_PREFIX)) !== false) {
			$len = strlen(PipeManager::PHP_SESSION_PREFIX);
			$start = $i + $len;
			$end = strpos($cookies, ';', $start) - $len;
			$session = substr($cookies, $start, $end);

			return $session;
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
		$token = reset($rq->getHeader($header)->toArray());
		return (string)$token;

	}

} 