<?php
require_once(dirname(__FILE__) . '/../../../library/Config.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');

$groupHandler = new Group();

// If policy allows parents let them in, else only allow staff
if (!(Config::get('report_staff_access') == 1 && $groupHandler->isParent() ) &&
    !$groupHandler->isStaff()) {
    error(19);
}

$parentReport = new Report_StaffDepartment($this);
$parentReport->render();
