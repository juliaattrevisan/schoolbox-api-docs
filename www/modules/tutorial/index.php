<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isStaff($_SESSION['gid']) || $_SESSION['su']) {
	SitePage::localRedir('/modules/tutorial/attendance/list.php');
}
else {
	SitePage::localRedir('/modules/tutorial/event/list.php');
}

