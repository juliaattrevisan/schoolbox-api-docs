<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis;

use Alaress\Cache as BaseCache;
use Predis\ClientInterface;
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
            // Avoid race condition by querying TTL and content in transaction
            [$ttl, $cachedResult] = $this->redis->transaction(function (MultiExec $tx) use ($redisKey) {
                $tx->ttl($redisKey)
                   ->get($redisKey);
            });

            switch ($ttl) {
                case -2:
                    // Key does not exist or has expired: refetch
                    break;

                case -1:
                    // Key exists and has no expiry: don't refetch
                    $cached = true;
                    break;

                default:
                    // Key exists but has an expiry: refetch if TTL > expiry
                    $cached = ($ttl <= $this->expiry);
            }
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
