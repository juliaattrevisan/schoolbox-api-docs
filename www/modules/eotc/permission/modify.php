<?php
/**
 * Permission Modify
 *
 * Assign EOTC Administratosr
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/permission/modify.html');
$this->setBlock(TEMPLATE_content, 'campus', 'campus_block');
$this->setBlock(TEMPLATE_content, 'status', 'status_block');
$this->setBlock(TEMPLATE_content, 'subscription', 'subscription_block');
$this->setBlock(TEMPLATE_content, 'permission', 'permission_block');

$this->setVar(
    array(
        'campus_block'     => '',
        'status_block'     => '',
        'permission_block' => ''
    )
);

// Set staff group IDs into the magicsuggest.
$groupHandler = new Group();
$this->setVar('STAFF_GROUP_TYPE_IDS', implode(';', $groupHandler->getStaffGroupTypeIds()));

/* Edit existing permissions for someone
 */
$userMapper            = \Schoolbox\UserMapper::getInstance();
$eventPermissionMapper = \EOTC\EventPermissionMapper::getInstance();

if (is_numeric($this->arg[0])) {
    try {
        /** @var \Schoolbox\User $user */
        $user = $userMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(31);
    }

    $allPermissions   = $eventPermissionMapper->findByUser($user);
    $allSubscriptions = $eventPermissionMapper->findSubscriptionsByUser($user);
} else {
    /* Create new permissions for someone
     */
    $allPermissions   = new \Schoolbox\Collection();
    $allSubscriptions = new \Schoolbox\Collection();
}

/* Accumulate all the campus names and remove dupes
 */
$campusIds = array();

foreach ($allPermissions as $currentPermission) {
    $campusIds[] = $currentPermission->getCampusId()->getId();
}

$campusMapper = \Schoolbox\CampusMapper::getInstance();
$allCampuses  = $campusMapper->findAll();

foreach ($allCampuses as $currentCampus) {
    if (in_array($currentCampus->getId(), $campusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar(
        array(
            'CAMPUS_ID'       => $currentCampus->getId(),
            'CAMPUS_NAME'     => $currentCampus->getName(),
            'CAMPUS_SELECTED' => $selected
        )
    );

    $this->parse('campus_block', 'campus', true);
}

/* Accumulate all the status names and remove dupes
 */
$statusIds = array();

foreach ($allPermissions as $currentPermission) {
    $statusIds[] = $currentPermission->getStatusId()->getId();
}

$statusMapper = \EOTC\EventStatusMapper::getInstance();

/* Ignore Rejected and Approved
 */
foreach ($statusMapper->findPermissionStatuses() as $currentStatus) {
    if (in_array($currentStatus->getId(), $statusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar(
        array(
            'STATUS_ID'       => $currentStatus->getId(),
            'STATUS_NAME'     => $currentStatus->getName(),
            'STATUS_SELECTED' => $selected
        )
    );

    $this->parse('status_block', 'status', true);
}

// Subscriptions: do not ignore rejected and approved
$statusIds = array();
foreach ($allSubscriptions as $currentSubscription) {
    $statusIds[] = $currentSubscription->getStatusId()->getId();
}
foreach ($statusMapper->findAll() as $currentStatus) {
    if (in_array($currentStatus->getId(), $statusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar(
        array(
            'STATUS_ID'       => $currentStatus->getId(),
            'STATUS_NAME'     => $currentStatus->getName(),
            'STATUS_SELECTED' => $selected
        )
    );

    $this->parse('subscription_block', 'subscription', true);
}

/* Render everything else
 */
$allUserIds = $eventPermissionMapper->findUserIds();

foreach ($allUserIds as $currentUserId) {
    $user = $userMapper->findById($currentUserId);

    $allPermissions   = $eventPermissionMapper->findByUser($user);
    $allSubscriptions = $eventPermissionMapper->findSubscriptionsByUser($user);

    /* Accumulate all the campus names and remove dupes
     */
    $campusNames = array();

    foreach ($allPermissions as $currentPermission) {
        $campusNames[$currentPermission->getCampusId()->getName()] = '';
    }
    foreach ($allSubscriptions as $currentPermission) {
        $campusNames[$currentPermission->getCampusId()->getName()] = '';
    }

    $allCampuses = implode(', ', array_keys($campusNames));

    /* Accumulate all the status names and remove dupes
     */
    $statusNames = array();
    foreach ($allPermissions as $currentPermission) {
        $statusNames[$currentPermission->getStatusId()->getName()] = '';
    }
    $statusSubscriptionNames = array();
    foreach ($allSubscriptions as $currentSubscription) {
        $statusSubscriptionNames[$currentSubscription->getStatusId()->getName()] = '';
    }

    $this->setVar(
        array(
            'PERMISSION_USER_ID'              => $user->getId(),
            'PERMISSION_USER_FIRSTNAME'       => $user->getFirstname(),
            'PERMISSION_USER_LASTNAME'        => $user->getLastname(),
            'PERMISSION_CAMPUS_NAMES'         => $allCampuses,
            'PERMISSION_STATUS_NAMES'         => implode(', ', array_keys($statusNames)),
            'PERMISSION_STATUS_SUBSCRIPTIONS' => implode(', ', array_keys($statusSubscriptionNames)),
        )
    );

    $this->parse('permission_block', 'permission', true);
}
