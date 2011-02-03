<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';

$chat = new Chat($config);
$group = new Group();
$page = new SitePage;

/* Send */
if(!empty($_POST['message'])) {
    $timestamp = new DateTime();

    $data = array(
        'category_id' => $_POST['category_id'],
        'user_id'     => $_SESSION['id'],
        'timestamp'   => $timestamp->format('Y-m-d H:i:s'),
        'message'     => scrubText($_POST['message'])
    );

    $chat->send($data);
}

/* Receive */
$timestamp = new DateTime($_POST['timestamp']);
$messages = $chat->receive($_POST['category_id'], $timestamp->format('Y-m-d H:i:s'), $_SESSION['id']);

if (!empty($messages)) {
    foreach ($messages as &$message) {
        $timestamp = new DateTime($message['timestamp']);

        $message['sender'] = formatName($message);
        $message['timestamp'] = $timestamp->format('H:i:s');
    }
} else {
    $messages = array();
}

$users = $chat->poll($_POST['category_id'], $_SESSION['id'], '-10 seconds');

if (!empty($users)) {
    foreach ($users as &$user) {
        if ($group->getPermission($_POST['category_id'], $_SESSION['id']) > WRITE_ACCESS) {
            $config = array('state' => 'on');
        }

        $user['name'] = formatName($user);
    }
}

if ($config['state'] != 'on') {
    $config['state'] = 'off';

    $timestamp = new DateTime();

    array_push($messages, array(
        'sender'    => 'System',
        'timestamp' => $timestamp->format('H:i:s'),
        'message'   => '**** Chat is unavailable. ****'
    ));
}

$data = array(
    'config'           => $config,
    'timestamp'        => $timestamp->format('Y-m-d H:i:s'),
    'messages'         => $messages,
    'users'            => $users
);

header("Content-type: application/json");
echo json_encode($data);

?>
