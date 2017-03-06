<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


use Alaress\Routing\Dictionary\IDictionary;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Trie-based router. Worst case traversal should be O(log(n))
 *
 * @package Alaress\Routing
 */
class Router implements IRouter
{
    /** @var Trie */
    protected $root;

    /** @var IDictionary */
    protected $dictionary;

    /** @var Trie[] */
    protected $cache;


    /**
     * Router constructor.
     *
     * @param IDictionary $dictionary
     * @throws InvalidArgumentException
     */
    public function __construct(IDictionary $dictionary)
    {
        $this->root = new Trie('');
        $this->dictionary = $dictionary;
    }


    /**
     * Add the given path as a known route, and a key-value pair to be stored at the end of that path.
     * If the key already exists on this path, the new value will replace any existing value.
     *
     * @param string     $path
     * @param string|int $key
     * @param mixed      $value
     * @return $this
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function add($path, $key, $value = null)
    {
        $fullRegex = $this->dictionary->translateWhole(str_replace('.', '\.', $path));

        // let's not do work we don't have to, eh?
        if (empty($this->cache[$fullRegex])) {

            $trie = $this->root;

            // perform Trie construction
            foreach (explode($this->dictionary->getDelimiter(), $path) as $shard) {

                // translate the shard to regex according to our dictionary
                $pattern = $this->dictionary->translateShard($shard);

                if ($trie->has($pattern)) {
                    $trie = $trie->get($pattern);
                } else {
                    // no wuzzes - just pop it in
                    $trie = $trie->put($pattern);
                }
            }

            $this->cache[$fullRegex] = $trie;
        }

        if (!preg_match("`$fullRegex`", $path, $matches)) {
            throw new OutOfBoundsException("How did this not match?? $fullRegex -> $path");
        }

        array_shift($matches);

        // $this->cache[$fullRegex] now points to the leaf node (any non-existent intermediate branches were created on the fly)
        $this->cache[$fullRegex]->set($matches, $key, $value);

        return $this;
    }


    /**
     * @return void
     */
    public function dropCache()
    {
        unset($this->cache);
        $this->cache = [];
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
        $route = new Route($path, $this->root, $this->dictionary->getDelimiter());

        return $route->data();
    }

}
