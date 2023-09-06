<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Pipe\MultiInstance;

use Countable;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Pipe\Pipe;
use Schoolbox\Realtime\SessionException;

/**
 * Multi-instance aware collection of pipes.
 */
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

    /**
     * Constructor
     *
     * @param SessionValidator $validator Validator for session data: a pipe's session must be verified to be valid.
     * @param LoggerInterface  $log       Logger
     */
    public function __construct(private SessionValidator $validator, private LoggerInterface $log)
    {
    }

    /**
     * Register this pipe within the collection.
     *
     * @param Pipe $pipe
     * @throws SessionException if the session in `$pipe` is not valid
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
            } catch (SessionException) {
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
     * Deregister a pipe within the collection.
     *
     * @param string $socketKey The pipe's socket key.
     * @return void
     */
    public function unregisterPipe(string $socketKey): void
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
     * Does this collection contain a pipe with the given socket key?
     *
     * @param string $socketKey The pipe's socket key.
     * @return bool
     */
    public function containsPipe(string $socketKey): bool
    {
        return array_key_exists($socketKey, $this->pipesBySocketKey);
    }

    /**
     * How many pipes does this user have?
     *
     * @param string $instanceId
     * @param int    $userId
     * @return int
     */
    public function userPipeCount(string $instanceId, int $userId): int
    {
        return count($this->getPipesByInstanceAndUserId($instanceId, $userId));
    }

    /**
     * Fetch a pipe by its socket key if it exists.
     *
     * @param string $socketKey
     * @return Pipe|null
     */
    public function getPipeBySocketKey($socketKey): ?Pipe
    {
        if ($this->containsPipe($socketKey)) {
            return $this->pipesBySocketKey[$socketKey];
        }

        return null;
    }

    /**
     * Fetch all pipes for this instance and user ID, keyed by socket key.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return array<string, Pipe>
     */
    public function getPipesByInstanceAndUserId(string $instanceId, int $userId): array
    {
        return $this->pipesByInstanceAndUserId[$instanceId][$userId] ?? [];
    }

    /**
     * How many open sockets does this collection have?
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->pipesBySocketKey);
    }
}
