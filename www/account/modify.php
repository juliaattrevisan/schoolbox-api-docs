<?php
/* This allows users to change their own passwords
 */
$this->setFile(TEMPLATE_content, 'account/modify.html');
$this->setBlock(TEMPLATE_content, 'success', 'success_block');
$this->setTitle('Change Password');
$this->setTab('main');

if (!in_array($_SESSION['gid'], unserialize(GROUP_can_change_password)) && !$_SESSION['su']) {
	error(19);
}

$this->setVar('success_block', '');

if ($this->arg[0] == 'success') {
	$this->parse('success_block', 'success');
}
