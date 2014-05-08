<?php

$metadataMapper = new \EOTC\EventMetadataMapper();
$metadata = $metadataMapper->findById(1);

$permissionMapper = new \EOTC\EventPermissionMapper();
$allPermissions = $permissionMapper->findByColumnList(array(
	'status_id' => $metadata->getStatus()->getId()
));

$userIds = array();

foreach($allPermissions as $currentPermission) {
	$userIds[] = $currentPermission->getUserId();
}

$userMapper = new \Schoolbox\UserMapper();
$allUsers = $userMapper->findByColumn('id', $userIds);

foreach($allUsers as $currentUser) {
	new \Alaress_Probe($currentUser->getFullname());
}

exit();