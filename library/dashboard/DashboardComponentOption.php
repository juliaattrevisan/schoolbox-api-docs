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

class DashboardComponentOption extends Model {
    
    public $_table = 'dashboard_component_option';

    public $_index = 'id';

    public function get_options ($instance_id) {
        $options_arr = $this->getAllByField('instance_id', $instance_id);

        if ( is_array($options_arr) ) {
            foreach ($options_arr as &$option) {

                $key = $option['key'];

                $options_arr[$key] = $option;

                unset($option);

            }
        }

        return $options_arr;
    }

    public function get_option_by_key ($instance_id, $key) {
        $option = $this->getByArray(array(
            'instance_id' => $instance_id,
            'key'         => $key
        ));

        return $option;
    }
    
}

?>