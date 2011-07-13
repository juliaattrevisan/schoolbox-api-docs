<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

ini_set('display_errors',1);
error_reporting(E_ALL & ~E_NOTICE);

header("Content-type: application/json");

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;
$group_handler = new Group();
$chat_handler = new Chat();
$homepage_handler = new Homepage($category_id);

$method = $_POST['method'];
$category_id = $_POST['category_id'];

$config = $homepage_handler->getComponentConfig($_POST['component_id']);

if (empty($config['muted_users'])) {
    $config['muted_users'] = array();
}

/* Send message */
if ($method == 'send') {

    if (!in_array($_SESSION['id'], $config['muted_users']) && $config['state'] == 'Active') {
        $chat_handler->create_log($_POST['category_id'], $_POST['user_id'], $_POST['body']);

        $time = new DateTime();

        $data = array(
            'time' => $time->format('D M j Y H:i:s \G\M\TO (T)'),
        );

        echo json_encode($data);        
    }

}

/* Receive messages */
else if ($method == 'receive') {

    if (empty($_POST['last_poll']) || $_POST['last_poll'] == 'null') {
        $last_poll = new DateTime('-30 minutes');
    } else {
        $last_poll = new DateTime($_POST['last_poll']);
    }

    $log = $chat_handler->get_logs($category_id, $last_poll);

    if (empty($log)) {
        $log = array();
    }

    $time = new DateTime();

    $data = array(
        'time' => $time->format('D M j Y H:i:s \G\M\TO (T)'),
        'log' => $log
    );

    echo json_encode($data);

}

/* Poll */
else if ($method == 'poll') {
    $chat_handler->update_poll($category_id, $_SESSION['id']);

    /* Return list of active users */
    $chat_handler->prune_users($category_id);

    $users = $chat_handler->get_users($category_id);

    if (empty($users)) {
        $users = array();
    }

    $time = new DateTime();

    $data = array(
        'time' => $time->format('D M j Y H:i:s \G\M\TO (T)'),
        'users' => $users,
        'config' => $homepage_handler->getComponentConfig($_POST['component_id']),
    );

    echo json_encode($data);
}

/* Mute or unmute user */
else if ($method == 'mute') {
    $idx = array_search($_POST['user_id'], $config['muted_users']);

    if ($idx === false) {
        $config['muted_users'][] = $_POST['user_id'];
    } else {
        unset($config['muted_users'][$idx]);
    }

    $homepage_handler->setComponentConfig($_POST['component_id'], $config);
    
    $time = new DateTime();

    $data = array(
        'time' => $time->format('D M j Y H:i:s \G\M\TO (T)'),
        'config' => $config
    );

    echo json_encode($data);
}

/* Update or get config */
else if ($method == 'config') {
    
}

?>