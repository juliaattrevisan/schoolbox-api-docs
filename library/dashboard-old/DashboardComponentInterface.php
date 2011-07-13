<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class DashboardComponentAbstract {
    protected $component;

    protected $component_instance;

    protected $option_default;

    protected $option;

    protected $template;

    protected $property;

    abstract function execute ();

    abstract function render ($template_handle);

    public function __construct (Array $property_arr, DashboardComponent $component, DashboardComponentInstance $component_instance, DashboardComponentOptionDefault $option_default, DashboardComponentOption $option, Template $template) {
        $this->component = $component;

        $this->component_instance = $component_instance;

        $this->option_default = $option_default;

        $this->option = $option;

        $this->template = $template;

        $this->property = $property_arr;
    }

    /* Getters and setters */
    public function get_component_id () {
        return $this->property['component_id'];
    }

    public function get_instance_id () {
        return $this->property['instance_id'];
    }

    public function get_class () {
        return $this->property['class'];
    }

    public function get_template () {
        return $this->property['template'];
    }

    public function get_name () {
        return $this->property['name'];
    }

    public function get_position () {
        return $this->property['position'];
    }

    /* Overloading */
    public function __get($name) {

        if ( isset($this->$name) ) {
            return $this->$name;
        }

        if ( isset($this->property[$name]) ) {
            return $this->property[$name];
        }

        return $this->$name;
    }

    public function __call($method, $args) {

        if ( method_exists($this, $method) ) {
            $object = $this;
        }
        elseif ( method_exists($this->template, $method) ) {
            $object = $this->template;
        }

        if ( !empty($object) ) {
            $rm = new ReflectionMethod($object, $method);

            return $rm->invokeArgs($object, $args);
        } else {
            return $this->$method();
        }

    }
}

?>