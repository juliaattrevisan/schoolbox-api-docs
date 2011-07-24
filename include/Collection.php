<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Collection {

    /**
     * An array filters applied to the collection.
     *
     * @var Array
     */
    protected $filter;

    /**
     * A mapper to notify when the Collection becomes out of sync.
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * An array of the models contained in this collection.
     *
     * @var Array
     */
    protected $models;

    /**
     * Sets the mapper
     */
    public function __construct () {
        /* Set mapper */
        $this->attachMapper( Mapper::getInstance() );
    }

    /**
     * Sets a filter and notifies the mapper
     *
     * @param Array $filterList
     * @return Bool
     */
    protected function setFilter ($filterList) {
        if ( !is_array($filterList) ) {
            return false;
        }

        $this->filter = $filterList;

        $this->notifyMapper();

        return true;
    }

    /**
     * Attaches a mapper which handles persistence with the db
     *
     * @param Mapper $mapper
     * @return Bool
     */
    protected function attachMapper (Mapper $mapper) {
        $this->mapper = $mapper;

        return true;
    }

    /**
     * Notifies the Mapper that a method has been called
     *
     * @return Bool
     */
    protected function notifyMapper () {
        return $this->mapper->updateCollection($this);
    }
}
