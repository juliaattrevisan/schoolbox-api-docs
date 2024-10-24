<?php

/** @var SitePage $this */

use Schoolbox\Permission\Access\UserAccess;

try {
    $loggedInUser = $this->getDi()->get('session')->get('user');
} catch (Exception $e) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_USER_NOT_FOUND);
}
if (!$loggedInUser->isSuperuser() && !$loggedInUser->hasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL)) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}

/* Remove record if it existed
 */
if (is_numeric($_POST['id'])) {
    $configRecord = Calendar_Api_Config_Mapper::getInstance()->findById(Scrub::text($_POST['id']));

    Calendar_Api_Config_Mapper::getInstance()->delete(array($configRecord));
}

exit();
