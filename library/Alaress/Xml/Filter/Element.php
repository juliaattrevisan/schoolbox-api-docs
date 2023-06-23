<?php

/**
 * Filter iterator for Alaress\Xml\Reader - filters out things that aren't
 * elements.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Xml\Filter;

use XmlReader;

class Element extends Base
{
    /**
     * Paths to check against
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept(): bool
    {
        $state  = $this->current();
        $reader = $state->getReader();
        if (
            $reader->nodeType === XMLReader::ELEMENT
            || ($reader->nodeType === XMLReader::END_ELEMENT && $reader->isEmptyElement)
        ) {
            return true;
        }
        return false;
    }
}
