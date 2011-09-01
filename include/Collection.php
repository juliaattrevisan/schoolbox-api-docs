<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Collection implements ArrayAccess, Iterator, Countable {

    protected $pointer;

    protected $container;

    public function __construct ($objectList = array()) {
        $this->container = $objectList;
    }

    /**
     * Sets the container
     *
     * @param Array $modelList
     * @return Bool
     */
    public function set ($modelList) {
        if ( !is_array($modelList) ) {
            return false;
        }

        $this->rewind();

        $this->container = $modelList;

        return true;
    }

    /**
     * Gets the container
     *
     * @return Array
     */
    public function get () {
        return $this->container;
    }

    /* ArrayAccess interface methods */
    public function offsetSet ($offset, $value) {
        if ( empty($offset) ) {
            $this->container[] = $value;
        }
        else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetGet ($offset) {
        if ( isset($this->container[$offset]) ) {
            return $this->container[$offset];
        }
        else {
            return null;
        }
    }

    public function offsetExists ($offset) {
        return isset($this->container[$offset]);
    }

    public function offsetUnset ($offset) {
        unset($this->container[$offset]);
    }

    /* Iterator interface methods */
    public function rewind () {
        $this->pointer = 0;
    }

    public function current () {
        return $this->container[$this->pointer];
    }

    public function key () {
        return $this->pointer;
    }

    public function next () {
        $this->pointer++;
    }

    public function valid () {
        return isset($this->container[$this->pointer]);
    }

    /* Countable interface methods */
    public function count () {
        return count($this->container);
    }
}