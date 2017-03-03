<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;

use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\SessionException;

class PipeCollection
{

    /** @var Pipe[] */
    protected $pipesBySocketKey = [];

    /** @var Pipe[][] */
    protected $pipesBySessionId = [];

    /** @var Pipe[][] */
    protected $pipesByUserId    = [];

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param SessionValidator $validator
     * @param LoggerInterface  $log
     */
    public function __construct($validator, $log)
    {
        $this->validator = $validator;
        $this->log       = $log;
    }


    /**
     * Register this pipe connection
     *
     * @param Pipe $pipe
     * @throws SessionException
     * @throws CommunicationException
     */
    public function registerPipe(Pipe $pipe)
    {
        $socketKey = $pipe->getSocketKey();
        $sessionId = $pipe->getSession();
        $remember  = $pipe->getCookie('remember');

        try {
            if (!($sessionId || $remember)) {
                throw new SessionException('No session or remember-me token, no user.');
            }

            // give preference to remember-me, since it lasts longer (and doesn't throw exceptions)
            if (!$userId = $this->validator->findUserIdByRememberMe($remember)) {
                $userId = $this->validator->findUserIdBySessionId($sessionId);
            }
        } catch (SessionException $e) {
            $this->unregisterPipe($socketKey);
            // rethrow
            throw $e;
        }

        $pipe->setUserId($userId);

        // index pipe in triplicate
        $this->pipesBySocketKey[$socketKey]             = $pipe;
        $this->pipesBySessionId[$sessionId][$socketKey] = $pipe;
        $this->pipesByUserId[$userId][$socketKey]       = $pipe;

        $this->log->debug("LINK '$socketKey' -> USER '$userId' SESSION '$sessionId'");
    }


    /**
     * @param string $socketKey
     * @return void
     */
    public function unregisterPipe($socketKey)
    {
        if ($this->containsPipe($socketKey)) {
            $pipe = $this->pipesBySocketKey[$socketKey];

            if ($userId = $pipe->getUserId()) {
                unset($this->pipesByUserId[$userId][$socketKey]);
                if (empty($this->pipesByUserId[$userId])) {
                    unset($this->pipesByUserId[$userId]);
                }
            }

            if ($sessionId = $pipe->getSession()) {
                unset($this->pipesBySessionId[$sessionId][$socketKey]);
                if (empty($this->pipesBySessionId[$sessionId])) {
                    unset($this->pipesBySessionId[$sessionId]);
                }
            }

            unset($this->pipesBySocketKey[$socketKey]);
        } else {
            $this->log->debug("Ignoring closure of '$socketKey'");
        }
    }


    /**
     * @param string $socketKey
     * @return bool
     */
    public function containsPipe($socketKey)
    {
        return array_key_exists($socketKey, $this->pipesBySocketKey);
    }


    /**
     * @param int $userId
     * @return int
     */
    public function userPipeCount($userId)
    {
        return array_key_exists($userId, $this->pipesByUserId)
            ? count($this->pipesByUserId[$userId])
            : 0;
    }


    /**
     * @param string $sessionId
     * @return int
     */
    public function sessionPipeCount($sessionId)
    {
        return array_key_exists($sessionId, $this->pipesBySessionId)
            ? count($this->pipesBySessionId[$sessionId])
            : 0;
    }


    /**
     * @param string $socketKey
     * @return Pipe
     */
    public function getPipeBySocketKey($socketKey)
    {
        if ($this->containsPipe($socketKey)) {
            return $this->pipesBySocketKey[$socketKey];
        }

        return null;
    }


    /**
     * @param int $userId
     * @return Pipe[]
     */
    public function getPipesByUserId($userId)
    {
        if ($this->userPipeCount($userId)) {
            return $this->pipesByUserId[$userId];
        }

        return [];
    }


    /**
     * @param string $sessionId
     * @return Pipe[]
     */
    public function getPipesBySessionId($sessionId)
    {
        if ($this->sessionPipeCount($sessionId)) {
            return $this->pipesBySessionId[$sessionId];
        }

        return [];
    }
} 