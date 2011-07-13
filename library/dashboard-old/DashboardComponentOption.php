<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */


class DashboardComponentOption extends DeprecatedModel {
    
    public $_table = 'dashboard_component_option';

    public $_index = 'id';

    private $option_default;

    private $instance_id;

    private $data;

    public function __construct ($instance_id, DashboardComponentOptionDefault $option_default) {

        $this->instance_id = $instance_id;

        $this->option_default = $option_default;

        $this->data = $this->get_options();

        parent::DeprecatedModel();
    }

    public function get_options () {
        if ( !empty($this->data) ) {
            return $this->data;
        }

        $option_arr = $this->getAllByField('instance_id', $this->instance_id);

        if ( is_array($option_arr) ) {
            foreach ($option_arr as &$option) {

                $key = $option['key'];

                $option_arr[$key] = $option;

                unset($option);
                
            }

            $this->data = $option_arr;
        }

        return $option_arr;
    }

    public function get_option_by_key ($key) {
        if ( isset($this->data->$key) ) {
            return $this->data->$key;
        }

        $option = $this->getByArray(array(
            'instance_id' => $this->instance_id,
            'key'         => $key
        ));

        if ( is_array($option) ) {
            $key = $option['key'];

            $this->data[$key] = $option;
        }

        return $option;
    }

    public function set_option ($key, $value) {
        $option = $this->getByField('key', $key);

        if ( is_array($option) ) {
            $option['value'] = $value;

            $this->update($option);
        }
        else {
            $option = array(
                'instance_id' => $this->instance_id,
                'key'         => $key,
                'value'       => $value
            );

            $option['id'] = $this->create($option);
        }

        $this->data[$key] = $option;

        return $option;
    }

    public function __get ($name) {
        if ( isset($this->$name) ) {
            return $this->$name;
        }

        if ( is_array($this->data[$name]) ) {
            return $this->data[$name];
        }
        
        if ( isset($this->option_default->$name) ) {
            return $this->option_default->$name;
        }

        return $this->$name;
    }

    public function __set ($name, $value) {
        if ( isset($this->$name) ) {
            return $this->$name = $value;
        }

        if ( !is_array($this->option_default->$name) ) {
            $option = $this->get_option_by_key($name);

            $option['value'] = $value;

            return $option;
        }

        return $this->$name = $value;
    }
}