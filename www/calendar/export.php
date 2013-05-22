<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2008 Lektec Consulting         *
*                                                           *
*                                                           *
*  Script to export portal campus calendar events to vCal.  *
*                                                           *
*                                                           *
/************************************************************/

require_once '../../include/SitePage.php';
require_once '../include/iCalcreator.class.php';
require_once '../../library/calendar.php';

$page = new SitePage(false);

$factory = new Calendar_Api_Factory();
$api = $factory->produce();

$exporter = new Calendar_Event_Collection_Exporter(
	$api,
	strtotime('this year Jan 01 00:00:00'),
	strtotime('this year Dec 31 23:59:59'),
	$page
);
$exporter->renderAll();

exit();
