<?php
/**
 * Permission ModifyForm
 *
 * Process the EOTC Administrator settings
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use EOTC\Authenticator;
use EOTC\EventPermission;
use EOTC\EventPermissionMapper;
use EOTC\EventStatus;
use EOTC\EventStatusMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

new Authenticator($page);

/** @var User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$sessionUser->isSuperuser()) {
	error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

try {
	/** @var User $user */
	$user = UserMapper::getInstance()->findById($_POST['staff']);
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
                try {
                    /** @var Campus $campus */
                    $campus = CampusMapper::getInstance()->findById($currentCampus);

                    /** @var EventStatus $status */
                    $status = EventStatusMapper::getInstance()->findById($currentStatus);

                    /** @var EventPermission $permission */
                    $permission = EventPermissionMapper::getInstance()->createModel();
                    $permission->setUser($user);
                    $permission->setCampus($campus);
                    $permission->setStatus($status);
                    $permission->setAllowed($settings['allowed']);
                    $permission->setSubscribed($settings['subscribed']);

                    $permissionMapper->save($permission);
                }
                catch (MapperException $e) {

                    /* noop
                     * could not save at least one permission due to missing campus and/or status
                     */
                }
			}
		}
	}
}

$page->localRedir('/modules/eotc/permission/modify');
