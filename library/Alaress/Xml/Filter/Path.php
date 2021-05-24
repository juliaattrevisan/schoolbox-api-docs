<?php

/**
 * Filter iterator for Alaress\Xml\Reader - filters out nodes that don't match
 * a path.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Xml\Filter;

use Iterator;

class Path extends Base
{
    /**
     * Paths to check against
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Constructor
     *
     * @param Iterator $iterator
     * @param array    $paths    A list of paths to match against.
     */
    public function __construct(Iterator $iterator, $paths)
    {
        parent::__construct($iterator);
        if (is_scalar($paths)) {
            $paths = [$paths];
        }
        $this->paths = $paths;
    }

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        $state = $this->current();
        foreach ($this->paths as $thisPath) {
            if ($state->matches($thisPath)) {
                return true;
            }
        }
        return false;
    }
}
