<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\Cache;

use Alaress\Codec\ICodec;
use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\HasExpirationTimestampInterface;
use Predis\ClientInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A PSR-6 implementation with a Redis backend.
 *
 * Differs from https://github.com/php-cache/predis-adapter in the following
 * ways:
 * * delegates encoding/decoding to a codec, instead of using
 *   `serialize`/`unserialize`, as we've had these issues with
 *   serializing/unserializing objects:
 *   * object structure changes between cache write and read
 *   * serialized objects may expose private data
 * * delegates namespacing to a separate class, instead of using the
 *   https://github.com/php-cache/namespaced-cache wrapper
 *   * this wrapper hashes the cache key, thus making the Redis report unable
 *     to detect usages of this key
 */
class ItemPool implements CacheItemPoolInterface
{
    /**
     * Redis client
     */
    private ClientInterface $redis;

    /**
     * Codec, for serializing/unserializing objects.
     */
    private ICodec $codec;

    /**
     * Key generator, for determining the key via which this should be stored in
     * Redis.
     */
    private IKeyGenerator $keyGenerator;

    /**
     * Deferred items.
     *
     * This implementation assumes that if `getItem` hits a deferred item, then
     * the deferred item is returned.
     *
     * @var array<string, CacheItemInterface>
     */
    private array $deferredItems = [];

    /**
     * Constructor
     *
     * @param ClientInterface $redis
     * @param ICodec          $codec
     * @param IKeyGenerator   $keyGenerator
     */
    public function __construct(ClientInterface $redis, ICodec $codec, IKeyGenerator $keyGenerator)
    {
        $this->redis        = $redis;
        $this->codec        = $codec;
        $this->keyGenerator = $keyGenerator;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * @param string $key
     * @return CacheItemInterface
     * @throws InvalidArgumentException if `$key` isn't valid
     */
    public function getItem($key)
    {
        $this->assertKeyValid($key);
        if (isset($this->deferredItems[$key])) {
            return $this->deferredItems[$key];
        }

        // Query redis for the encoded value
        $actualKey = $this->keyGenerator->generateKey($key);
        $exists    = $this->redis->exists($actualKey);
        if ($exists) {
            // return the stored item
            $item = $this->redis->get($actualKey);
            if (isset($item)) {
                return new CacheItem($key, true, $this->codec->decode($item));
            }
        }
        // return a miss
        return new CacheItem($key, false, null);
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param array<string> $keys
     * @return array<string, CacheItemInterface>
     * @throws InvalidArgumentException if any `$keys` aren't valid
     */
    public function getItems(array $keys = [])
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }
        return $result;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException if `$key` isn't valid
     */
    public function hasItem($key)
    {
        $this->assertKeyValid($key);
        if (isset($this->deferredItems[$key])) {
            return true;
        }
        return (bool)$this->redis->exists($this->keyGenerator->generateKey($key));
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     */
    public function clear()
    {
        // Find all keys which match the prefix.
        // Can't quite figure this part out yet.
        return false;
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException if `$key` isn't valid
     */
    public function deleteItem($key)
    {
        $this->assertKeyValid($key);
        if (!$this->hasItem($key)) {
            return false;
        }

        if ($this->redis->del($this->keyGenerator->generateKey($key))) {
            unset($this->deferredItems[$key]);
            return true;
        }
        return false;
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param array $keys
     * @return bool
     * @throws InvalidArgumentException if any `$keys` aren't valid
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        $key    = $this->keyGenerator->generateKey($item->getKey());
        $cached = $this->codec->encode($item->get());
        $ttl    = null;
        if ($item instanceof HasExpirationTimestampInterface) {
            // set an expiry
            $expiry = $item->getExpirationTimestamp();
            if ($expiry !== null && $expiry > 0) {
                $ttl = $expiry - time();
                if ($ttl <= 0) {
                    return false;
                }
            }
        }

        if (isset($ttl)) {
            $result = $this->redis->setex($key, $ttl, $cached);
        } else {
            $result = $this->redis->set($key, $cached);
        }
        return (bool)$result;
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferredItems[$this->keyGenerator->generateKey($item->getKey())] = $item;
        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     */
    public function commit()
    {
        foreach ($this->deferredItems as $key => $item) {
            if (!$this->save($item)) {
                return false;
            }
            unset($this->deferredItems[$key]);
        }
        return true;
    }

    /**
     * Asserts `$key` is valid.
     *
     * Currently, that just means "is it a string"?
     *
     * @param mixed $key
     * @throws InvalidArgumentException if $key isn't valid
     */
    private function assertKeyValid($key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be a string: received %s', gettype($key)));
        }
    }
//
//    /**
//     * Determines the Redis key for the item's actual key.
//     *
//     * @param string $key
//     * @return string
//     */
//    private function determineRedisKey(string $key): string
//    {
//        return sprintf('accessToken:%s:%s', INSTANCE_ID, $key);
//    }
}
