<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage();

new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
	error(19);
}

try {
	/** @var \Schoolbox\User $user */
	$userMapper = \Schoolbox\UserMapper::getInstance();
	$user = $userMapper->findById($this->arg[0]);
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(31);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = \EOTC\EventPermissionMapper::getInstance();
$permissionMapper->deleteForUser($user);

$page->localRedir('/modules/eotc/permission/modify/' . $user->getId());
