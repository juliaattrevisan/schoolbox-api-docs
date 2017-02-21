<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


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

    /** @var string */
    protected $delimiter;

    /** @var array */
    protected $shardDictionary;

    /** @var array */
    protected $wholeDictionary;

    /** @var Trie[] */
    protected $cache;


    /**
     * Router constructor.
     *
     * @param string $delimiter
     * @param array  $dictionary
     * @throws \InvalidArgumentException
     */
    public function __construct($delimiter = '/', array $dictionary = null)
    {
        if (empty($delimiter)) {
            throw new \InvalidArgumentException("Delimiter cannot be empty");
        }

        $this->root = new Trie('');

        $this->delimiter = $delimiter;

        $this->setDictionary($dictionary ?: [
            '\*'  => "[^\\{$this->delimiter}]+",  // asterisk matches single word
            '#'   => '.*',                        // hash matches multiple words
            '\d+' => '(\d+)'                      // compress digits into a \d+ check rather than storing individually
        ]);
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
        $fullRegex = $this->transform(str_replace('.', '\.', $path), $this->wholeDictionary);

        // let's not do work we don't have to, eh?
        if (empty($this->cache[$fullRegex])) {

            $trie = $this->root;

            // perform Trie construction
            foreach (explode($this->delimiter, $path) as $shard) {

                // translate the shard to regex according to our dictionary
                $pattern = $this->transform($shard, $this->shardDictionary);

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
        $route = new Route($path, $this->root, $this->delimiter);

        return $route->data();
    }


    /**
     * @param array $dictionary
     */
    private function setDictionary(array $dictionary)
    {
        foreach ($dictionary as $pattern => $transform) {
            $this->shardDictionary["`^$pattern$`"] = $transform;
            $this->wholeDictionary["`$pattern`"]   = $transform;
        }

        // pre-cache this transformation into the format we will need
        $this->shardDictionary = [
            array_keys($this->shardDictionary), array_values($this->shardDictionary)
        ];
        $this->wholeDictionary = [
            array_keys($this->wholeDictionary), array_values($this->wholeDictionary)
        ];
    }


    /**
     * @param string $string
     * @param array  &$dictionary
     * @return string
     */
    private function transform($string, array &$dictionary)
    {
        return preg_replace($dictionary[0], $dictionary[1], $string);
    }
}
