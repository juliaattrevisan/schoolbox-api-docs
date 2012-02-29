<?php
require_once(dirname(__FILE__) . '/../../../include/Config.php');
require_once(dirname(__FILE__) . '/../../../library/group.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');

$parentReport = new Report_Boarder($this);
$parentReport->render();
