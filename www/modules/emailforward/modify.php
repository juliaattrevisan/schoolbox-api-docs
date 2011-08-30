<?php
require_once(dirname(__FILE__) . '/../../../include/config.php');
require_once(dirname(__FILE__) . '/../../../library/user.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');
require_once('ormondLDAP.php');

$ldap = new OrmondLDAP();

$this->setTitle(HEADING . ' - Change Email Forward');
$this->setFile(TEMPLATE_content, 'modules/emailforward/modify.html');

$userHandler = new User();
$user = $userHandler->getByID($_SESSION['id']);

$this->setVar(array(
	'UID' => $_SESSION['uid'],
	'USERNAME' => $user['username'],
	'EMAIL' => $ldap->getLDAPemail($user['username'], $ldap->getLDAPdn($user['username'])),
));