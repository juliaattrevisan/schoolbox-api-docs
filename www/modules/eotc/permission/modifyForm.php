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

$userMapper = new \Schoolbox\UserMapper();

try {
	/* -_- magicsuggest sends this back at you in json_encoded format
	 * or as an int - i.e. there are two formats:
	 *
	 * [151]
	 * 151
	 */
	if (is_numeric($_POST['staff'])) {
		$user = $userMapper->findById($_POST['staff']);
	}
	else {
		$wtf = json_decode($_POST['staff']);
		$user = $userMapper->findById($wtf[0]);
	}
}
catch (\Exception $e) {
	error(31);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = new \EOTC\EventPermissionMapper();
$permissionMapper->deleteForUser($user);

if (is_array($_POST['campus']) && count($_POST['campus'])) {
	foreach ($_POST['campus'] as $currentCampus) {

		/* Apply the same perms for each campus
		 */
		if (is_array($_POST['status']) && count($_POST['status'])) {
			foreach ($_POST['status'] as $currentStatus) {
				$permission = new \EOTC\EventPermission();
				$permission->setUserId($user->getId());
				$permission->setCampusId($currentCampus);
				$permission->setStatusId($currentStatus);
				$permission->setCreatedAt($_SERVER['REQUEST_TIME']);
				$permission->setUpdatedAt($_SERVER['REQUEST_TIME']);

				$permissionMapper->save($permission);
			}
		}
	}
}

$page->localRedir('/modules/eotc/permission/modify');
