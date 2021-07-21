<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Remember;

use Predis\ClientInterface;

/**
 * A mechanism for remembering a username which a user has logged in with.
 */
class Remember
{
    /**
     * The format of the Redis key in which to store the username.
     */
    private const REDIS_KEY_FORMAT = 'remember:%s:%s';

    /**
     * The instance ID of the current instance: required for namespacing the
     * remembered username in Redis.
     */
    private string $instanceId;

    /**
     * A Redis client, the username will be stored here.
     */
    private ClientInterface $redis;

    /**
     * Constructor
     *
     * @param string          $instanceId
     * @param ClientInterface $redis
     */
    public function __construct(string $instanceId, ClientInterface $redis)
    {
        $this->instanceId = $instanceId;
        $this->redis      = $redis;
    }

    /**
     * Retrieve a username given a key.
     *
     * @param string $key A key, previously returned by `rememberUsername`
     * @return string|null The username previously remembered
     */
    public function retrieveRememberedUsername(string $key): ?string
    {
        // First attempt: fetch from key "remember:$instanceId:$key"
        $username = $this->redis->get($this->generateRedisKey($key));
        if (isset($username)) {
            return $username;
        }

        // Second attempt: fetch from key "$key" and migrate to new key
        $username = $this->redis->get($key);
        if (isset($username)) {
            $this->redis->setex($this->generateRedisKey($key), $username, $this->redis->ttl($key));
            $this->redis->del($key);
            return $username;
        }

        // No user was found
        return null;
    }

    /**
     * Remember the given username.
     *
     * @param string $username The username to remember
     * @param int    $expiry   The amount of time to remember it for
     * @return string A key to pass into `retrieveRememberedUsername`.
     */
    public function rememberUsername(string $username, int $expiry): string
    {
        $key = $this->generateRandomKey();
        $this->redis->setex($this->generateRedisKey($key), $username, $expiry);
        return $key;
    }

    /**
     * Generate a random key for a remember me session.
     *
     * This will be used as a cookie value, and as the basis for a Redis key
     * (which will be namespaced by instance ID and purpose).
     *
     * @return string
     */
    private function generateRandomKey(): string
    {
        return sha1(microtime(true) . mt_rand(10000, 90000));
    }

    /**
     * Converts a random key into a key in which the user's username will be
     * stored.
     *
     * @param string $randomKey
     * @return string
     */
    private function generateRedisKey(string $randomKey): string
    {
        return sprintf(self::REDIS_KEY_FORMAT, $this->instanceId, $randomKey);
    }
}
