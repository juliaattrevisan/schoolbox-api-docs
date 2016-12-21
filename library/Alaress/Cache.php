<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress;

/**
 * Class Cache
 *
 * Multi-purpose cache object for simplifying the process of
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
     * Cache constructor.
     *
     * @param callable $fetch
     */
    public function __construct(callable $fetch)
    {
        $this->fetch = $fetch;
    }


    /**
     *
     * @param string|int $key
     * @param array      $fetchArgs will be ignored if the cache is hit
     * @return mixed
     */
    public function get($key, array $fetchArgs = [])
    {
        if (!is_scalar($key)) {
            throw new \InvalidArgumentException("Key must be scalar, got ".gettype($key));
        }

        if (empty ($this->cache[$key])) {
            $this->cache[$key] = call_user_func_array($this->fetch, $fetchArgs);
        }

        return $this->cache[$key];
    }

}
