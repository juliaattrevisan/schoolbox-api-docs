<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Generic_Object {
    protected $fields = array();

    public function __construct($fields) {
        if(is_array($fields)) {
            foreach($fields as $k => $v) {
                $this->fields[$k] = $v;
            }
        }
        else {
            //throw new Exception_Schoolbox('Object cannot be instantiated without fields.');
        }
    }

    /* Twig-sensitive methods. No exceptions thrown. */

    public function __isset($field) {
        return isset($this->fields[$field]);
    }

    public function __get($field) {
        try {
            return $this->get($field);
        }
        catch(Exception_Schoolbox $e) {
            return '';
        }
    }

    /* Access methods: don't try to ask for something that doesn't exist */

    /**
     * @param $field
     * @return Collection
     */
    public function get($field) {
        return $this->getField($field, '');
    }

    /**
     * This protected get method is designed for extending classes to utilise.
     * Use subclasses to create alias methods which take advantage of the $args parameter
     *
     * @param $field
     * @param $args
     * @return Collection
     * @throws Exception_Schoolbox
     */
    protected function getField($field, $args) {

        if($this->__isset($field)) {

            // if the data is absent, try to hydrate it
            if($this->fields[$field] instanceof Collection) {
                if($this->fields[$field]->count() == 0) {
                    try {
                        $dm = $this->getDataMapper();
                        if($args) {
                            $dm->hydrate($this, $field, $args);
                        }
                        else {
                            $dm->hydrate($this, $field);
                        }
                    }
                    catch(ErrorException $e) {
                        throw new Exception_Schoolbox("Field {$this->classname()}.$field cannot be hydrated.");
                    }
                }
            }
            return $this->fields[$field];
        }
        throw new Exception_Schoolbox("Field {$this->classname()}.$field does not exist.");
    }

    public function set($field, $value) {
        if($this->__isset($field)) {
            $this->fields[$field] = $value;
        }
        else {
            throw new Exception_Schoolbox("Field {$this->classname()}.$field does not exist.");
        }
    }

    public function classname() {
        return get_called_class();
    }

    /**
     * @return Generic_DataMapper
     */
    public function getDataMapper() {
        $mapper = get_called_class() . '_DataMapper';
        return new $mapper();
    }

    /**
     * Overriding this method is encouraged.
     * @return string
     */
    public function __toString() {
        return '';
    }
}
