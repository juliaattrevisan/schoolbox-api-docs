<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing;

use InvalidArgumentException;

/**
 * This class is used as a simple means to compile a list of routes. It performs no operations on paths
 * given and does not attempt to do anything other than literal matches. Its primary point and purpose is
 * for loading the cache for a more sophisticated router to read from.
 *
 * @package Alaress\Routing
 */
class FlatRouter implements IRouter
{
    /** @var string[][] */
    private $paths = [];

    /** @var bool */
    private $locked = false;

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
        if ($this->locked) {
            return $this;
        }

        $this->paths[$path][$key] = $value;

        return $this;
    }

    /**
     * Get the data stored at the end of this path. The return value is an array of key-value pairs as
     * stored by static::add(), or throws an InvalidArgument exception if there is no data (ie. path not declared)
     *
     * @param string $path
     * @throws InvalidArgumentException
     * @return array
     */
    public function get($path)
    {
        if (isset($this->paths[$path])) {
            return $this->paths[$path];
        }

        throw new InvalidArgumentException("Path '$path' is not defined.");
    }

    /**
     * Retrieve all the routes contained in this router
     *
     * @return string[][]
     */
    public function getAll()
    {
        return $this->paths;
    }


    /**
     * If you know you're not going to be adding anything further to this router,
     * you can lock it, which will optimise internal memory structures.
     *
     * @return void
     */
    public function lock()
    {
        $this->locked = true;
    }


    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function dropAll()
    {
        $this->locked = false;
        $this->paths = [];
        return $this;
    }
}
