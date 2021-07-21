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
        // Search in the new key first
        $redisKey = $this->generateRedisKey($key);
        $username = $this->redis->get($redisKey);
        if (isset($username)) {
            return $username;
        }

        // Then search the old key, and migrate to the new key if the username
        // was found there
        $oldRedisKey = $this->generateOldRedisKey($key);
        $username    = $this->redis->get($oldRedisKey);
        if (isset($username)) {
            // Give the new key the same lifetime as the old one has remaining.
            // If somehow it expired in the moment between getting and checking:
            // return null
            $ttl = $this->redis->ttl($oldRedisKey);
            if ($ttl > 0) {
                $this->redis->setex($redisKey, $ttl, $username);
                $this->redis->del($oldRedisKey);
                return $username;
            }
        }

        // No user was found
        return null;
    }

    /**
     * Forget the remembered username.
     *
     * @param string $key A key, previously returned by `rememberUsername`
     */
    public function forgetRememberedUsername(string $key): void
    {
        // Forget both the old and new keys
        $this->redis->del([
            $this->generateRedisKey($key),
            $this->generateOldRedisKey($key),
        ]);
    }

    /**
     * Remember the given username.
     *
     * @param string $username The username to remember
     * @param int    $expiry   The number of seconds to remember for
     * @return string A key to pass into `retrieveRememberedUsername`.
     */
    public function rememberUsername(string $username, int $expiry): string
    {
        $key = $this->generateRandomKey();
        $this->redis->setex($this->generateRedisKey($key), $expiry, $username);
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

    /**
     * Convert a random key into the key in which the previous remember me
     * implementation stored the username.
     *
     * This is implemented for backward compatibility with the previous remember
     * me implementation.
     *
     * @param string $randomKey
     * @return string
     */
    private function generateOldRedisKey(string $randomKey): string
    {
        // The original implementation just used the random key as the Redis
        // key: this wasn't instance aware or prefixed.
        return $randomKey;
    }
}
