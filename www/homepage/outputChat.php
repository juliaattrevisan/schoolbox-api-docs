<?php

require_once SITE_root . '/library/chat.php';
require_once SITE_root . '/library/group.php';
$chat = new Chat($config);
$group = new Group();

if (empty($config['state'])) {
    $config['state'] = $default['state'];
}

$this->setBlock('config', 'chat_state', 'chat_state_block');
$this->setBlock($componentHandle, 'chat_state_active', 'chat_state_active_block');
$this->setBlock($componentHandle, 'chat_state_inactive', 'chat_state_inactive_block');

foreach (array('Active', 'Inactive') as $state) {
    $this->setVar(array(
        'COMPONENT_CONFIG_CHAT_STATE' => $state,
        'COMPONENT_CONFIG_CHAT_STATE_SELECTED' => $state == $config['state']? ' selected="selected"' : ''
    ));

    $this->parse("chat_state_block", "chat_state", true);    
}

if ($config['state'] == 'Active') {
    $this->setVar('chat_state_inactive_block', '');
    $this->parse('chat_state_active_block', 'chat_state_active');
} else {
    $this->setVar('chat_state_active_block', '');

    if ($permission > 1) {
        $this->parse('chat_state_inactive_block', 'chat_state_inactive');
    } else {
        $this->setVar('chat_state_inactive_block', '');        
    }
}

$this->setVar(array(
    'CHAT_COMPONENT_ID' => $component['homepageComponentLocationID'],
    'CHAT_USER_ID'      => $_SESSION['id'],
    'CHAT_USER_NAME'    => scrubTEXT(formatName($_SESSION)),
    'CHAT_USER_PERM' => $group->getPermission($categoryID),
    'CHAT_STATE'        => $config['state'],
));

?>