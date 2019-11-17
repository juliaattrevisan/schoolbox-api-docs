<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing;


class ByUserRouter implements IRouter
{
    /** @var array */
    protected $routes = [];

    /**
     * Add the given path as a known route, and a key-value pair to be stored at the end of that path.
     * If the key already exists on this path, the new value will replace any existing value.
     *
     * @param string     $path
     * @param string|int $key
     * @param mixed      $value
     * @return $this
     */
    public function add($path, $key, $value = null)
    {
        $this->routes[$key][$path] = $value;

        return $this;
    }


    /**
     * Get the data stored at the end of this path. The return value is an array of key-value pairs as
     * stored by static::add(), or throws an InvalidArgument exception if there is no data (ie. path not declared)
     *
     * @param int $userId
     * @return array
     */
    public function get($userId)
    {
        if (!key_exists($userId, $this->routes)) {
            return [];
        }
        return array_keys($this->routes[$userId]);
    }


    /**
     * If you know you're not going to be adding anything further to this router,
     * you can lock it, which will optimise internal memory structures.
     *
     * @return void
     */
    public function lock()
    {
        // noop
    }


    /**
     * Removes all routes from this Router
     *
     * @return $this
     */
    public function dropAll()
    {
        return $this;
    }

}
