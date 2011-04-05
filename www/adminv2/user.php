<?php
$this->setFile(TEMPLATE_content, 'adminv2/user.html');
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