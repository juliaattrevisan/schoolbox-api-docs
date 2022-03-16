<?php

/**
 * Permission Modify
 *
 * Assign EOTC Administratosr
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\Authenticator;
use EOTC\EventPermissionMapper;
use EOTC\EventStatusMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/** @var SitePage $this */
new Authenticator($this);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/permission/modify.html');
$this->setBlock(TEMPLATE_content, 'campus', 'campus_block');
$this->setBlock(TEMPLATE_content, 'status', 'status_block');
$this->setBlock(TEMPLATE_content, 'subscription', 'subscription_block');
$this->setBlock(TEMPLATE_content, 'permission', 'permission_block');

$this->setVar([
    'campus_block'     => '',
    'status_block'     => '',
    'permission_block' => '',
]);

// Set staff group IDs into the magicsuggest.
$groupHandler = new Group();
$this->setVar('STAFF_GROUP_TYPE_IDS', implode(';', $groupHandler->getStaffGroupTypeIds()));

/* Edit existing permissions for someone
 */
$userMapper            = UserMapper::getInstance();
$eventPermissionMapper = EventPermissionMapper::getInstance();

if (is_numeric($this->arg[0])) {
    try {
        /** @var User $user */
        $user = $userMapper->findById($this->arg[0]);
        $allPermissions   = $eventPermissionMapper->findByUser($user);
        $allSubscriptions = $eventPermissionMapper->findSubscriptionsByUser($user);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_USER_NOT_FOUND);
    }
} else {
    /* Create new permissions for someone
     */
    $allPermissions   = new Collection();
    $allSubscriptions = new Collection();
}

/* Accumulate all the campus names and remove dupes
 */
$campusIds = [];

foreach ($allPermissions as $currentPermission) {
    $campusIds[] = $currentPermission->getCampus()->getId();
}

$campusMapper = CampusMapper::getInstance();
$allCampuses  = $campusMapper->findAll()->sortBy('sequence', 'ASC');

foreach ($allCampuses as $currentCampus) {
    if (in_array($currentCampus->getId(), $campusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar([
        'CAMPUS_ID'       => $currentCampus->getId(),
        'CAMPUS_NAME'     => $currentCampus->getName(),
        'CAMPUS_SELECTED' => $selected,
    ]);

    $this->parse('campus_block', 'campus', true);
}

/* Accumulate all the status names and remove dupes
 */
$statusIds = [];

foreach ($allPermissions as $currentPermission) {
    $statusIds[] = $currentPermission->getStatus()->getId();
}

$statusMapper = EventStatusMapper::getInstance();

/* Ignore Rejected and Approved
 */
foreach ($statusMapper->findPermissionStatuses() as $currentStatus) {
    if (in_array($currentStatus->getId(), $statusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar([
        'STATUS_ID'       => $currentStatus->getId(),
        'STATUS_NAME'     => $currentStatus->getName(),
        'STATUS_SELECTED' => $selected,
    ]);

    $this->parse('status_block', 'status', true);
}

// Subscriptions: do not ignore rejected and approved
$statusIds = [];

foreach ($allSubscriptions as $currentSubscription) {
    $statusIds[] = $currentSubscription->getStatus()->getId();
}

foreach ($statusMapper->findAll() as $currentStatus) {
    if (in_array($currentStatus->getId(), $statusIds)) {
        $selected = 'checked="checked"';
    } else {
        $selected = '';
    }

    $this->setVar([
        'STATUS_ID'       => $currentStatus->getId(),
        'STATUS_NAME'     => $currentStatus->getName(),
        'STATUS_SELECTED' => $selected,
    ]);

    $this->parse('subscription_block', 'subscription', true);
}

/* Render everything else
 */
$availableUsers = [];
$allUserIds = $eventPermissionMapper->findUserIds();
if (count($allUserIds)) {
    $availableUsers = $userMapper->findByColumn('id', $allUserIds)->indexBy('id');
}

foreach ($availableUsers as $user) {
    $userPermissions = $eventPermissionMapper->findByColumn('user_id', $user->getId());

    $campusNames     = [];
    $allowedStatuses = [];
    $subscriptions   = [];

    /** @var \EOTC\EventPermission $currentPermission */
    foreach ($userPermissions as $currentPermission) {
        if ($currentPermission->getCampus() && !in_array($currentPermission->getCampus()->getName(), $campusNames)) {
            $campusNames[] = $currentPermission->getCampus()->getName();
        }
        if ($currentPermission->getAllowed() && !in_array($currentPermission->getStatus()->getName(), $allowedStatuses)) {
            $allowedStatuses[] = $currentPermission->getStatus()->getName();
        }
        if ($currentPermission->getSubscribed() && !in_array($currentPermission->getStatus()->getName(), $subscriptions)) {
            $subscriptions[] = $currentPermission->getStatus()->getName();
        }
    }

    $this->setVar(
        [
            'PERMISSION_USER_ID'              => $user->getId(),
            'PERMISSION_USER_FIRSTNAME'       => $user->getFirstname(),
            'PERMISSION_USER_LASTNAME'        => $user->getLastname(),
            'PERMISSION_CAMPUS_NAMES'         => implode(', ', $campusNames),
            'PERMISSION_STATUS_NAMES'         => implode(', ', $allowedStatuses),
            'PERMISSION_STATUS_SUBSCRIPTIONS' => implode(', ', $subscriptions),
        ]
    );

    $this->parse('permission_block', 'permission', true);
}
