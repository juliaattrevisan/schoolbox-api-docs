<?php
/**
 * Generates School-based permissions for reports to allow desired functionality across schools
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Permissions_Factory {

    /**
     * @static
     * @param string $schoolName
     * @return Report_Permissions
     */
    public static function build($schoolName = '') {

        // get from config if not specified
        if($schoolName == '') {
            $schoolName = Cfg::get('site_location');
        }

        // generate dynamically
        $className = ucwords(strtolower($schoolName)) . "_Report_Permissions";

        // if the school-specific permissions class exists, use that
        if(class_exists($className)) {
            return new $className();
        } else {
            // otherwise, return the generic permissions class
            return new Default_Report_Permissions();
        }
    }
}


/**
 *  THESE DON'T REALLY NEED TO LIVE IN SEPARATE FILES JUST YET
 *  (and if they do, don't forget to rearrange X_Report_Permissions to Report_Permissions_X)
 */

class Default_Report_Permissions implements Report_Permissions {

    /**
     * Default behaviour is that we only allow report access to parents, staff and supers
     * @param Report_Abstract $report
     * @return bool
     */
    public function checkPermissions(Report_Abstract $report) {
        if ( $report->getGroupHandler()->isParent() ||
            $report->getGroupHandler()->isSuperuser() ||
            $report->getGroupHandler()->isStaff()) {
            return true;
        }

        return false;
    }
}


class Wenona_Report_Permissions implements Report_Permissions {

    /**
     * @param Report_Abstract $report
     * @return bool
     */
    public function checkPermissions(Report_Abstract $report) {
        switch(get_class($report)) {

            case 'Report_Parent':
                if ($report->getGroupHandler()->isSuperuser() || $report->getGroupHandler()->isStaff()) {
                    return true;
                }
                /* If you are a parent, ensure that you have a child in the selected year level.
                *  If you don't have a child in this year level, you can't view it.
                */
                else if ($report->getGroupHandler()->isParent()) {

                    print_r($_SESSION);

                    if (is_array($_SESSION['children']) && count($_SESSION['children'])) {
                        foreach($_SESSION['children'] as $currentChild) {
                            if ($currentChild['year'] == $report->getYearLevel()) {
                                return true;
                            }
                        }
                    }
                }

                return false;

            default:
                $default = new Default_Report_Permissions();
                return $default->checkPermissions($report);
        }
    }
}


