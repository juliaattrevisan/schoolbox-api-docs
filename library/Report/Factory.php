<?php
/**
 * Report Factory for generating concrete instances of reports.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Report_Factory {

    // provide optional type safety
    private static $typesafe = false;
    private static $allowed = array('group', 'activity');

    /**
     * @static
     * @param $reportType
     * @return Report_IReport
     */
    public static function build($reportType, $page) {
        if(Report_Factory::$typesafe) {
            if(!in_array(strtolower($reportType), Report_Factory::$allowed));
            // don't return anything
            return null;
        }

        // get the class to be implemented
        $class = "Report_" . ucwords(strtolower($reportType));

        if(!class_exists($class))
            // not found
            return null;

        // instantiate
        return new $class($page);
    }
}

?>