<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');

$page = new SitePage();

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

try {
	$userMapper = new \Schoolbox\UserMapper();
	$user = $userMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(31);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = new \EOTC\EventPermissionMapper();
$permissionMapper->deleteForUser($user);

$page->localRedir('/modules/eotc/permission/modify/' . $user->getId());