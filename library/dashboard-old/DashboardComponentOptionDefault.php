<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class DashboardComponentOptionDefault extends DeprecatedModel {

    public $_table = 'dashboard_component_option_default';

    public $_index = 'id';

    private $component_id;

    private $data;

    public function __construct ($component_id) {
        $this->component_id = $component_id;

        $this->data = $this->get_default_options($component_id);
    }

    public function get_default_options () {
        $default_option_arr = $this->getAllByField('component_id', $this->component_id);

        if ( is_array($default_option_arr) ) {
            foreach ($default_option_arr as &$default_option) {

                $key = $default_option['key'];

                $default_option_arr[$key] = $default_option;

                unset($default_option);

            }
        }

        return $default_option_arr;
    }

    public function get_default_option_by_key ($key) {
        $default_option = $this->getByArray(array(
            'component_id' => $this->component_id,
            'key'          => $key
        ));

        return $default_option;
    }

    public function __get ($name) {
        if ( isset($this->data[$name]) ) {
            return $this->data[$name];
        }

        return $this->$name;
    }

}

?>