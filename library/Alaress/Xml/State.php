<?php
/**
 * Current iterated state of the XmlReader.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Xml;

use XMLReader;

class State
{
    /**
     * The XML reader, with whatever its internal state is
     *
     * @var XMLReader
     */
    protected $reader;

    /**
     * @var array
     */
    protected $elementStack;

    public function __construct(XMLReader $reader, $elementStack)
    {
        $this->reader       = $reader;
        $this->elementStack = $elementStack;
    }

    /**
     * @return XMLReader
     */
    public function getReader()
    {
        return $this->reader;
    }

//    /**
//     * @return string
//     */
//    public function getInnerXml()
//    {
//        return $this->reader->readInnerXml();
//    }
//
//    /**
//     * @return string
//     */
//    public function getOuterXml()
//    {
//        return $this->reader->readOuterXml();
//    }

    /**
     * @return array
     */
    public function getElementStack()
    {
        return $this->elementStack;
    }

    /**
     * Gets the current path
     *
     * @return string
     */
    public function getPath()
    {
        return '/'.implode('/', $this->getElementStack());
    }
}
