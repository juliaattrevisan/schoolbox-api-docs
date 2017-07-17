<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Activity_ListIterator extends Activity_ListAbstract {

    public function __construct($datasource, array $config = array()) {
        parent::__construct($config);
        if($datasource instanceof Iterator) {
            $this->datasource = $datasource;
        }
    }


    /**
     * Will return the render of the current list item
     * @return string
     */
    public function current() {
        return $this->datasource->current();
    }

    /**
     * @return void Any returned value is ignored.
     */
    public function next() {
        $this->datasource->next();
    }

    /**
     * @return mixed scalar on success, or null on failure.
     */
    public function key() {
        return $this->datasource->key();
    }

    /**
     * @return boolean The return value will be casted to boolean and then evaluated.
     */
    public function valid() {
        return $this->datasource->valid();
    }

    /**
     * @return void Any returned value is ignored.
     */
    public function rewind() {
        $this->datasource->rewind();
    }


}
