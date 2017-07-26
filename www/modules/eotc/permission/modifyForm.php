<?php
/**
 * Permission ModifyForm
 *
 * Process the EOTC Administrator settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use EOTC\Authenticator;
use EOTC\EventPermissionMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once(dirname(__FILE__) . '/../../../../library/Bootstrap.php');

$page = new SitePage();

new Authenticator($page);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
	error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

try {
	/** @var User $user */
	$userMapper = UserMapper::getInstance();
	$user = $userMapper->findById($_POST['staff']);
}
catch (MapperException $e) {
	error(ErrorMapper::ERROR_USER_NOT_FOUND);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = EventPermissionMapper::getInstance();
$permissionMapper->deleteForUser($user);

if (is_array($_POST['campus']) && count($_POST['campus'])) {
	foreach ($_POST['campus'] as $currentCampus) {

		/* Apply the same perms for each campus
		 */
		if (is_array($_POST['status']) && count($_POST['status'])) {
			foreach ($_POST['status'] as $currentStatus => $settings) {
				$permission = EventPermissionMapper::getInstance()->createModel();
				$permission->setUser($user->getId());
				$permission->setCampus($currentCampus);
				$permission->setStatus($currentStatus);
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
