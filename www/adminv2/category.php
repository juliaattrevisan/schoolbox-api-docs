<?php
require_once SITE_root . '/library/user.php';

$this->setTitle('Admin - Category');
$this->setFile(TEMPLATE_content, 'adminv2/category.html');
$this->setBlock(TEMPLATE_content, 'group', 'group_block');

$userHandler = new User();

$allGroups = $userHandler->getGroupList();

foreach($allGroups as $currentGroup) {
	$this->setVar(array(
		'GROUP_ID' => $currentGroup['id'],
		'GROUP_NAME' => $currentGroup['name'],
	));

	$this->parse('group_block', 'group', true);
}

?>