<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing;


class Route
{
    /** @var string */
    protected $path;

    /** @var string */
    protected $delimiter;

    /** @var array */
    protected $chunks;

    /** @var Trie */
    protected $trie;


    /**
     * @param string $path
     * @param Trie   $trie
     * @param string $delimiter
     * @throws \InvalidArgumentException
     */
    public function __construct($path, Trie $trie, $delimiter = '/')
    {
        $this->path      = $path;
        $this->delimiter = $delimiter;

        // read in the list of tries we want
        $this->trie = $this->locate($path, $trie);

        // pop the empty head off this array
        array_shift($this->chunks);
    }


    /**
     * @param string $path
     * @param Trie   $trie
     * @throws \InvalidArgumentException
     * @return Trie
     */
    protected function locate($path, Trie $trie)
    {
        $this->chunks[] = $trie->key();

        if (strpos($path, $this->delimiter) === false) {
            $chunk = $path;
            $remainder = '';

        } else {
            list ($chunk, $remainder) = explode($this->delimiter, $path, 2);
        }


        // aaaaaaand recursion
        try {
            return $this->locate($remainder, $trie->get($chunk));

        } catch (\InvalidArgumentException $e) {
            // ok, we're done now
        }

        // try with remainder
        return $trie->get($path);
    }


    /**
     * @throws \InvalidArgumentException
     * @return array
     */
    public function data()
    {
        $regex = '`' . implode('\\'.$this->delimiter, $this->chunks) . '`';

        if (preg_match($regex, $this->path, $matches)) {
            array_shift($matches);

            return $this->trie->fetch($matches);
        }

        throw new \InvalidArgumentException("No data match");
    }

}
