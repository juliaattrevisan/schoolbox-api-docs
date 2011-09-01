<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_ControllerAbstract {
    /**
     * @var \Component_Instance
     */
    protected $instance;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Template Stores the global template object.
     */
    protected $template;

    /**
     * @param Component_Instance $instance
     * @param User $user
     * @param Template $template
     */
    public function __construct (Component_Instance $instance, User $user, Template $template) {
        $this->instance = $instance;
        $this->user = $user;
        $this->template = $template;
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
        elseif ( method_exists($this->template, $method) ) {
            $object = $this->template;
        }

        if ( !empty($object) ) {
            $rm = new ReflectionMethod($object, $method);

            return $rm->invokeArgs($object, $argumentArray);
        } else {
            return $this->$method();
        }

    }

    public function __get ($property) {
        if ( isset($this->instance->$property) ) {
            return $this->instance->$property;
        }
        else {
            return $this->$property;
        }
    }
}