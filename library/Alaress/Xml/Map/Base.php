<?php

/**
 * Base map iterator for Alaress\Xml\Reader
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Xml\Map;

use Alaress\Xml\State;
use IteratorIterator;
use UnexpectedValueException;

abstract class Base extends IteratorIterator
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
