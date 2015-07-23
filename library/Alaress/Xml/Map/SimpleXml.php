<?php
/**
 * Map iterator for Alaress\Xml\ReaderIterator - creates a SimpleXML document.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Xml\Map;

use SimpleXMLElement;

class SimpleXml extends Base
{
    /**
     * @return SimpleXMLElement
     */
    public function current()
    {
        $state = parent::current();
        return new SimpleXMLElement($state->getReader()->readOuterXml());
    }
}
