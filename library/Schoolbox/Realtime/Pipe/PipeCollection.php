<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Predis\Client;
use Predis\CommunicationException;
use Schoolbox\Realtime\SessionException;
use Schoolbox\Realtime\Verboser;

class PipeCollection {

	/** @var Pipe[] */
	protected $pipesBySocketKey;

	/** @var Pipe[][] */
	protected $pipesBySessionId;

	/** @var Pipe[][] */
	protected $pipesByUserId;

	/** @var Verboser */
	protected $v;

	/** @var Client */
	protected $redis;

	public function __construct($redis, $v) {
		$this->pipesBySocketKey = array();
		$this->pipesBySessionId = array();
		$this->pipesByUserId    = array();
		$this->redis = $redis;
		$this->v = $v;
	}

	/**
	 * Register this pipe connection
	 *
	 * @param Pipe $pipe
	 * @throws SessionException
	 */
	public function registerPipe($pipe) {
		$socketKey = $pipe->getSocketKey();
		$sessionId = $pipe->getSession();

		try {
			if(!$sessionId) {
				throw new SessionException("No session, no user.");
			}

			$this->pipesBySocketKey[$socketKey] = $pipe;
			$this->pipesBySessionId[$sessionId][$socketKey] = $pipe;
			$this->v->v("LINK       '$socketKey' -> SESSION '$sessionId'");

			if($userId = $this->findUserBySession($sessionId, $pipe->getRemoteAddress(), 2)) {
				$pipe->setUserId($userId);
				$this->pipesByUserId[$userId][$socketKey] = $pipe;
				$this->v->v("LINK       '$socketKey' -> USER    '$userId'");
			}
		}
		catch(SessionException $e) {
			$this->unregisterPipe($socketKey);
			throw $e;
		}
	}

	/**
	 * @param string $socketKey
	 */
	public function unregisterPipe($socketKey) {
		if($this->containsPipe($socketKey)) {
			$pipe = $this->pipesBySocketKey[$socketKey];

			if($userId = $pipe->getUserId()) {
				unset($this->pipesByUserId[$userId][$socketKey]);
			}

			if($sessionId = $pipe->getSession()) {
				unset($this->pipesBySessionId[$sessionId][$socketKey]);
			}

			unset($this->pipesBySocketKey[$socketKey]);
		}
		else {
			$this->v->v("Ignoring closure of '$socketKey'");
		}

	}

	/**
	 * Extracts a user by ID from a Redis session and returns the associated User object, or
	 * null if the user cannot be located.
	 *
	 * @param string $sessionId
	 * @param string $pipeIp
	 * @param int $retriesRemaining
	 * @throws \Schoolbox\Realtime\SessionException
	 * @throws \Predis\CommunicationException
	 * @return int
	 */
	protected function findUserBySession($sessionId, $pipeIp = null, $retriesRemaining = 0) {
		if(!$sessionId) {
			throw new SessionException("No sessionID provided on which to search.");
		}

		$redisResponse = '';

		try {
			$redisResponse = $this->redis->executeCommand(
				$this->redis->createCommand('get', array(PipeManager::REDIS_SESSION_PREFIX.$sessionId))
			);
		}
		catch(CommunicationException $e) {
			// redis timed out, this attempt should have reawoken it

			if($retriesRemaining <= 0) {
				// if we've run out of retries, rethrow the exception
				throw $e;
			}

			// recurse
			$this->findUserBySession($sessionId, $pipeIp, $retriesRemaining - 1);
		}

		if($redisResponse) {
			if(!preg_match('`id\|s:[0-9]+:"([0-9]+)";`', $redisResponse, $matches)) {
				throw new SessionException("User not found");
			}

			if(is_numeric($userId = $matches[1])) {
				return $userId;
			}
		}
		throw new SessionException("User ID not found in session.");
	}

	/**
	 * @param $socketKey
	 * @return bool
	 */
	public function containsPipe($socketKey) {
		return array_key_exists($socketKey, $this->pipesBySocketKey);
	}

	/**
	 * @param $userId
	 * @return int
	 */
	public function userPipeCount($userId) {
		return array_key_exists($userId, $this->pipesByUserId) ? count($this->pipesByUserId[$userId]) : 0;
	}

	/**
	 * @param $sessionId
	 * @return int
	 */
	public function sessionPipeCount($sessionId) {
		return array_key_exists($sessionId, $this->pipesBySessionId) ? count($this->pipesBySessionId[$sessionId]) : 0;
	}

	/**
	 * @param $socketKey
	 * @return Pipe
	 */
	public function getPipeBySocketKey($socketKey) {
		if($this->containsPipe($socketKey)) {
			return $this->pipesBySocketKey[$socketKey];
		}
		return null;
	}

	/**
	 * @param $userId
	 * @return Pipe[]
	 */
	public function getPipesByUserId($userId) {
		if($this->userPipeCount($userId)) {
			return $this->pipesByUserId[$userId];
		}
		return array();
	}

	/**
	 * @param $sessionId
	 * @return Pipe[]
	 */
	public function getPipesBySessionId($sessionId) {
		if($this->sessionPipeCount($sessionId)) {
			return $this->pipesBySessionId[$sessionId];
		}
		return array();
	}
} 