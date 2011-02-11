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
    $this->setVar(array(
        'COMPONENT_CONFIG_CHAT_STATE' => $state,
        'COMPONENT_CONFIG_CHAT_STATE_SELECTED' => $state == $config['state']? ' selected="selected"' : ''
    ));

    $this->parse("chat_state_block", "chat_state", true);    
}

$this->setVar(array(
    'CHAT_COMPONENT_ID' => $component['homepageComponentLocationID'],
    'CHAT_USER_ID' => $_SESSION['id'],
    'CHAT_NAME_TITLE' => $_SESSION['title'],
    'CHAT_NAME_FIRSTNAME' => $_SESSION['firstname'],
    'CHAT_NAME_LASTNAME' => $_SESSION['lastname'],
));

?>