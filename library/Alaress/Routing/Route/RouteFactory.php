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

class RouteFactory
{

    /** @var bool */
    private $recurse;

    /** @var Trie */
    private $root;

    /** @var string */
    private $delimiter;


    /**
     * @param Trie   $root
     * @param string $delimiter
     * @param bool   $isRecursive
     */
    public function __construct(Trie $root, $delimiter, $isRecursive)
    {
        $this->root      = $root;
        $this->delimiter = $delimiter;
        $this->recurse   = $isRecursive;
    }


    /**
     * @param string $path
     * @throws InvalidArgumentException
     * @return IRoute
     */
    public function build($path)
    {
        if ($this->recurse) {
            return new RecursiveRoute($path, $this->root, $this->delimiter);
        }

        return new Route($path, $this->root, $this->delimiter);
    }
}
