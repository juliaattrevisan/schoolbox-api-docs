<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Collection implements ArrayAccess, ICollection {

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

    public function toArray () {
        return $this->container;
    }

    public function getAll() {
        return $this->toArray();
    }

    /**
     * Add item to end of this collection
     *
     * @param $value
     */
	public function add($value) {
        $this[] = $value;
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
        reset($this->container);
    }

    public function current () {
        return current($this->container);
    }

    public function key () {
        return key($this->container);
    }

    public function next () {
        next($this->container);
    }

    public function valid () {
        return current($this->container) !== false;
    }

    /* Countable interface methods */
    public function count () {
        return count($this->container);
    }
}