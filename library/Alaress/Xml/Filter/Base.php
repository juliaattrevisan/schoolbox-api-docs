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
use UnexpectedValueException;

abstract class Base extends FilterIterator
{
    /**
     * Get the current element value
     *
     * @return State The current element value.
     * @throws UnexpectedValueException if it wasn't a State
     */
    public function current()
    {
        $state = parent::current();
        if (!$state instanceof State) {
            throw new UnexpectedValueException("Current value must be a XMLReader, got something else");
        }
        return $state;
    }
}
