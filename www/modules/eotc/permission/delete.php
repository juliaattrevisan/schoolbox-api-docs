<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// not in SitePage context?
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

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
} catch (\Schoolbox\Mapper\Exception $e) {
    error(31);
}

/* Delete all perms for this user, and re-add new ones
 */
$permissionMapper = \EOTC\EventPermissionMapper::getInstance();
$permissionMapper->deleteForUser($user);

$page->localRedir('/modules/eotc/permission/modify/' . $user->getId());
