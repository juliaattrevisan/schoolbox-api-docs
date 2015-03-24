<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\oEmbed\Strategy;


abstract class Base {

    /**
     * Next in the chain
     *
     * @var Base
     */
    protected $next;

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
     * Set next
     *
     * @param  $next
     */
    public function setNext($next) {
        $this->next = $next;
    }

    /**
     * Get next
     *
     * @return bool
     */
    public function getNext() {
        return $this->next;
    }

    /**
     * Test with next strategy in the sequence
     *
     * @return mixed
     */
    protected function testNext() {
        if ($this->getNext()) {
            return $this->getNext()->test();
        }
    }

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