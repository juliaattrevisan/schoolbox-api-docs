<?php

/**
 *
 * @deprecated Models no longer need to extend a base class.
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Model implements ArrayAccess {
    /**
         * Defines the state of the model
         *
         * @var Bool
         */
    protected $state;

    /**
     * State constants
     */
    const STATE_CLEAN = 0;

    const STATE_DIRTY = 1;

    /**
     * Constructor
     */
    public function __construct () {
    }

    /**
     * Sets the current state of the model.
     *
     * @deprecated Doesn't appear to be used anywhere.
     *
     * @param Integer $state
     * @return Bool
     */
    public function setState ($state) {
        if (empty($state) || !is_numeric($state) ) {
            return false;
        }

        $this->state = $state;

        return true;
    }

    /**
     * Retrieves the current state of the model.
     *
     * @deprecated Doesn't appear to be used anywhere.
     *
     * @return Bool|Mixed
     */
    public function getState () {
        if ( empty($this->state) || !is_numeric($this->state) ) {
            return false;
        }

        return $this->state;
    }


    /* ArrayAccess interface methods */
    public function offsetSet ($offset, $value) {
        $this->$offset = $value;
    }

    public function offsetGet ($offset) {
        if ( isset($this->$offset) ) {
            return $this->$offset;
        }
        else {
            return null;
        }
    }

    public function offsetExists ($offset) {
        return isset($this->$offset);
    }

    public function offsetUnset ($offset) {
        unset($this->$offset);
    }

    public function toArray () {
        $modelReflection = new ReflectionObject($this);
        
        $propertyReflectionList = $modelReflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $propertyList = array();

        if ( !empty($propertyReflectionList) ) {
            foreach ( $propertyReflectionList as $propertyReflection ) {
                $propertyName = $propertyReflection->getName();
                $propertyValue = $propertyReflection->getValue($this);

                $propertyList[$propertyName] = $propertyValue;
            }
        }

        return $propertyList;
    }

    /**
     * Sets a property of this class.
     *
     * @deprecated Use set*() methods instead.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($property, $value) {
        trigger_error(
            E_USER_DEPRECATED,
            sprintf(
                'Direct set of property "%s::%s" is deprecated - use setter methods instead',
                __CLASS__, $property
            )
        );
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }

    /**
     * Gets a property of this class.
     *
     * @deprecated Use get*() methods instead.
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get($property) {
        trigger_error(
            E_USER_DEPRECATED,
            sprintf(
                'Direct set of property "%s::%s" is deprecated - use setter methods instead',
                __CLASS__, $property
            )
        );
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }
}
