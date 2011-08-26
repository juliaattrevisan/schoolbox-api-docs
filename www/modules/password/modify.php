<?php
require_once(dirname(__FILE__) . '/../../../include/Config.php');
require_once(dirname(__FILE__) . '/../../../library/user.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');
require_once('createlm.php');

$this->setTitle(HEADING . ' - Change Password');
$this->setFile(TEMPLATE_content, '../ormond/modules/password/modify.html');

$userHandler = new User();
$user = $userHandler->getByID($_SESSION['id']);

$this->setVar(array(
	'UID' => $_SESSION['uid'],
	'USERNAME' => $user['username'],
));