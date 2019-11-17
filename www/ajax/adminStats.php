<?php
/**
 * JSON handler for ajax-loading admin summary modules.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use External\IConnector;
use Schoolbox\Database;
use Schoolbox\Engagement\Login;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage;
$output = array();
$now = new Alaress_Date_Format();

if(!isset($_POST['module'])) {
    // don't bother returning anything
    die();
}

switch($_POST['module']) {
    // cases mirror categories listed
    case 'users':
        /** @var IConnector $config */
        $config        = $kernel->get('config');
        $login         = new Login($this->getDi()->get('databaseInternal'), $config->get('session_length'));
        $summary       = $login->userSummary($now);
        $totalUser     = 0;
        $totalLogin    = 0;

        foreach($summary as $group) {
            $output[] = array( 'GROUP_ID'        => $group['id'],
                               'GROUP_NAME'      => Scrub::text($group['name']),
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
        if(@file_get_contents('https://repo.schoolbox.com.au/testfile.txt', null, $context)) {
            $output[] = array('WWW_LIGHT'  => 'success',
                              'WWW_STATUS' => 'Available'
            );
        } else {
            $output[] = array('WWW_LIGHT'  => 'alert',
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

// to test how it looks with delay
// sleep(2);

header("Content-type: application/json");
print json_encode($output);
