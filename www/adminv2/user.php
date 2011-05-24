<?php
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/user.html');
$this->setTitle('Admin - Users');
$this->setBlock(TEMPLATE_content, 'superusers', 'superusers_block');

require_once SITE_root . '/library/user.php';
$userHandler = new User;

$superuserList = $userHandler->getSuperusers();

if(is_array($superuserList)) {
    foreach($superuserList as $superuser) {
        $output = array('SUPERUSER_NAME'    => formatName($superuser),
                        'SUPERUSER_LOGIN'   => '',
                        'SUPERUSER_ID'  => $superuser['uid']);
        $this->setVar($output);
        $this->parse('superusers_block','superusers', true);
    }
}
?>