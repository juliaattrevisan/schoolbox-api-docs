<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// This page outputs a resource summary page

require("../../include/session.php");
require("../../include/include.php");
require("conf.php");
require("resource.php");

$this->t_body = new Template(TPL_directory);
$this->t_body->set_file("tplBODY", "modules/resource/resourceList.htm");
include("../../include/page.php");
$this->t_body->set_var("TITLE", HEADING." - Resource Booking System");

$resdb = new Resource;

if($_SESSION['resourcebooking']['start'])
	$resources = $resdb->getAvailableResources($_SESSION['resourcebooking']['start'], $_SESSION['resourcebooking']['end']);
else
	$resources = $resdb->getAllResources();

$roomList = '<ul>';
$equipList = '<ul>';

foreach($resources as $res)
{
	if($res['type_type'] == 'room')
	{
		if($resLast['type_name'] != $res['type_name'])
		{
			$roomList .= "</ul><b>{$res['type_name']}</b><ul>";
		}
	
		$roomList .= "<li><a href=\"viewResource.php?id={$res['id']}\">{$res['name']}</a></li>";
	}
	else
	{
		if($resLast['type_name'] != $res['type_name'])
		{
			$equipList .= "</ul><b>{$res['type_name']}</b><ul>";
		}
		
		$equipList .= "<li><a href=\"viewResource.php?id={$res['id']}\">{$res['name']}</a></li>";
		
	}
	
	$resLast = $res;
}

$this->t_body->set_var("EQUIPMENT_LIST", $equipList);
$this->t_body->set_var("ROOM_LIST", $roomList);

//only superusers can add new resources for now
$this->t_body->set_block("tplBODY", "add_resource", "add_resource_block");	
if($_SESSION['su'])
	$this->t_body->parse("add_resource_block", "add_resource");
else
	$this->t_body->set_var("add_resource_block", "");
	

$this->t_body->pparse("finaloutput", "tplBODY");
?>
