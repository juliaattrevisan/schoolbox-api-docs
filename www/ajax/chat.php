<?php

ini_set('display_errors',1);
error_reporting(E_ALL & ~E_NOTICE);

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/homepage.php';

$data_recv = array();
$data_send = $_POST['send'];

$category_id = $data_send['config']['category_id'];
$component_id = $data_send['config']['component_id'];

$chat = new Chat($data_send['config']);
$group = new Group();
$page = new SitePage;

$homepage = new Homepage($category_id);

/* Check in */
$chat->poll($category_id, $_SESSION['id']);

/* Receive */
$data_recv = array(
    'users'    => $chat->users($category_id),
    'messages' => $chat->receive($category_id, $data_send['config']['last_time']) // must be last
);

if (!empty($data_recv['users']['inactive'])) {
    $chat->users_remove($data_recv['users']['inactive']);
}

/* Send */
if (!empty($data_send['messages'])) {
    foreach ($data_send['messages'] as $message) {
        $message = array(
            'category_id' => $category_id,
            'user_id'     => $_SESSION['id'],
            'type'        => 'user',
            'time'        => $message['time'],
            'body'        => $message['body']
        );

        $chat->send($message);
    }
}

/* Config */
$component_config = $homepage->getComponentConfig($component_id);
$permission = $group->getPermission($category_id, $_SESSION['id']);

if (empty($data_recv['users']['admin'])) {
    $component_config['state'] = 'off';
}

if ($permission >= WRITE_ACCESS) {
    $component_config['muted_users'] = $data_send['config']['muted_users'];
}

$homepage->setComponentConfig($component_id, $component_config);

$data_recv['config'] = array(
    'last_time' => $chat->_config['last_time']->format('Y-m-d H:i:s'),
    'permission' => $permission,
);

$data_recv['config'] = array_merge($data_send['config'], $data_recv['config'], $component_config);

header("Content-type: application/json");
echo json_encode($data_recv);

?>