<?php
/**
 * Permission ModifyForm
 *
 * Process the EOTC Administrator settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');

$page = new SitePage();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
	error(19);
}

try {
	/** @var \Schoolbox\User $user */
	$userMapper = \Schoolbox\UserMapper::getInstance();
	$user = $userMapper->findById($_POST['staff']);
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(31);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = \EOTC\EventPermissionMapper::getInstance();
$permissionMapper->deleteForUser($user);

if (is_array($_POST['campus']) && count($_POST['campus'])) {
	foreach ($_POST['campus'] as $currentCampus) {

		/* Apply the same perms for each campus
		 */
		if (is_array($_POST['status']) && count($_POST['status'])) {
			foreach ($_POST['status'] as $currentStatus => $settings) {
				$permission = new \EOTC\EventPermission();
				$permission->setUserId($user->getId());
				$permission->setCampusId($currentCampus);
				$permission->setStatusId($currentStatus);
				$permission->setAllowed($settings['allowed']);
				$permission->setSubscribed($settings['subscribed']);
				$permission->setCreatedAt($_SERVER['REQUEST_TIME']);
				$permission->setUpdatedAt($_SERVER['REQUEST_TIME']);

				$permissionMapper->save($permission);
			}
		}
	}
}

$page->localRedir('/modules/eotc/permission/modify');
