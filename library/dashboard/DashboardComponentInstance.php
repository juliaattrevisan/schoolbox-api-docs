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

        if ( is_array($instance_arr)) {
            foreach ($instance_arr as $i => &$instance) {

                $component = $this->component_model->get_component($instance['component_id']);
                $instance = $this->factory($component, $instance);

                if ( !($instance instanceof DashboardComponentInterface) ) {
                    unset($instance_arr[$i]);
                }

            }
        }

        return $instance_arr;
    }

    public function factory ($component, $instance) {

        if ( !is_array($component) || !is_array($instance) ) {
            return false;
        }

        $component_file = dirname(__FILE__) . "/../../www/dashboard/component/{$component['class']}.php";

        if ( file_exists($component_file) ) {

            require_once($component_file);

            if ( class_exists($component['class'])) {
                $option_default_model = new DashboardComponentOptionDefault($component['id']);
                $option_model = new DashboardComponentOption($instance['id']);

                /* Prepare properties */
                $property_arr = $this->merge_properties($component, $instance);

                $argument_arr = array($property_arr, $this->component_model, $this, $option_default_model, $option_model, $this->template_model);

                /* Load dependencies */
                $dependency_arr = $this->load_dependencies($component);

                /* Instantiate component */
                $rc = new ReflectionClass($component['class']);
                $instance_obj = $rc->newInstanceArgs($argument_arr);

                /* Execute component init */
                $rm = new ReflectionMethod($component['class'], 'init');
                $rm->invokeArgs($instance_obj, $dependency_arr);

                return $instance_obj;
            }

        }

        return false;
    }

    private function load_dependencies ($component) {
        $dependency_arr = explode(',', trim($component['dependencies']));

        if ( is_array($dependency_arr) ) {
            $dependency_path = dirname(__FILE__) . '/../../library/';

            foreach ($dependency_arr as &$dependency) {
                $dependency = trim($dependency);

                if ( class_exists($dependency) ) {
                    $r = new ReflectionClass($dependency);

                    $dependency = $r->newInstance();
                } else {
                    $dependency = false;
                }
            }
        }

        return $dependency_arr;

    }

    private function merge_properties ($component, $instance) {
        $property_arr = array(
            'id'           => $instance['id'],
            'component_id' => $component['id'],
            'instance_id'  => $instance['id'],
            'group_id'     => $instance['group_id'],
            'class'        => $component['class'],
            'template'     => $component['template'],
            'position'     => $instance['position'],
            'order'        => $instance['order'],
            'name'         => $component['name'],
            'description'  => $component['description']
        );

        return $property_arr;
    }
}

?>