<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/homepage.php';

$homepage = new Homepage($_POST['category_id']);
$config = $homepage->getComponentConfig($_POST['component_id']);

$chat = new Chat($config);
$group = new Group();
$page = new SitePage;

if ($group->getPermission($_POST['category_id'], $_SESSION['id']) > WRITE_ACCESS) {
    $config['state'] = 'on';
}

if ($config['state'] == 'on') {
    /* Send */
    if (!empty($_POST['message'])) {
        $timestamp = new DateTime();

        $data = array(
            'category_id' => $_POST['category_id'],
            'user_id' => $_SESSION['id'],
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'message' => scrubText($_POST['message'])
        );

        $chat->send($data);
    }

    /* Receive */
    if (!empty($_POST['timestamp'])) {
        $timestamp = new DateTime($_POST['timestamp']);
        $messages = $chat->receive($_POST['category_id'], $timestamp, $_SESSION['id']);
    } else {
        $messages = $chat->receive($_POST['category_id'], '-15 minutes');
    }

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
            // is there an active user with write permission
            if ($group->getPermission($_POST['category_id'], $user['user_id']) > WRITE_ACCESS) {
                $write_user = true;
            }

            $user['name'] = formatName($user);
        }
    }

    $config['state'] = $write_user ? 'on' : 'off';
    $homepage->setComponentConfig($_POST['component_id'], $config);

    $data = array(
        'config' => $config,
        'timestamp' => $timestamp->format('Y-m-d H:i:s'),
        'messages' => $messages,
        'users' => $users
    );
}

header("Content-type: application/json");
echo json_encode($data);

?>
