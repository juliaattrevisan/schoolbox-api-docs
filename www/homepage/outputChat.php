<?php

require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';
$chat = new Chat($config);
$group = new Group();

if (empty($config['state'])) {
    $config['state'] = $default['state'];
}

$this->setBlock('config', 'chat_state', 'chat_state_block');

foreach (array('on', 'off') as $state) {
    $this->setVar('COMPONENT_CONFIG_CHAT_STATE', $state);
    $this->setVar('COMPONENT_CONFIG_CHAT_STATE_SELECTED', $state == $config['state']? ' selected="selected"' : '');
    $this->parse("chat_state_block", "chat_state", true);    
}

if ($config['state'] == 'on') {
    $this->setVar('CHAT_INIT_NAME', formatName($_SESSION));

    $users = $chat->poll($categoryID, $_SESSION['id'], '-10 seconds');
    $this->setBlock($componentHandle, 'chat_user', 'chat_user_block');

    if (!empty($users)) {
        foreach ($users as $user) {
            $this->setVar('CHAT_NAME', formatName($user));
            $this->parse('chat_user_block', 'chat_user', true);
        }
    }

    $messages = $chat->receive($categoryID, '-15 minutes');
    $this->setBlock($componentHandle, 'chat_message', 'chat_message_block');

    if (!empty($messages)) {
        foreach ($messages as $message) {
            $timestamp = new DateTime($message['timestamp']);

            $this->setVar(array(
                'CHAT_SENDER'    => formatName($message),
                'CHAT_TIMESTAMP' => $timestamp->format('H:i:s'),
                'CHAT_MESSAGE'   => scrubText($message['message'])
            ));

            $this->parse('chat_message_block', 'chat_message', true);
        }

        $this->setVar('CHAT_INIT_TIMESTAMP', $timestamp->format('Y-m-d H:i:s'));
    } else {
        $timestamp = new DateTime();

        $this->setVar(array(
            'CHAT_INIT_TIMESTAMP' => $timestamp->format('Y-m-d H:i:s'),
            'chat_message_block'  => ''
        ));
    }
} else {
    // display chat closed
}


?>