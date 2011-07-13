<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_AbstractFactory {
    /**
     * @var String
     */
    static $location;

    /**
     * @var Integer
     */
    protected $identifier;

    /**
     * @var Object
     */
    protected $templateObject;

    /**
     * @param Template $templateObject
     */
    public function __construct (Template $templateObject) {
        $this->setTemplate($templateObject);
    }

    /**
     * Setter for $templateObject.
     *
     * @param  $templateObject
     * @return void
     */
    public function setTemplate ($templateObject) {
        $this->templateObject = $templateObject;
    }

    /**
     * Sets the context for Component_AbstractFactory::buildContext
     *
     * @param Mixed $identifier
     * @return Bool
     */
    protected function setIdentifier ($identifier) {

        if ( is_string($identifier) ) {
            $this->identifier = $identifier;
            return true;
        }
        else {
            return false;
        }

    }

    /**
     * Builds and returns an array of components for a polymorphic context which is defined by $this->foreignTable
     * and $this->foreignID.
     *
     * @return Array
     */
    public function buildComponentList () {
        /* Get Component Instances */
        $instanceArray = array();

        $componentArray = array();

        if ( !empty($instanceArray) ) {
            foreach ($instanceArray as $instanceObject) {

                $definitionObject = $instanceObject->definition()->find_one();
                $optionArray = $instanceObject->option()->find_many();

                /* Get component specific dependencies */
                $dependencyArray = $this->getDependencies($instanceObject->class);

                /* Instantiate component controller */
                $argumentArray = array_merge(array($definitionObject, $optionArray), $dependencyArray);

                $rc = new ReflectionClass($instanceObject->class);
                $componentArray[] = $rc->newInstanceArgs($argumentArray);

            }
        }

        return $componentArray;
    }

    
    public function buildComponent ($instanceID) {

    }

    /**
     * Determines dependencies from a component controller's constructor.
     *
     * @param String $className
     * @return Array
     */
    private function getDependencies ($className) {

        $rm = new ReflectionMethod($className, '__construct');

        /*
        $dependencyArray = explode(',', trim($definitionObject->dependencies) );

        if ( is_array($dependencyArray) ) {
            foreach ($dependencyArray as &$dependency) {

                $rc = new ReflectionClass($dependency);
                $dependency = $rc->newInstance();
                
            }
        }

        return $dependencyArray;*/
    }
    
}