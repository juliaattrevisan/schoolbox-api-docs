<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis;

use Alaress\Cache as BaseCache;
use Predis\ClientInterface;

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
        // Is it in redis already?
        $redisKey = $this->buildRedisKey($key);

        // Try to fetch from Redis (but not if invalidated)
        if (!$invalidateCache && $this->redis->exists($redisKey)) {
            $result = json_decode($this->redis->get($redisKey), true);
        } else {
            $result = parent::get($key, $fetchArgs, $invalidateCache);
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
