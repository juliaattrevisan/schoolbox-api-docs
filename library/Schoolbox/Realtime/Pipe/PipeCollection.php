<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\SessionException;

class PipeCollection {

	/** @var Pipe[] */
	protected $pipesBySocketKey;

	/** @var array */
	protected $pipesBySessionId;

	/** @var array */
	protected $pipesByUserId;

	/** @var LoggerInterface */
	protected $log;


    /**
     * @param SessionValidator $validator
     * @param LoggerInterface $log
     */
    public function __construct($validator, $log) {
		$this->pipesBySocketKey = array();
		$this->pipesBySessionId = array();
		$this->pipesByUserId    = array();
		$this->validator = $validator;
		$this->log = $log;
	}

	/**
	 * Register this pipe connection
	 *
	 * @param Pipe $pipe
	 * @throws SessionException
	 */
	public function registerPipe(Pipe $pipe) {
		$socketKey = $pipe->getSocketKey();
		$sessionId = $pipe->getSession();
        $remember  = $pipe->getCookie('remember');

		try {
			if(!($sessionId || $remember)) {
				throw new SessionException("No session or remember-me token, no user.");
			}

			$this->pipesBySocketKey[$socketKey] = $pipe;
			$this->pipesBySessionId[$sessionId][$socketKey] = $pipe;
			$this->log->debug("LINK       '$socketKey' -> SESSION '$sessionId'");

            // give preference to remember-me, since it lasts longer (and doesn't throw exceptions)
            if (!$userId = $this->validator->findUserIdByRememberMe($remember)) {
                $userId = $this->validator->findUserIdBySessionId($sessionId);
            }

            $pipe->setUserId($userId);
            $this->pipesByUserId[$userId][$socketKey] = $pipe;
            $this->log->debug("LINK       '$socketKey' -> USER    '$userId'");
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
                if (empty($this->pipesByUserId[$userId])) {
                    unset($this->pipesByUserId[$userId]);
                }
			}

			if($sessionId = $pipe->getSession()) {
				unset($this->pipesBySessionId[$sessionId][$socketKey]);
                if (empty($this->pipesBySessionId[$sessionId])) {
                    unset($this->pipesBySessionId[$sessionId]);
                }
			}

			unset($this->pipesBySocketKey[$socketKey]);
		}
		else {
			$this->log->debug("Ignoring closure of '$socketKey'");
		}

	}

	/**
	 * @param string $socketKey
	 * @return bool
	 */
	public function containsPipe($socketKey) {
		return array_key_exists($socketKey, $this->pipesBySocketKey);
	}

	/**
	 * @param int $userId
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