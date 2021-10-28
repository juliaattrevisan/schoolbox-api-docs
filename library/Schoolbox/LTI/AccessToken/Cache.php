<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Access token cache: basically, a wrapper around a mapper.
 */
class Cache implements CacheItemPoolInterface
{
    /**
     * Access token mapper
     */
    private Mapper $mapper;

    /**
     * Model cache
     *
     * @var array<string, CacheItem>
     */
    private array $cache = [];

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * @param string $key
     * @return CacheItem
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function getItem($key)
    {
        $this->assertKeyValid($key);
        if (!isset($this->cache[$key])) {
            try {
                $model ??= $this->mapper->findById($key);
            } catch (MapperException $e) {
                // TODO need to pass in a tool, and possibly user, for cache misses.
                //      Alternately this needs to be NULL
                $model = (new Model())->setIdentifier($key);
            }
            $this->cache[$key] = new CacheItem($model);
        }
        return $this->cache[$key];
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param array<string> $keys
     * @return array<string, CacheItem>
     * @throws CacheInvalidArgumentException if any `$keys` aren't valid
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
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function hasItem($key)
    {
        $this->assertKeyValid($key);
        return isset($this->cache[$key]);
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     */
    public function clear()
    {
        foreach (array_keys($this->cache) as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     * @return bool
     * @throws CacheInvalidArgumentException if `$key` isn't valid
     */
    public function deleteItem($key)
    {
        $this->assertKeyValid($key);
        if (!$this->hasItem($key)) {
            return false;
        }

        $model = $this->getItem($key)->get();
        try {
            $deleted = $this->mapper->delete($model);
            if ($deleted) {
                unset($this->cache[$key]);
                return true;
            }
        } catch (MapperException $e) {
            // noop
        }
        return false;
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param array $keys
     * @return bool
     * @throws CacheInvalidArgumentException if any `$keys` aren't valid
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
        if ($item instanceof CacheItem) {
            $this->cache[$item->getKey()] = $item;
            try {
                $this->mapper->save($item->get());
                return true;
            } catch (MapperException $e) {
                // noop: fall through
            }
        }
        return false;
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if ($item instanceof CacheItem) {
            $this->cache[$item->getKey()] = $item;
            return true;
        }
        return false;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     */
    public function commit()
    {
        foreach ($this->cache as $item) {
            if (!$this->save($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Asserts `$key` is valid.
     *
     * Currently, that just means "is it a string"?
     *
     * @param mixed $key
     * @throws CacheInvalidArgumentException if $key isn't valid
     */
    private function assertKeyValid($key): void
    {
        if (!is_string($key)) {
            throw new CacheInvalidArgumentException(sprintf('Cache key must be a string: received %s', gettype($key)));
        }
    }
}
