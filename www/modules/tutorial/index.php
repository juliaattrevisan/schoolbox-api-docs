<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
$page = new SitePage();

if (isStaff($_SESSION['gid']) || $_SESSION['su'])
	localRedir('/modules/tutorial/list-attendance.php');
else
	localRedir('/modules/tutorial/list-event.php');

?>