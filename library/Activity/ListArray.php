<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Activity_ListArray extends Activity_ListAbstract {

    protected $ptr = 0;

    public function __construct($datasource, array $config = array()) {
        parent::__construct($config);

        if(is_array($datasource)) {
            $this->datasource = $datasource;
        }
    }


    /**
     * Will return the render of the current list item
     * @return string
     */
    public function current() {
        return $this->renderItem($this->datasource[$this->ptr]);
    }

    /**
     * @return void
     */
    public function next() {
        $this->ptr++;
    }

    /**
     * @return int
     */
    public function key() {
        return $this->ptr;
    }

    /**
     * @return bool
     */
    public function valid() {
        return isset($this->datasource[$this->ptr]);
    }

    /**
     * @return void
     */
    public function rewind() {
        $this->ptr = 0;
    }

}
