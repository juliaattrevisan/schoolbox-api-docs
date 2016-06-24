<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


class Trie
{
    /** @var array */
    protected $tries;

    /** @var array */
    protected $data;

    /** @var string */
    protected $key;


    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
        $this->tries = [];
        $this->data  = [];
    }


    /**
     * @return string
     */
    public function key()
    {
        return $this->key;
    }


    /**
     * @param string $key
     * @throws \InvalidArgumentException
     * @return self
     */
    public function get($key)
    {
        if (empty($this->tries)) {
            throw new \InvalidArgumentException("Set is empty");
        }

        // try for a string literal first
        if (isset($this->tries[$key])) {
            return $this->tries[$key];
        }

        // literal failed, try for regex
        foreach ($this->tries as $pattern => $trie) {
            if (preg_match("`^$pattern$`", $key)) {
                return $trie;
            }
        }

        throw new \InvalidArgumentException("Key '$key' not found");
    }


    /**
     * @param string $key
     * @return self
     */
    public function put($key)
    {
        $t = new self($key);
        $this->tries[$key] = $t;

        return $t;
    }


    /**
     * @param array      $params
     * @param int|string $k
     * @param mixed      $v
     */
    public function set(array $params, $k, $v)
    {
        $node =& $this->data;

        foreach ($params as $p) {
            if (!isset($node[$p])) {
                $node[$p] = [];
            }
            $node =& $node[$p];
        }

        $node[$k] = $v;
    }


    /**
     * @param array $params
     * @throws \InvalidArgumentException
     * @return array
     */
    public function fetch(array $params)
    {
        $node =& $this->data;

        foreach ($params as $p) {
            if (empty($node[$p])) {
                throw new \InvalidArgumentException("No data match");
            }
            $node =& $node[$p];
        }

        return $node;
    }

}
