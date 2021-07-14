<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;

// Was file directly accessed (ie not a SitePage include)?
if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir(\preg_replace('/.php$/', '', $_SERVER['SCRIPT_NAME']));
}
/** @var SitePage $this */

// Check if module is enabled
if (!Config::get('staff_directory_enabled')) {
    $this->pageNotFound();
}

/** @var \Schoolbox\User $currentUser */
$currentUser = $_SESSION['user'];

// If policy allows parents let them in, else only allow staff
if (
    !(Config::get('report_staff_access') && $currentUser->getRole()->isParent()) &&
    !($currentUser->getRole()->isStaff() && $currentUser->getRole()->getViewStaffDirectory())
) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$parentReport = new Report_Staff($this);
$parentReport->render();
