<?php

use Schoolbox\Collection;
use Schoolbox\Role;
use Schoolbox\RoleMapper;

/** @var SitePage $this */

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

// cloner code
$this->setBlock(TEMPLATE_content, 'select_clone', 'select_clone_block');
$this->setBlock('select_clone', 'select_clone_opt', 'select_clone_opt_block');
$this->setBlock('select_clone', 'select_new', 'select_new_block');
$this->setBlock('select_clone', 'new_role', 'new_role_block');

/** @var Collection|Role[] $roles */
$roles = RoleMapper::getInstance()->findAll();

$iterate = array('src' => 'Source', 'dest' => 'Destination');

foreach ($iterate as $key => $label) {
    $this->setVar(array(
        'select_clone_opt_block' => '',
        'CLONE_LABEL'            => $label,
        'CLONE_KEY'              => $key
    ));

    if ($key == 'src') {
        $this->setVar('select_new_block', '');
        $this->setVar('new_role_block', '');
    } else {
        $this->parse('select_new_block', 'select_new');
        $this->parse('new_role_block', 'new_role');
    }

    $this->dropDown('select_clone_opt', $roles, array(), TEMPLATE_content, array(
        'id'   => 'CLONE_OPT_VALUE',
        'name' => 'CLONE_OPT_NAME'
    ));

    $this->parse('select_clone_block', 'select_clone', true);
}
