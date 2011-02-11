<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/homepage.php';

$data_recv = array();
$data_send = $_POST['send'];

$chat = new Chat($data_send['config']);
$group = new Group();
$page = new SitePage;

$homepage = new Homepage($data_send['config']['category_id']);

/* Check in */
$chat->poll($data_send['config']['category_id'], $_SESSION['id']);

/* Receive */
$data_recv = array(
    'users'    => $chat->users($data_send['config']['category_id']),
    'config'   => $data_send['config'],/*, $homepage->getComponentConfig($data_send['config']['component_id'])*/ // replace client's config
    'messages' => $chat->receive($data_send['config']['category_id']) // must be last
);

if (!empty($data_recv['users']['inactive'])) {
    $chat->users_remove($data_recv['users']['inactive']);
}

/* Send */
if (!empty($data_send['messages'])) {
    foreach ($data_send['messages'] as $message) {
        $message = array(
            'category_id' => $data_send['config']['category_id'],
            'user_id'     => $_SESSION['id'],
            'type'        => 'user',
            'time'        => $message['time'],
            'body'        => $message['body']
        );

        $chat->send($message);
    }
}

/* Set Last Time */
$data_recv['config']['last_time'] = $chat->_config['last_time']->format('Y-m-d H:i:s');

header("Content-type: application/json");
echo json_encode($data_recv);

?>
