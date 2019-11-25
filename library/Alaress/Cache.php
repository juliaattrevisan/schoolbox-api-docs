<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress;

use InvalidArgumentException;
use function array_key_exists;
use function gettype;
use function is_scalar;

/**
 * Class Cache
 *
 * Multi-purpose cache object for simplifying the process of doing computations/retrievals only once.
 *
 * The logic for cache-miss is passed in via construction.
 *
 * @package Alaress
 */
class Cache
{

    /** @var callable */
    private $fetch;

    /** @var array */
    private $cache = [];


    /**
     * The signature of the $fetch callback is user-defined; whatever you need it to be for this particular cache.
     *
     * @param callable $fetch  the logic to execute in the event of a cache miss. The signature is user-defined.
     */
    public function __construct(callable $fetch)
    {
        $this->fetch = $fetch;
    }


    /**
     *
     * @param string|int $key              a scalar value that represents the key of the particular datum you want
     * @param array      $fetchArgs        must be an array that matches the signature provided in the fetch callback and contains
     *                                     the data required to execute said callback in the event of a cache-miss for the given key
     * @param bool       $invalidateCache  if true, any cached value for the given key will be deleted and re-fetched
     * @throws InvalidArgumentException    if the given key is not registered
     * @return mixed
     */
    public function get($key, array $fetchArgs = [], $invalidateCache = false)
    {
        if (!is_scalar($key)) {
            throw new InvalidArgumentException("Key must be scalar, got ".gettype($key));
        }

        if ($invalidateCache) {
            $this->invalidate($key);
        }

        if (!array_key_exists($key, $this->cache)) {
            // cache miss: execute fetch
            $this->cache[$key] = $this->fetch($fetchArgs);
        }

        return $this->cache[$key];
    }


    /**
     * @param string $key
     * @return $this
     */
    public function invalidate($key)
    {
        unset ($this->cache[$key]);
        return $this;
    }


    /**
     * Will fetch the result of the given args DIRECTLY. The cache will be completely bypassed.
     *
     * @param array $fetchArgs
     * @return mixed
     */
    public function fetch(array $fetchArgs = [])
    {
        return call_user_func_array($this->fetch, $fetchArgs);
    }


    /**
     * Returns whether this cache contains an item for the specified key
     *
     * @param mixed $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function contains($key): bool
    {
        if (!is_scalar($key)) {
            throw new InvalidArgumentException("Key must be scalar, got " . gettype($key));
        }
        return array_key_exists($key, $this->cache);
    }
}
