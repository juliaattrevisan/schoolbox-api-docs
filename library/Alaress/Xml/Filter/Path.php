<?php
/**
 * Filter iterator for Alaress\Xml\Reader - filters out nodes that don't match
 * a path.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Xml\Filter;

use Alaress\Xml\State;
use FilterIterator;
use Iterator;
use UnexpectedValueException;

class Path extends FilterIterator
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
        if (!$state instanceof State) {
            throw new \UnexpectedValueException("Current value must be a XMLReader, got something else");
        }
        // test paths
        $path = $state->getPath();
        foreach ($this->paths as $thisPath) {
            if ($path === $thisPath) {
                return true;
            }
        }
        return false;
    }
}
