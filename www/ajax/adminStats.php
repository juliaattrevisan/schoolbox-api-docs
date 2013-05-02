<?php
/**
 * JSON handler for ajax-loading admin summary modules.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;
$output = array();

if(isset($_POST['module'])) {
    switch($_POST['module']) {
        // cases mirror categories listed
        case 'users':
            $groupHandler = new Group();
            $summary = $groupHandler->userSummary();

            foreach($summary as $group) {
                $output[] = array( 'GROUP_ID'        => $group['id'],
                                   'GROUP_NAME'      => $group['name'],
                                   'GROUP_USER'      => $group['total'],
                                   'GROUP_LOGGED_IN' => $group['logged_in'] );
            }
            break;

        case 'service_www':
//          $a = microtime(true);                             // DEBUG
            if(@file_get_contents('http://schoolbox.com.au/schoolboxinstaller/status.html')) {
//              $b = number_format(microtime(true) - $a, 4);  // DEBUG
                $output[] = array('WWW_LIGHT'  => 'on',
                                  'WWW_STATUS' => 'Available'
//                              . '<br />( ' . $b . ' sec )'  // DEBUG
                );
            }
            else {
                $output[] = array('WWW_LIGHT'  => '',
                                  'WWW_STATUS' => 'Inaccessible');
            }

            break;


            // other cases unimplemented for now
        case 'storage':
        case 'memory':
        case 'service_db':
        case 'service_email':
        case 'service_ldap':
        case 'recent_activity':
        default:
            break;
    }
} else {
    // don't bother returning anything
    die();
}

// to test how it looks with delay
// sleep(2);

header("Content-type: application/json");
print json_encode($output);

?>
