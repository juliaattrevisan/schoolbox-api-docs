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
$this->setBlock(TEMPLATE_content, 'campus', 'campus_block');
$this->setBlock(TEMPLATE_content, 'status', 'status_block');

/* Edit existing permissions for someone
 */
$userMapper = new \Schoolbox\UserMapper();
$eventPermissionMapper = new \EOTC\EventPermissionMapper();

try {
	$user = $userMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(31);
}

if (is_numeric($this->arg[0])) {
	$allPermissions = $eventPermissionMapper->findByUser($user);

	/* Accumulate all the campus names and remove dupes
	 */
	$campusIds = array();

	foreach ($allPermissions as $currentPermission) {
		$campusIds[] = $currentPermission->getCampusId()->getId();
	}

	$campusMapper = new \Schoolbox\CampusMapper();
	$allCampuses = $campusMapper->findAll();

	foreach ($allCampuses as $currentCampus) {
		if (in_array($currentCampus->getId(), $campusIds)) {
			$selected = 'checked="checked"';
		}
		else {
			$selected = '';
		}

		$this->setVar(array(
			'CAMPUS_ID' => $currentCampus->getId(),
			'CAMPUS_NAME' => $currentCampus->getName(),
			'CAMPUS_SELECTED' => $selected
		));

		$this->parse('campus_block', 'campus', true);
	}

	/* Accumulate all the status names and remove dupes
	 */
	$statusIds = array();

	foreach ($allPermissions as $currentPermission) {
		$statusIds[] = $currentPermission->getStatusId()->getId();
	}

	$statusMapper = new \EOTC\EventStatusMapper();
	$allStatuses = $statusMapper->findPermissionStatuses();

	/* Ignore Rejected and Approved
	 */
	foreach ($allStatuses as $currentStatus) {
		if (in_array($currentStatus->getId(), $statusIds)) {
			$selected = 'checked="checked"';
		}
		else {
			$selected = '';
		}

		$this->setVar(array(
			'STATUS_ID' => $currentStatus->getId(),
			'STATUS_NAME' => $currentStatus->getName(),
			'STATUS_SELECTED' => $selected
		));

		$this->parse('status_block', 'status', true);
	}
}
/* Create new permissions for someone
 */
else {
}

/* Render everything else
 */
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