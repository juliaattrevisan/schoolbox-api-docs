<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress;

use InvalidArgumentException;

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
     * @param string|int $key        a scalar value that represents the key of the particular datum you want
     * @param array      $fetchArgs  must be an array that matches the signature provided in the fetch callback and contains
     *                               the data required to execute said callback in the event of a cache-miss for the given key
     * @return mixed
     */
    public function get($key, array $fetchArgs = [])
    {
        if (!is_scalar($key)) {
            throw new InvalidArgumentException("Key must be scalar, got ".gettype($key));
        }

        if (!array_key_exists($key, $this->cache)) {
            // cache miss: execute fetch
            $this->cache[$key] = call_user_func_array($this->fetch, $fetchArgs);
        }

        return $this->cache[$key];
    }

}
