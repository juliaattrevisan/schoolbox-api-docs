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
     * @deprecated Doesn't appear to be used anywhere.
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


    /**
     * ArrayAccess set method
     *
     * @deprecated Use set*() methods instead.
     *
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet ($offset, $value) {
        $this->triggerDeprecationWarning($offset, __FUNCTION__);
        $this->$offset = $value;
    }

    /**
     * ArrayAccess get method
     *
     * @deprecated Use get*() methods instead.
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet ($offset) {
        $this->triggerDeprecationWarning($offset, __FUNCTION__);
        if ( isset($this->$offset) ) {
            return $this->$offset;
        }
        else {
            return null;
        }
    }

    /**
     * ArrayAccess exists method
     *
     * @deprecated Use get*() methods instead.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists ($offset) {
        $this->triggerDeprecationWarning($offset, __FUNCTION__);
        return isset($this->$offset);
    }

    /**
     * ArrayAccess exists method
     *
     * @deprecated Use set*() methods instead.
     *
     * @param string $offset
     *
     * @return void
     */
    public function offsetUnset ($offset) {
        $this->triggerDeprecationWarning($offset, __FUNCTION__);
        unset($this->$offset);
    }

    /**
     * Converts this object to an array.
     *
     * @deprecated Doesn't appeared to be used anywhere.
     *
     * @return array
     */
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
        $this->triggerDeprecationWarning($property, __FUNCTION__);
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
        $this->triggerDeprecationWarning($property, __FUNCTION__);
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        return null;
    }


    /**
     * Triggers an appropriate deprecation warning for this type of access.
     *
     * @param string $property The property that was access.
     * @param string $method   The method that was called.
     *
     * @return void
     */
    protected function triggerDeprecationWarning($property, $method) {
        $message = '';
        switch ($method) {
            case '__get':
                $message = 'Direct get of property "%s::%s" is deprecated - use get methods instead';
                break;

            case '__set':
                $message = 'Direct set of property "%s::%s" is deprecated - use set methods instead';
                break;

            case 'offsetGet':
            case 'offsetSet':
            case 'offsetUnset':
            case 'offsetExists':
                $message = 'Array access of property "%s::%s" is deprecated - use get/set methods instead';
                break;
        }
        trigger_error(
            sprintf($message, get_class($this), $property),
            E_USER_DEPRECATED
        );
    }
}
