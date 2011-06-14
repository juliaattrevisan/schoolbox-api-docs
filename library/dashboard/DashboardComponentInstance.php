<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

require_once dirname(__FILE__) . '/../../include/model.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponent.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponentOptionDefault.php';
require_once dirname(__FILE__) . '/../../library/dashboard/DashboardComponentOption.php';

class DashboardComponentInstance extends Model {
    
    public $_table = 'dashboard_component_instance';

    public $_index = 'id';

    public function __construct (DashboardComponent $component_model, DashboardComponentOptionDefault $option_default_model, DashboardComponentOption $option_model, Template $template_model) {
        $this->component_model = $component_model;
        $this->option_default_model = $option_default_model;
        $this->option_model = $option_model;
        $this->template_model = $template_model;

        parent::Model();
    }

    public function get_instances ($group_id) {
        $instance_arr = $this->getAllByField('group_id', $group_id);

        if ( is_array($instance_arr) && $this->check_dependencies()) {
            foreach ($instance_arr as &$instance) {

                $component = $this->component_model->get_component($instance['component_id']);
                $instance = $this->factory($component, $instance);

                if ( !($instance instanceof DashboardComponentInterface) ) {
                    unset($instance);
                }

            }
        }

        return $instance_arr;
    }

    public function factory ($component, $instance) {

        if ( !is_array($component) || !is_array($instance) ) {
            return false;
        }

        $component_file = dirname(__FILE__) . "../../www/dashboard/component/{$component['class']}.php";

        if ( file_exists($component_file) ) {
            
            require_once($component_file);

            if ( class_exists($component['class']) && $component['class'] instanceof DashboardComponentInterface) {
                $option_default_model = new DashboardComponentOptionDefault($component['id']);
                $option_model = new DashboardComponentOption($instance['id']);

                $argument_arr = array($this->component_model, $this, $option_default_model, $option_model, $this->template_model);

                /* Load dependencies */
                $dependency_arr = $this->load_dependencies($component);
                $argument_arr = array_merge($argument_arr, $dependency_arr);

                /* Instantiate component */
                $r = new ReflectionClass($component['class']);
                $instance_obj = $r->newInstanceArgs($argument_arr);

                return $instance_obj;
            }

        }

        return false;
    }

    private function load_dependencies ($component) {
        $dependency_arr = explode(',', trim($component['dependencies']));

        if ( is_array($dependency_arr) ) {
            $dependency_path = dirname(__FILE__) . '../../library/';

            foreach ($dependency_arr as &$dependency) {
                $dependency = trim($dependency);

                if ( file_exists($dependency_path . $dependency . '.php') ) {
                    $dependency_file = "$dependency_path/$dependency.php";
                }
                elseif ( file_exists($dependency_path . strtolower($dependency) . '.php') ) {
                    $dependency_file = $dependency_path . strtolower($dependency) . '.php';
                } else {
                    return false;
                }

                require_once($dependency_file);

                if ( class_exists($dependency) ) {
                    $r = new ReflectionClass($dependency);

                    $dependency = $r->newInstance();
                }
            }
        }

        return $dependency_arr;

    }

    private function check_dependencies () {
        if ( !($this->component_model instanceof DashboardComponent) ) {
            return false;
        }

        if ( !($this->template_model instanceof Template) ) {
            return false;
        }

        return true;
    }
}

?>