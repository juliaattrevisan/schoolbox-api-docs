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
            $totalUser = 0;
            $totalLogin = 0;

            foreach($summary as $group) {
                $output[] = array( 'GROUP_ID'        => $group['id'],
                                   'GROUP_NAME'      => $group['name'],
                                   'GROUP_USER'      => $group['total'],
                                   'GROUP_LOGGED_IN' => $group['logged_in'] );
                $totalUser += $group['total'];
                $totalLogin += $group['logged_in'];
            }
            $output[] = array(  'GROUP_ID'=>'',
                                'GROUP_NAME'=>'Total',
                                'GROUP_USER'=>$totalUser,
                                'GROUP_LOGGED_IN'=>$totalLogin,);

            break;

        case 'service_www':
            $context = \Proxy\Server::getInstance()->getContext();
		    if(@file_get_contents('http://schoolbox.com.au/schoolboxinstaller/status.html', null, $context)) {
                $output[] = array('WWW_LIGHT'  => 'on',
                                  'WWW_STATUS' => 'Available'
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
