<?php

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

$groupHandler = new Group();

// If policy allows parents let them in, else only allow staff
if (
    !(Config::get('report_staff_access') == 1 && $groupHandler->isParent() ) &&
    !$groupHandler->isStaff()
) {
    error(19);
}

$parentReport = new Report_Staff($this);
$parentReport->render();
