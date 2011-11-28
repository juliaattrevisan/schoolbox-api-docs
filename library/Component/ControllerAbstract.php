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
     * @var DeprecatedUser
     */
    protected $user;

    /**
     * @var SitePage
     */
    protected $sitepage;

    /**
     * @param Component_Instance $instance
     * @param DeprecatedUser $user
     * @param SitePage $sitepage
     */
    public function __construct (Component_Instance $instance, DeprecatedUser $user, SitePage $sitepage) {
        $this->instance = $instance;
        $this->user = $user;
        $this->sitepage = $sitepage;
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @abstract
     * @return void
     */
    abstract function execute ();

    /**
     * @param $templateHandle
     * @param string $templateName
     * @param string $templatePath
     * @return bool
     */
    protected function loadTemplate ($templateHandle, $templateName = '', $templatePath = '') {

        $templateFile = $this->getTemplate($templateName, $templatePath);

        if ( !$this->sitepage->setFile($templateHandle, $templateFile) ) {
            return false;
        }

        return true;
    }

    protected function getTemplate ($templateName = '', $templatePath = '') {
        if ( empty($templateName) ) {
            /* Break apart class name by underscores, get only last segment, remove last 10 characters then send it to lowercase */
            $templateName = ucfirst( substr( array_pop( explode('_', $this->definition->class) ), 0, -10 ) );
        }

        if ( empty($templatePath) ) {
            $templatePath = "component";
        }

        $templateFile = "$templatePath/$templateName.html";

        return $templateFile;
    }

    /**
     * Renders view using Component_AbstractController::$templateObject.
     *
     * @param String $templateHandle
     * @return void
     */
    protected function renderTemplate ($templateHandle) {

        if ( !$this->loadTemplate($templateHandle) ) {
            return false;
        }
        
    }

    /**
     * Allows calls to the sitepage object in this style:
     * $this->setBlock();
     * Exists to ease porting of existing components.
     *
     * @param String $method
     * @param Array $argumentList
     * @return mixed
     */
    public function __call($method, $argumentList) {

        if ( method_exists($this, $method) ) {
            $object = $this;
        }
        elseif ( method_exists($this->sitepage, $method) ) {
            $object = $this->sitepage;
        }

        if ( !empty($object) ) {
            $rm = new ReflectionMethod($object, $method);

            return $rm->invokeArgs($object, $argumentList);
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