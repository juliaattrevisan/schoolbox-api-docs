<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class DashboardComponentInstance extends DeprecatedModel {
    
    public $_table = 'dashboard_component_instance';

    public $_index = 'id';

    public function __construct (DashboardComponent $component_model, Template $template_model) {
        $this->component_model = $component_model;
        $this->template_model = $template_model;

        parent::DeprecatedModel();
    }

    public function get_instances ($group_id) {
        $instance_arr = $this->getAllByField('group_id', $group_id);

        if ( is_array($instance_arr) ) {
            foreach ($instance_arr as $i => &$instance) {

                $factory = DashboardComponentFactory::get_instance();

                $component = $this->component_model->get_component($instance['component_id']);
                $instance = $factory->build($component, $instance);

                if ( !($instance instanceof DashboardComponentInterface) ) {
                    unset($instance_arr[$i]);
                }

            }
        }

        return $instance_arr;
    }

}

?>