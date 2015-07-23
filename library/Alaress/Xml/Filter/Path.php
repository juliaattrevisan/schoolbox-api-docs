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

use Alaress\Xml\Reader;
use FilterIterator;
use UnexpectedValueException;
use XmlReader;

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
     * @param Reader $reader
     * @param array  $paths  A list of paths to match against.
     */
    public function __construct(Reader $reader, $paths)
    {
        parent::__construct($reader);
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
        $iterator = $this->getInnerIterator();
        $reader   = $this->current();
        if (!$iterator instanceof Reader) {
            throw new \UnexpectedValueException("Iterator must be a Alaress\\Xml\\Reader, got something else");
        }
        if (!$reader instanceof XMLReader) {
            throw new \UnexpectedValueException("Current value must be a XMLReader, got something else");
        }
        if ($reader->nodeType === XMLReader::ELEMENT
            || ($reader->nodeType === XMLReader::END_ELEMENT && $reader->isEmptyElement)
        ) {
            // test paths
            $path = $iterator->getPath();
            foreach ($this->paths as $thisPath) {
                if ($path === $thisPath) {
                    return true;
                }
            }
        }
        return false;
    }
}
