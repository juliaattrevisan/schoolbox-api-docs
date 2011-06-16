<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */


class DashboardComponent extends Model {
    
    public $_table = 'dashboard_component';

    public $_index = 'id';

    public function get_component ($id) {
        $component = $this->getByID($id);

        return $component;
    }
}

?>