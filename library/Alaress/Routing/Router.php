<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing;

use Alaress\Routing\Dictionary\IDictionary;
use Alaress\Routing\Route\RouteFactory;
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

    /** @var RouteFactory */
    private $factory;


    /**
     * Router constructor.
     *
     * @param IDictionary $dictionary
     * @param bool        $isRecursive
     */
    public function __construct(IDictionary $dictionary, $isRecursive = true)
    {
        $this->dictionary = $dictionary;
        $this->root       = new Trie('', function ($keyA, $keyB) {
            return $this->dictionary->sort($keyA, $keyB);
        });
        $this->factory    = new RouteFactory($this->root, $dictionary->getDelimiter(), $isRecursive);
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
        if ($this->root->isLocked()) {
            // do nothing
            return $this;
        }

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
            // @codeCoverageIgnoreStart
            throw new OutOfBoundsException("How did this not match?? $fullRegex -> $path");
            // @codeCoverageIgnoreEnd
        }

        array_shift($matches);

        // $this->cache[$fullRegex] now points to the node we want to apply the payload to (any non-existent intermediate branches were created on the fly)
        $this->cache[$fullRegex]->set($matches, $key, $value);

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
        return $this->factory->build($path)->data();
    }


    /**
     * If you know you're not going to be adding anything further to this router,
     * you can lock it, which will optimise internal memory structures.
     *
     * @return void
     */
    public function lock()
    {
        unset($this->cache);
        $this->root->lock();
    }


    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function dropAll()
    {
        unset($this->cache);
        $this->root->reset();
        return $this;
    }
}
