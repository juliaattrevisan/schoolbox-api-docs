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

if (strlen($_POST['id'])) {
    $bits = explode('_', $_POST['id']);

    $className = 'Calendar_Api_Config_Connector_' . Scrub::text($bits[1]);

    $config = new $className();
    $config->setEventType(Scrub::text($bits[0]));
    $config->setConnectorClass(Scrub::text($bits[1]));

    $configRecord = new Calendar_Api_Config_Record();
    $configRecord->setEventType(Scrub::text($bits[0]));
    $configRecord->setConnectorClass(Scrub::text($bits[1]));
    $configRecord->updateConfig($config);

    /* This is active
     */
    $configRecord->activate();

    /* Ram in this new record
     */
    Calendar_Api_Config_Mapper::getInstance()->save(array($configRecord));
}

exit();
