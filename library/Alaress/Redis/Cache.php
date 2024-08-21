<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis;

use Alaress\Cache as BaseCache;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Predis\Transaction\MultiExec;

/**
 * A cache that uses Redis as its backend.
 */
class Cache extends BaseCache
{
    /**
     * @var ClientInterface
     */
    private $redis;


    /**
     * @var string
     */
    private $baseKey;


    /**
     * @var int
     */
    private $expiry;


    /**
     * Constructor
     *
     * @param callable        $fetch   Method for fetching value
     * @param ClientInterface $redis   Redis client
     * @param string          $baseKey Base key, for avoiding collisions
     * @param int             $expiry  How long does Redis hold cached values?
     */
    public function __construct(callable $fetch, ClientInterface $redis, string $baseKey, int $expiry = 0)
    {
        parent::__construct($fetch);
        $this->redis   = $redis;
        $this->baseKey = $baseKey;
        $this->expiry  = $expiry;
    }


    /**
     * @param string $key
     * @return $this
     */
    public function invalidate($key)
    {
        $this->redis->del([$this->buildRedisKey($key)]);
        parent::invalidate($key);
        return $this;
    }

    /**
     * Clears the cache.
     *
     * @return bool Always true for now.
     */
    public function clear(): bool
    {
        $keyspace = new Keyspace($this->redis, $this->buildRedisKey('*'));
        $keys     = [];
        foreach ($keyspace as $key) {
            $keys[] = $key;
        }
        $this->redis->del($keys);
        return true;
    }

    /**
     * @param int|string $key
     * @param array      $fetchArgs
     * @param bool       $invalidateCache
     * @return mixed
     */
    public function get($key, array $fetchArgs = [], $invalidateCache = false)
    {
        // Try to fetch the value from Redis (but not if invalidating)
        $redisKey     = $this->buildRedisKey($key);
        $cached       = false;
        $cachedResult = null;
        if ($invalidateCache) {
            $this->invalidate($key);
        } else {
            // Avoid race condition by querying cached status and result in transaction
            [$exists, $cachedResult] = $this->redis->transaction(function (MultiExec $tx) use ($redisKey): void {
                $tx->exists($redisKey)
                   ->get($redisKey);
            });
            $cached = (bool)$exists;
        }

        if ($cached) {
            $result = json_decode($cachedResult, true);
        } else {
            $result = $this->fetch($fetchArgs);
            if ($this->expiry > 0) {
                $this->redis->setex($redisKey, $this->expiry, json_encode($result));
            } else {
                $this->redis->set($redisKey, json_encode($result));
            }
        }
        return $result;
    }


    /**
     * Build Redis key out of `$key` and the `$baseKey` passed on construct.
     *
     * @param int|string $key
     * @return string
     */
    private function buildRedisKey($key): string
    {
        return "$this->baseKey.$key";
    }
}
