<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\Cache;

use Alaress\Codec\ICodec;
use Predis\ClientInterface;
use Predis\Collection\Iterator\SetKey;
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
     * The set key for this cache: keys are written to this set so that they
     * may be cleared efficiently.
     *
     * This may not be used as the cache key, as Redis can't store a value and
     * set in the one key.
     */
    public const SET_KEY = '__set';

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
    public function getItem($key): CacheItemInterface
    {
        $this->assertKeyValid($key);
        if (isset($this->deferredItems[$key])) {
            return $this->deferredItems[$key];
        }

        // Query redis for the encoded value
        $actualKey = $this->keyGenerator->generateKey($key);

        // Avoid race condition by querying existence and value in transaction
        $this->redis->multi();
        $this->redis->exists($actualKey);
        $this->redis->get($actualKey);
        [$exists, $item] = $this->redis->exec();
        if ($exists) {
            // return the stored item
            if (isset($item)) {
                return new Item($key, true, $this->codec->decode($item));
            }
        }
        // return a miss
        return new Item($key, false, null);
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param array<string> $keys
     * @return array<string, CacheItemInterface>
     * @throws InvalidArgumentException if any `$keys` aren't valid
     */
    public function getItems(array $keys = []): iterable
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
    public function hasItem($key): bool
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
    public function clear(): bool
    {
        $keys = [];
        foreach (new SetKey($this->redis, $this->keyGenerator->generateKey(self::SET_KEY)) as $key) {
            $keys[] = $key;
        }
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
        return true;
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException if `$key` isn't valid
     */
    public function deleteItem($key): bool
    {
        $this->assertKeyValid($key);
        if ($this->redis->del($this->keyGenerator->generateKey($key))) {
            $this->redis->srem($this->keyGenerator->generateKey(self::SET_KEY), [$key]);
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
    public function deleteItems(array $keys): bool
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
    public function save(CacheItemInterface $item): bool
    {
        $key    = $this->keyGenerator->generateKey($item->getKey());
        $cached = $this->codec->encode($item->get());

        // Special handling for TTL, as CacheItemInterface provides no method
        // for getting TTL: only for setting
        $ttl = null;
        if ($item instanceof Item) {
            $ttl = $item->getTtl();
        }

        // Put this in the key set, so it can be cleared later
        $this->redis->sadd($this->keyGenerator->generateKey(self::SET_KEY), [$key]);
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
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferredItems[$this->keyGenerator->generateKey($item->getKey())] = $item;
        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     */
    public function commit(): bool
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
        if ($key === self::SET_KEY) {
            throw new InvalidArgumentException(sprintf('Cache key "%s" is reserved and may not be used', $key));
        }
    }
}
