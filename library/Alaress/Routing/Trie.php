<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


use InvalidArgumentException;

class Trie
{
    /** @var static[] */
    protected $tries = [];

    /** @var array */
    private $data = [];

    /** @var string */
    private $key;

    /** @var bool */
    private $sorted = false;

    /** @var callable */
    private $usort;


    /**
     * @param string $key
     * @param callable $usort
     */
    public function __construct($key, callable $usort)
    {
        $this->key   = $key;
        $this->usort = $usort;
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
     * @throws InvalidArgumentException
     * @return static
     */
    public function get($key)
    {
        // if not sorted, do it now
        if (!$this->sorted) {
            $this->sort();
        }

        if (empty($this->tries)) {
            throw new InvalidArgumentException("Set is empty");
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

        throw new InvalidArgumentException("Key '$key' not found");
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        // try for a string literal first
        if (isset($this->tries[$key])) {
            return true;
        }

        // do not attempt a pattern match, simply return false
        return false;
    }


    /**
     * @param string $key
     * @return static
     */
    public function put($key)
    {
        // new insertion: no longer can guarantee sorted
        $this->sorted = false;

        $t = new static($key, $this->usort);
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
     * @throws InvalidArgumentException
     * @return array
     */
    public function fetch(array $params)
    {
        $node =& $this->data;

        foreach ($params as $p) {
            if (empty($node[$p])) {
                throw new InvalidArgumentException("No data match");
            }
            $node =& $node[$p];
        }

        return $node;
    }


    /**
     * Sort the internally-held trie structure.
     * This method is protected so, if required, you can extend to override this behaviour.
     *
     * @return void
     */
    protected function sort()
    {
        // recursively sort the entire tree
        foreach ($this->tries as $t) {
            $t->sort();
        }

        if ($this->sorted) {
            return;
        }

        uksort($this->tries, $this->usort);
    }
}
