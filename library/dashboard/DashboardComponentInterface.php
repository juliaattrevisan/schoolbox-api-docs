<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class DashboardComponentInterface {
    protected $component;

    protected $option_default;

    protected $option;

    protected $template;

    abstract function execute ();

    abstract function render ($template_handle);

    public function __construct (DashboardComponent $component, DashboardComponentInstance $component_instance, DashboardComponentOptionDefault $option_default, DashboardComponentOption $option, Template $template) {
        $this->component = $component;

        $this->component_instance = $component_instance;

        $this->option_default = $option_default;

        $this->option = $option;

        $this->template = $template;
    }

    public function __get($name) {

        if ( isset($this->$name) ) {
            return $this->$name;
        }

        if ( isset($this->component_instance->$name) ) {
            return $this->component_instance->$name;
        }

        if ( isset($this->component->$name) ) {
            return $this->component->$name;
        }

        if ( isset($this->option->$name) ) {
            return $this->option->$name;
        }

        if ( isset($this->option_default->$name) ) {
            return $this->option_default->$name;
        }

        return $this->$name;
    }
}

?>