<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Route;

use Alaress\Routing\Trie;

class Route implements IRoute
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
    public function __construct($path, Trie $trie, $delimiter)
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
        $regex = '`' . implode('\\' . $this->delimiter, $this->chunks) . '`';

        if (preg_match($regex, $this->path, $matches)) {
            array_shift($matches);

            return $this->trie->fetch($matches);
        }

        // this is here for safety, but as far as can be determined
        // it is not possible for the above preg_match to return false
        throw new \InvalidArgumentException('No data match'); // @codeCoverageIgnore
    }
}
