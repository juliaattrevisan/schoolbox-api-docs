<?php
/* This allows users to change their own passwords
 */
$this->setFile(TEMPLATE_content, 'account/modify.html');
$this->setBlock(TEMPLATE_content, 'success', 'success_block');
$this->setBlock(TEMPLATE_content, 'portal_password_not_set', 'portal_password_not_set_block');
$this->setTitle('Change Password');

$groupHandler = new Group();

if (!in_array($_SESSION['gid'], $groupHandler->canChangePassword()) && !$_SESSION['su']) {
    error(19);
}

$this->setVar('success_block', '');
$this->setVar('portal_password_not_set_block', '');

if ($this->arg[0] == 'success') {
    $this->parse('success_block', 'success');
}
if ($this->arg[1] == 'portalPasswordNotSet') {
    $this->parse('portal_password_not_set_block', 'portal_password_not_set');
}
