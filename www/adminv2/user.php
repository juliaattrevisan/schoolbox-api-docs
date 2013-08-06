<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/user.html');
$this->setTitle('Admin - Users');
$this->setBlock(TEMPLATE_content, 'superusers', 'superusers_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'user');

$userHandler = new User();

$superuserList = $userHandler->getSuperusers();

if(is_array($superuserList)) {
    foreach($superuserList as $superuser) {

        $loginLog = $userHandler->getUserLogins($superuser['username'],1);

        $output = array(
			'SUPERUSER_NAME' => $userHandler->formatName($superuser),
			'SUPERUSER_LOGIN' => date(Cfg::get('date_short'),strtotime( $loginLog[0]['date'])),
			'SUPERUSER_ID' => $superuser['uid']
		);

        $this->setVar($output);
        $this->parse('superusers_block','superusers', true);
    }
}
?>
