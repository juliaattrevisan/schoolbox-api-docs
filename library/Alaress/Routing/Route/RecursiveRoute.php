<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Route;

use Alaress\Routing\Trie;
use InvalidArgumentException;

/**
 * The more computationally-expensive route than its more primitive sibling. It can handle
 * scenarios where multiple Trie branches match the path given. The result is a merger of
 * all data found on all LEAVES (*not* intermediaries) that match the given path.
 *
 * @package Alaress\Routing\Route
 */
class RecursiveRoute implements IRoute
{

    /** @var string */
    private $path;

    /** @var string */
    private $delim;

    /** @var Trie[] */
    private $tries;


    /**
     * @param string $path
     * @param Trie   $root
     * @param string $delimiter
     * @throws InvalidArgumentException
     */
    public function __construct($path, Trie $root, $delimiter)
    {
        $this->path  = $path;
        $this->delim = $delimiter;
        $this->tries = $this->flattenMap($this->generateMap($path, $root));
    }


    /**
     * Process the map into a flat array of Tries, indexed by the paths that point to them.
     *
     * @param array  $matches
     * @param string $prepend
     * @return Trie[]
     */
    private function flattenMap(array $matches, $prepend = null)
    {
        $paths = [];

        foreach ($matches as $key => $more) {
            if (is_array($more)) {
                $passthru = $prepend ? "$prepend\\{$this->delim}$key" : $key;

                foreach ($this->flattenMap($more, $passthru) as $path => $trie) {
                    $paths[$path] = $trie;
                }
            } else {
                $paths[sprintf('%s%s%s', $prepend, '\\' . $this->delim, $key)] = $more;
            }
        }

        return $paths;
    }


    /**
     * Construct a map of Tries, recursively indexed by the path fragments that point to them.
     *
     * @param string $path
     * @param Trie   $trie
     * @return array
     */
    private function generateMap($path, Trie $trie)
    {
        $matches = [];

        if (strpos($path, $this->delim) === false) {
            // no remainder
            $head      = $path;
            $remainder = '';
        } else {
            list ($head, $remainder) = explode($this->delim, $path, 2);
        }

        foreach ($trie->match($head) as $next) {
            // attempt to recurse the tree with the current remainder
            $matches[$next->key()] = $this->generateMap($remainder, $next);
        }

        foreach ($trie->match($path) as $leaf) {
            // match the current path remainder as a leaf, and store it if it matches
            $matches[$leaf->key()] = $leaf;
        }

        return $matches;
    }


    /**
     * Returns the data payload this Route points to within the Trie structure.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function data()
    {
        $data = [];

        foreach ($this->tries as $pattern => $trie) {
            if (preg_match("`$pattern`", $this->path, $matches)) {
                array_shift($matches);

                try {
                    foreach ($trie->fetch($matches) as $key => $value) {
                        $data[$key] = $value;
                    }
                } catch (InvalidArgumentException $e) {
                    // suppress and continue
                    continue;
                }
            }
        }

        if (empty($data)) {
            throw new InvalidArgumentException('No data match');
        }

        return $data;
    }
}
