<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


class Router
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
        $this->root = new Trie('');

        if (empty($delimiter)) {
            throw new \InvalidArgumentException("Delimiter cannot be empty");
        }
        $this->delimiter = $delimiter;

        $this->setDictionary($dictionary ?: [
            '\*'  => "[^\\{$this->delimiter}]+",  // asterisk matches single word
            '#'   => '.*',                        // hash matches multiple words
            '\d+' => '(\d+)'                      // compress digits into a \d+ check rather than storing individually
        ]);
    }


    /**
     * @param string     $path
     * @param string|int $key
     * @param mixed      $value
     */
    public function add($path, $key, $value = null)
    {
        $fullRegex = $this->transformWhole($path);

        // let's not do work we don't have to, eh?
        if (empty($this->cache[$fullRegex])) {

            $trie = $this->root;

            // perform Trie construction
            foreach (explode($this->delimiter, $path) as $shard) {

                // translate the shard to regex according to our dictionary
                $pattern = $this->transformShard($shard);

                try {
                    $trie = $trie->get($pattern);
                } catch (\InvalidArgumentException $e) {
                    // no wuzzes - just pop it in
                    $trie = $trie->put($pattern);
                }
            }

            $this->cache[$fullRegex] = $trie;
        }

        if (!preg_match("`$fullRegex`", $path, $matches)) {
            throw new \OutOfBoundsException("How did this not match?? $fullRegex -> $path");
        }

        array_shift($matches);

        // $this->cache[$fullRegex] now points to the leaf node (any non-existent intermediate branches were created on the fly)
        $this->cache[$fullRegex]->set($matches, $key, $value);
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
     * @param $path
     * @throws \InvalidArgumentException
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
            $this->wholeDictionary["`$pattern`"] = $transform;
        }
    }


    /**
     * @param string $shard
     * @return string
     */
    private function transformShard($shard)
    {
        return $this->transform($shard, $this->shardDictionary);
    }


    /**
     * @param string $whole
     * @return string
     */
    private function transformWhole($whole)
    {
        // regex-ise periods
        $whole = str_replace('.', '\.', $whole);
        return $this->transform($whole, $this->wholeDictionary);
    }


    /**
     * @param string $string
     * @param array $dictionary
     * @return string
     */
    private function transform($string, array $dictionary)
    {
        return preg_replace(array_keys($dictionary), array_values($dictionary), $string);
    }
}
