<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_AbstractController {
    /**
     * @var Template Stores the global template object.
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
     * @param Template $templateObject
     * @return void
     */
    public function setTemplate (Template $templateObject) {
        $this->templateObject = $templateObject;
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @abstract
     * @return void
     */
    abstract function execute ();

    /**
     * Renders view using Component_AbstractController::$templateObject.
     *
     * @abstract
     * @param String $templateHandle
     * @return void
     */
    abstract function render ($templateHandle);

    /**
     * Allows calls to the template object in this style:
     * $this->setBlock();
     * Exists to ease porting of existing components.
     *
     * @param String $method
     * @param Array $argumentArray
     * @return mixed
     */
    public function __call($method, $argumentArray) {

        if ( method_exists($this, $method) ) {
            $object = $this;
        }
        elseif ( method_exists($this->templateObject, $method) ) {
            $object = $this->templateObject;
        }

        if ( !empty($object) ) {
            $rm = new ReflectionMethod($object, $method);

            return $rm->invokeArgs($object, $argumentArray);
        } else {
            return $this->$method();
        }

    }

}