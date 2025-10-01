<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Pipe;

use Predis\ClientInterface;
use Predis\CommunicationException;
use Ratchet\Session\Serialize\HandlerInterface;
use Schoolbox\MultiInstance\ConfigRepository;
use Schoolbox\MultiInstance\ConnectorRepository;
use Schoolbox\MultiInstance\RedisClientRepository;
use Schoolbox\Realtime\SessionException;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Session validator used by pipe manager.
 */
class SessionValidator
{
    /**
     * A cache of user IDs, keyed by instance and then username.
     *
     * @var array<string, array<string, int>>
     */
    private array $userIdCache = [];

    /**
     * Constructor
     *
     * @param RedisClientRepository $redisClientRepository Repository for Redis clients for all instances served by this
     *                                                     node.
     * @param HandlerInterface      $handler               Session serialization handler: required for decoding session
     *                                                     data without actually creating the session.
     * @param ConnectorRepository   $connectorRepository   Repository for database connections for all instances served
     *                                                     by this node: required for converting a username to a user ID
     *                                                     for a given instance.
     * @param ConfigRepository      $configRepository      Repository for configs for all instances served by this node:
     *                                                     required in order to determine each node's session expiry
     *                                                     time.
     */
    public function __construct(
        private RedisClientRepository $redisClientRepository,
        private HandlerInterface $handler,
        private ConnectorRepository $connectorRepository,
        private ConfigRepository $configRepository,
    ) {
    }

    /**
     * Find a user's ID in a given instance, from the value of their remember
     * token.
     *
     * @param string $instanceId The instance ID to search within.
     * @param string $rememberMe The user's remember me token.
     * @return int
     * @throws SessionException       if the user wasn't found
     * @throws CommunicationException if Redis could not be communicated with
     */
    public function findUserIdByRememberMe(string $instanceId, string $rememberMe): int
    {
        $username = $this->findData($instanceId, $rememberMe, 'remember-me token', '');

        if (!isset($this->userIdCache[$instanceId][$username])) {
            // Not in cache: query database
            $id = (int)$this->connectorRepository->fetchConnectorForInstance($instanceId)
                ->executeQuery(
                    'SELECT `id` FROM `user` WHERE `username` = :username LIMIT 0, 1',
                    ['username' => $username]
                )
                ->fetchColumn();
            if ($id === 0) {
                throw new SessionException('User not found');
            }
            $this->userIdCache[$instanceId][$username] = $id;
        }

        return $this->userIdCache[$instanceId][$username];
    }

    /**
     * Find a user's ID in a given instance, from a session ID associated with
     * that user.
     *
     * @param string $instanceId The instance ID to search within.
     * @param string $sessionId  The user's session ID.
     * @return int
     * @throws SessionException       if the session could not be found or decoded
     * @throws CommunicationException if Redis could not be communicated with
     */
    public function findUserIdBySessionId(string $instanceId, string $sessionId): int
    {
        try {
            $session = $this->handler->unserialize(
                $this->findData($instanceId, $sessionId, 'sessionID', PipeManager::REDIS_SESSION_PREFIX_BASE . $instanceId . ':')
            );
        } catch (\RuntimeException $e) {
            throw new SessionException('Could not decode session', 0, $e);
        }

        // check a user ID exists
        // (note: the user object in the session is not guaranteed to decode)
        if (empty($session['id'])) {
            throw new SessionException('No valid user found in session');
        }

        // check the session hasn't gone stale
        $sessionLength = $this->configRepository->fetchConfigForInstance($instanceId)->get('session_length');
        if (empty($session['lasttime']) || time() - $session['lasttime'] > $sessionLength) {
            throw new SessionException('Session has expired');
        }

        return (int)$session['id'];
    }

    /**
     * Find data in Redis.
     *
     * @param string $instanceId
     * @param string $token
     * @param string $label
     * @param string $prefix
     * @return string
     * @throws SessionException       if no token provided, or nothing found for token
     * @throws CommunicationException if Redis could not be communicated with
     * @noinspection PhpDocRedundantThrowsInspection
     */
    private function findData(
        string $instanceId,
        #[SensitiveParameter] string $token,
        string $label,
        string $prefix,
    ): string {
        if (!$token) {
            throw new SessionException("No $label provided on which to search.");
        }

        // this has the potential to throw CommunicationException?
        $redisResponse = $this->redisClientRepository->fetchForInstance($instanceId)->get($prefix . $token);
        if (!isset($redisResponse)) {
            throw new SessionException("No session found for given $label.");
        }

        return $redisResponse;
    }
}
