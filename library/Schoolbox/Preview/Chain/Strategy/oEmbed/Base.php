<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed;


abstract class Base {

    /**
     * A DOMXPath thingy
     *
     * @var DOMXPath instance
     */
    protected $xml = null;

    /**
     * Constructor
     *
     * @param $xml
     */
    public function __construct($xml) {
        $this->setXml($xml);
    }

    /**
     * Find first element containing informatin about $attribute
     * matching $query
     *
     * @param $xml
     * @param $query
     * @param $attribute
     * @return mixed
     */
    protected function findFirstMatching($xml, $query, $attribute) {
        return array_shift($this->analyse($xml, $query, $attribute));
    }

    /**
     * Have a think about $query and return whatever it found
     *
     * @param $xml
     * @param $query
     * @param $attribute
     * @return string
     */
    protected function analyse($xml, $query, $attribute) {
        $allElements = $xml->query($query);

        $stack = [];

        foreach($allElements as $currentElement) {
            foreach($currentElement->attributes as $currentAttribute) {
                if ($currentAttribute->nodeName == $attribute) {
                    $stack[] = $currentAttribute->nodeValue;
                }
            }
        }

        return $stack;
    }

    /* Getters/Setters
     */

    /**
     * Set xml
     *
     * @param null $xml
     */
    protected function setXml($xml)
    {
        $this->xml = $xml;
    }

    /**
     * Get xml
     *
     * @return null
     */
    protected function getXml()
    {
        return $this->xml;
    }
}