<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Mapper {

    static private $instance;

    /**
     * Singleton function
     *
     * @static
     * @return Mapper
     */
    static public function getInstance() {
        if ( empty(self::$instance) ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Constructor gets the PDO instance
     */
    private function __construct () {
        
    }

    /**
     * Keeps the collection in sync
     *
     * @param Collection $collection
     * @return Bool
     */
    public function updateCollection (Collection $collection) {
        return true;
    }

    /**
     * Keeps the model in sync
     *
     * @param Model $model
     * @return Bool
     */
    public function updateModel (Model $model) {
        
        return true;
    }

    private function getModelName ($collection) {

    }

    private function getCollectionName ($model) {

    }

    private function graphResult ($resultSet) {
        
    }

    private function getModelRelations ($model) {
        
    }
}