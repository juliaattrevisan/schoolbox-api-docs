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

//
// TODO pull user ID from token, and set into session
session_start();

$export_type = $_GET['export'];
$export_id   = intval($_GET['id']);
$api         = null;
switch ($export_type) {

	case 'personal':
		$api = new Calendar_Api_EventType_Event_Schoolbox_Personal(array());
		break;

	case 'group':
		$api = new Calendar_Api_EventType_Event_Schoolbox_Group(array());
		if ($export_id > 0) {
			$api->setGroupId($export_id);
		}
		else {
			//
			// Exit with error (bad request?)
		}
		break;

	case 'campus':
		$api = new Calendar_Api_EventType_Event_Schoolbox_Campus(array());
		if ($export_id > 0) {
			$api->setCampusId($export_id);
		}
		else {
			//
			// Exit with error (bad request?)
		}
		break;

	case 'all':
		$factory = new Calendar_Api_Factory();
		$api     = $factory->produce();
		break;

	default:
		break;
}

if ($api instanceof Calendar_Api_EventType_Interface) {
	$exporter = new Calendar_Event_Collection_Exporter(
		$api,
		$_SERVER['REQUEST_TIME'],
		strtotime('+1 year', $_SERVER['REQUEST_TIME']),
		$page
	);
	$exporter->renderAll();
}
else {
	//
	// Exit with error (bad request?)
}

exit();
