<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe\MultiInstance;

use Countable;
use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Pipe\Pipe;
use Schoolbox\Realtime\SessionException;

class PipeCollection implements Countable
{
    /**
     * Pipes grouped by socket key.
     * (Socket keys should be unique across all instance IDs.)
     *
     * @var array<string, Pipe>
     */
    protected array $pipesBySocketKey = [];

    /**
     * Pipes grouped by instance ID, session ID and then socket key.
     *
     * @var array<string, array<string, array<string, Pipe>>>
     */
    protected array $pipesBySessionId = [];

    /**
     * Pipes grouped by instance ID, user ID and then socket key.
     *
     * @var array<string, array<int, array<string, Pipe>>>
     */
    protected array $pipesByInstanceAndUserId = [];

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
        $socketKey  = $pipe->getSocketKey();
        $sessionId  = $pipe->getSession();
        $remember   = $pipe->getCookie('remember');
        $instanceId = $pipe->getInstanceId();

        // Extract user ID
        try {
            if (!($sessionId || $remember)) {
                throw new SessionException('No session or remember-me token, no user.');
            }

            // Try the users session, if that fails, fall back to remember me
            try {
                $userId = $this->validator->findUserIdBySessionId($instanceId, $sessionId);
            } catch (SessionException $e) {
                $userId = $this->validator->findUserIdByRememberMe($instanceId, $remember);
            }
        } catch (SessionException $e) {
            $this->unregisterPipe($socketKey);
            $pipe->close();
            // rethrow
            throw $e;
        }

        $pipe->setUserId($userId);

        // index pipe in triplicate
        // Socket key is presumed to be unique between instances:
        // session ID could be presumed to be unique as well.
        // User ID is not unique between instances.
        $this->pipesBySocketKey[$socketKey]                               = $pipe;
        $this->pipesBySessionId[$instanceId][$sessionId][$socketKey]      = $pipe;
        $this->pipesByInstanceAndUserId[$instanceId][$userId][$socketKey] = $pipe;

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
            $pipe       = $this->pipesBySocketKey[$socketKey];
            $instanceId = $pipe->getInstanceId();

            if ($userId = $pipe->getUserId()) {
                unset($this->pipesByInstanceAndUserId[$instanceId][$userId][$socketKey]);
                if (empty($this->pipesByInstanceAndUserId[$instanceId][$userId])) {
                    unset($this->pipesByInstanceAndUserId[$instanceId][$userId]);
                }
            }

            if ($sessionId = $pipe->getSession()) {
                unset($this->pipesBySessionId[$instanceId][$sessionId][$socketKey]);
                if (empty($this->pipesBySessionId[$instanceId][$sessionId])) {
                    unset($this->pipesBySessionId[$instanceId][$sessionId]);
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
        return array_key_exists($userId, $this->pipesByInstanceAndUserId)
            ? count($this->pipesByInstanceAndUserId[$userId])
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
     * @param string $instanceId
     * @param int $userId
     * @return Pipe[]
     */
    public function getPipesByInstanceAndUserId(string $instanceId, int $userId): array
    {
        return $this->pipesByInstanceAndUserId[$instanceId][$userId] ?? [];
    }


    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->pipesBySocketKey);
    }
}
