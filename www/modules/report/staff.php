<?php
require_once(dirname(__FILE__) . '/../../../include/Config.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');

$groupHandler = new Group();
if (!$groupHandler->isStaff()) {
	error(19);
}

$parentReport = new Report_Staff($this);
$parentReport->render();
