<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

require_once dirname(__FILE__) . '/../../../library/dashboard/DashboardComponentInterface.php';

 
class TimetableComponent extends DashboardComponentInterface {
    private $external_timetable;

    public function init (ExternalTimetable $external_timetable) {
        $this->external_timetable = $external_timetable;
    }

    public function execute() {
        
    }

    public function render($template_handle) {
        
    }
}

?>