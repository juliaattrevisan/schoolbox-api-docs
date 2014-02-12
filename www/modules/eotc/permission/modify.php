<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'modules/eotc/permission/modify.html');
$this->setBlock(TEMPLATE_content, 'permission', 'permission_block');

/* Edit existing permissions for someone
 */
$userMapper = new \Schoolbox\UserMapper();

try {
	$user = $userMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(31);
}

if (is_numeric($this->arg[0])) {


}
/* Create new permissions for someone
 */
else {
}

/* Render everything else
 */
$eventPermissionMapper = new \EOTC\EventPermissionMapper();
$allUserIds = $eventPermissionMapper->findUserIds();

foreach ($allUserIds as $currentUserId) {
	$user = $userMapper->findById($currentUserId);

	$allPermissions = $eventPermissionMapper->findByUser($user);

	/* Accumulate all the campus names and remove dupes
	 */
	$campusNames = array();

	foreach ($allPermissions as $currentPermission) {
		$campusNames[$currentPermission->getCampusId()->getName()] = '';
	}

	$allCampuses = implode(', ', array_keys($campusNames));

	/* Accumulate all the status names and remove dupes
	 */
	$statusNames = array();

	foreach ($allPermissions as $currentPermission) {
		$statusNames[$currentPermission->getStatusId()->getName()] = '';
	}

	$allStatuses = implode(', ', array_keys($statusNames));

	$this->setVar(array(
		'PERMISSION_USER_FIRSTNAME' => $user->getFirstname(),
		'PERMISSION_USER_LASTNAME' => $user->getLastname(),
		'PERMISSION_CAMPUS_NAMES' => $allCampuses,
		'PERMISSION_STATUS_NAMES' => $allStatuses,
	));

	$this->parse('permission_block', 'permission', true);
}