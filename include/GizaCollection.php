<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class GizaCollection implements Iterator {

    private $collection = array();
    private $position;

    public function __construct($collection = null) {
        // create empty
        if($collection != null) {
            $this->add($collection);
        }
        $this->position = 0;
    }

    public function add($collection) {
        if($collection instanceof ICollection) {
            $this->collection = array_merge($this->collection, $collection->getAll());
        }

        elseif(is_array($collection) && count($collection)) {
            foreach($collection as $item) {
                if(is_a($item, 'Generic_Object')) {
                    $this->collection[] = $item;
                }
            }
        }

        elseif(is_a($collection, 'Generic_Object')) {
            $this->collection[] = $collection;
        }

        else {
            throw new Exception_Schoolbox('Collection can only hold ORM Objects');
        }
    }

    /**
     * Get method allows passthru when there is only 1 item in the Collection, otherwise returns the requested index
     *
     * @param int $index
     * @return Generic_Object|Collection
     * @throws Exception_Schoolbox
     */
    public function get($index = 0) {
        if(count($this->collection == 1)) {
            if($this->collection[0]->__isset($index)) {
                return $this->collection[0]->get($index);
            }
            return $this->collection[0];
        }
        elseif(count($this->collection)) {
            return $this->collection[$index];
        }
        throw new Exception_Schoolbox("Could not get '$index'.");
    }

    // exploiting the weird way PHP implements 'protected': method can be called across objects of the same class :S
    protected function getAll() {
        return $this->collection;
    }

    public function asArray() {
        return $this->collection;
    }

    public function count() {
        return count($this->collection);
    }


    /* Iteration Implementation. Collection can be used in foreach */

    /**
     * @return Generic_Object
     */
    public function current() {
        return $this->collection[$this->position];
    }

    public function next() {
        ++$this->position;
    }

    public function key() {
        return $this->position;
    }

    public function valid() {
        return isset($this->collection[$this->position]);
    }

    public function rewind() {
        $this->position = 0;
    }

}
