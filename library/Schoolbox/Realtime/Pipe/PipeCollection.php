<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Countable;
use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\SessionException;

class PipeCollection implements Countable
{

    /** @var Pipe[] */
    protected $pipesBySocketKey = [];

    /** @var Pipe[][] */
    protected $pipesBySessionId = [];

    /** @var Pipe[][] */
    protected $pipesByUserId    = [];

    /** @var LoggerInterface */
    protected $log;

    /** @var SessionValidator */
    protected $validator;

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

            //Try the users session, if that fails, fall back to remember me
            try {
                $userId = $this->validator->findUserIdBySessionId($sessionId);
            } catch (SessionException $e) {
                $userId = $this->validator->findUserIdByRememberMe($remember);
            }
        } catch (SessionException $e) {
            $this->unregisterPipe($socketKey);
            $pipe->close();
            // rethrow
            throw $e;
        }

        $pipe->setUserId($userId);

        // index pipe in triplicate
        $this->pipesBySocketKey[$socketKey]             = $pipe;
        $this->pipesBySessionId[$sessionId][$socketKey] = $pipe;
        $this->pipesByUserId[$userId][$socketKey]       = $pipe;

        $sess = $sessionId ?: 'sessionless';

        $pipeCount = count($this->pipesBySocketKey);
        $this->log->debug("Registered $userId @ $socketKey ($sess) [pipes={$pipeCount}]");
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

            $pipeCount = count($this->pipesBySocketKey);
            $this->log->debug("Unregistered $socketKey [pipes={$pipeCount}]");
        } else {
            $pipeCount = count($this->pipesBySocketKey);
            $this->log->debug("Ignoring closure of $socketKey [pipes={$pipeCount}]");
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


    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->pipesBySocketKey);
    }
}
