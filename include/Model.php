<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

abstract class Model {

    /**
     * Relation constants
     */
    const RELATION_ONE_TO_ONE = 1;
    const RELATION_ONE_TO_MANY = 2;

    /*
     * State constants
     */
    const STATE_CLEAN = 0;
    const STATE_DIRTY = 1;

    /**
     * A mapper to notify when the Collection becomes out of sync.
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * An array containing filters which represent a single model
     *
     * @var Array
     */
    protected $filter;

    /**
     * Defines the state of the model
     *
     * @var Bool
     */
    protected $state;

    /**
     * The property which is the unique identifier
     *
     * @var String
     */
    protected $identifier;

    /**
     * Stores an array of property names
     *
     * @var Array
     */
    protected $propertyList;

    /**
     * Contains data for properties
     *
     * @var Array
     */
    protected $propertyData;

    /**
     * A list of models and the type of relationship the model has with them
     *
     * @var Array
     */
    protected $relationList;
    
    /**
     * Related models which should be linking when mapping occurs
     *
     * @var Array
     */
    protected $relationUsable;

    /**
     * Contains an array of related models
     *
     * @var Array
     */
    protected $relationGraph;

    /**
     * Sets the mapper
     */
    public function __construct () {
        /* Set mapper */
        $this->attachMapper( Mapper::getInstance() );

        /* Set current state of the model */
        $this->setState(self::STATE_CLEAN);

        /* Set model definition */
        $this->setDefinition();
    }

    /**
     * Defining functions are called from here.
     *
     * @abstract
     * @return Bool
     */
    abstract protected function setDefinition ();

    /**
     * Sets a property of the model if it is defined
     *
     * @param  $propertyName
     * @param  $propertyValue
     * @return Bool
     */
    protected function setProperty ($propertyName, $propertyValue) {
        if ( !in_array($propertyName, $this->propertyList) ) {
            return false;
        }

        if ( !is_array($this->propertyData) ) {
            $this->propertyData = array();
        }

        $this->propertyData[$propertyName] = $propertyValue;

        return true;
    }

    /**
     * Retrieves a property of the model
     *
     * @param String $propertyName
     * @return Bool|Mixed
     */
    protected function getProperty ($propertyName) {
        if ( !in_array($propertyName, $this->propertyList) || !array_key_exists($propertyName, $this->propertyData) ) {
            return false;
        }

        return $this->propertyData[$propertyName];
    }

    /**
     * Defines a single property
     *
     * @param String $propertyName
     * @return Bool
     */
    protected function hasProperty ($propertyName) {
        if ( !is_array($this->propertyList) ) {
            $this->propertyList = array();
        }

        if ( !in_array($propertyName, $this->propertyList) ) {
            $this->propertyList[] = $propertyName;
        }

        return true;
    }

    /**
     * Defines an array of properties 
     *
     * @param Array $propertyList
     * @return Bool
     */
    protected function hasProperties ($propertyList) {
        if ( !is_array($this->propertyList) ) {
            $this->propertyList = array();
        }

        foreach ($propertyList as $propertyName) {

            if ( !in_array($propertyName, $this->propertyList) ) {
                $this->propertyList[] = $propertyName;
            }

        }

        return true;
    }

    /**
     * Defines the identifying property
     *
     * @param String $propertyName
     * @return Bool
     */
    protected function hasIdentifier ($propertyName) {
        if ( !is_array($this->propertyList) ) {
            $this->propertyList = array();
        }

        if ( !in_array($propertyName, $this->propertyList) ) {
            $this->hasProperty($propertyName);
        }

        $this->identifier = $propertyName;

        return true;
    }

    protected function getIdentifier () {
        if ( empty($this->identifier) ) {
            return false;
        }

        return $this->identifier;
    }

    /**
     * Defines a direct relationship between the model and another.
     *
     * @param String $modelName
     * @param Integer $relationType
     * @return Bool
     */
    protected function hasRelation ($modelName, $relationType = self::RELATION_ONE_TO_MANY) {
        if ( !is_array($this->relationList) ) {
            $this->relationList = array();
        }

        $this->relationList[$modelName] = $relationType;
        
        return true;
    }

    /**
     * Allows a relation to be included (joined) when the object graph is created.
     *
     * @param String $modelName
     * @return Bool
     */
    protected function useRelation ($modelName) {
        if ( empty($this->relationList[$modelName]) ) {
            return false;
        }

        $this->relationUsable[$modelName] = true;

        return true;
    }

    /**
     * Sets the current state of the model.
     *
     * @param Integer $state
     * @return Bool
     */
    protected function setState ($state) {
        if (empty($state) || !is_numeric($state) ) {
            return false;
        }

        $this->state = $state;

        return true;
    }

    /**
     * Retrieves the current state of the model.
     *
     * @return Bool|Mixed
     */
    protected function getState () {
        if ( empty($this->state) || !is_numeric($this->state) ) {
            return false;
        }

        return $this->state;
    }

    /**
     * @param String $propertyName
     * @param String $propertyValue
     * @return Bool
     */
    protected function setFilter ($propertyName, $propertyValue) {
        if ( empty($propertyName) || empty($propertyValue) ) {
            return false;
        }

        $this->filter[] = array(
            'propertyName' => $propertyName,
            'propertyValue' => $propertyValue
        );

        return $this->notifyMapper();
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
        return $this->mapper->updateModel($this);
    }

    /**
     * Overloading method
     *
     * @throws Exception
     * @param String $methodName
     * @param Array $argumentList
     * @return Bool|Mixed
     */
    public function __call ($methodName, $argumentList) {
        /* Called get{$propertyName} */
        if ( substr($methodName, 0, 3) == 'get' ) {
            $propertyName = lcfirst( substr($methodName, 3) );

            return $this->getProperty($propertyName);
        }

        /* Called set{$propertyName}($propertyValue) */
        else if ( substr($methodName, 0, 3) == 'set' ) {
            $propertyName = lcfirst( substr($methodName, 3) );

            if ( empty($argumentList) ) {
                throw new Exception("Incorrect number of arguments supplied for {$methodName}");
            }

            $propertyValue = $argumentList[0];

            return $this->setProperty($propertyName, $propertyValue);
        }

        /* Throw exception */
        else {
            throw new Exception("Call to undefined method {$methodName}");
        }
    }
}