<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing;

use Generator;
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

    /** @var bool */
    private $writable = true;


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
        if (empty($this->tries)) {
            throw new InvalidArgumentException('Set is empty');
        }

        // if not sorted, do it now
        if (!empty($this->writable) && !$this->sorted) {
            $this->sort();
        }

        // try for a string literal first
        if (isset($this->tries[$key])) {
            return $this->tries[$key];
        }

        $trie = $this->match($key)->current();

        if ($trie) {
            return $trie;
        }

        throw new InvalidArgumentException("Key '$key' not found");
    }


    /**
     * For increased utility, this method allows you to bypass ::get() and obtain the iterator
     * for all matching tries. If nothing matches, an empty Generator will be returned.
     *
     * @param string $key
     * @return Generator|static[]
     */
    public function match($key)
    {
        if (empty($this->tries)) {
            return;
        }

        foreach ($this->tries as $pattern => $trie) {
            if (preg_match("`^$pattern$`", $key)) {
                yield $trie;
            }
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        if (empty($this->tries)) {
            return false;
        }

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
        if (empty($this->writable)) {
            return null;
        }

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
        if (empty($this->writable)) {
            return;
        }

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
        if (empty($this->data)) {
            throw new InvalidArgumentException('No data match');
        }

        $node =& $this->data;

        foreach ($params as $p) {
            if (empty($node[$p])) {
                throw new InvalidArgumentException('No data match');
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
        if (empty($this->writable)) {
            return;
        }

        // recursively sort the entire tree
        foreach ($this->tries as $t) {
            $t->sort();
        }

        if ($this->sorted) {
            return;
        }

        uksort($this->tries, $this->usort);

        $this->sorted = true;
    }


    /**
     * This is a one-way street. Locks down and memory-optimises this trie and everything below it
     */
    public function lock()
    {
        if (empty($this->writable)) {
            return;
        }

        if (empty($this->data)) {
            unset($this->data);
        }

        // finalise sorting
        $this->sort();
        unset($this->writable, $this->usort, $this->sorted);

        if (count($this->tries)) {
            foreach ($this->tries as $t) {
                $t->lock();
            }
        } else {
            // we are a leaf node, these tidbits are irrelevant
            unset($this->tries);
        }
    }


    /**
     * @return bool
     */
    public function isLocked()
    {
        return empty($this->writable);
    }


    /**
     * Remove the contents of this Trie
     *
     * @return $this
     */
    public function reset()
    {
        // unlock
        $this->writable = true;

        foreach ($this->tries as $trie) {
            // recursively erase the content from leaf to trunk
            $trie->reset();
        }

        // remove stubs
        $this->tries = [];

        return $this;
    }
}
