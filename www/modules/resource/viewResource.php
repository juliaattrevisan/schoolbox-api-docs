<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// This page outputs a resource summary page

require_once("../../include/session.php");
require_once("../../include/include.php");
require_once("conf.php");
require_once("resource.php");
require_once("common.php");

if($_GET['id'])
{
	$resdb = new Resource;
	//get resource and check if it exists
	if(!$resource = $resdb->getResource($_GET['id']))
		error(101);
		

	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "modules/resource/resource.htm");
	include("../../include/page.php");
	$this->t_body->set_var("TITLE", HEADING." - Resource Booking System");

	$this->t_body->set_block("tplBODY", "equipment", "equipment_block");
	$this->t_body->set_block("tplBODY", "assignment", "assignment_block");
	$this->t_body->set_block("tplBODY", "edit_link", "edit_link_block");

	
	$this->t_body->set_var("ID", $resource['id']);
	$this->t_body->set_var("NAME", $resource['name']);
	$this->t_body->set_var("IMAGE","<img src=\"resource_images/{$resource['id']}.dat\" width=\"200\" height=\"200\">");
	$this->t_body->set_var("TYPE", $resource['type_name']);
	$this->t_body->set_var("DESCRIPTION", $resource['description']);
	$this->t_body->set_var("LOCATION", $resource['location_name']);
	$this->t_body->set_var("OWNER", $resource['firstname']." ".$resource['lastname']);

	if($resource['type'] == ROOM)
	{
		$equipList = $resdb->getAssignedResources($_GET['id']);

		if(is_array($equipList))
		{
			foreach($equipList as $equipment)
			{
				$equipmentList .=  "<a href=\"viewResource.php?id={$equipment['id']}\">{$equipment['name']}</a><br>";
			}
			$this->t_body->set_var("EQUIPMENTLIST", $equipmentList);
			$this->t_body->parse("equipment_block", "equipment");
		}
		else  //if no equipment assigned to room do not display equipment block
			$this->t_body->set_var("equipment_block", "");
		
		//rooms cant be assigned so dont show assignment block
		$this->t_body->set_var("assignment_block", ""); 
	}
	else
	{		
		//if equipment is assigned to a room output the room and link to it
		if($resource['room'])
		{
			$room = $resdb->getResource($resource['room']);
			$this->t_body->set_var("ROOM", "<a href=\"viewResource.php?id={$room['id']}\">{$room['name']}</a>");
			$this->t_body->parse("assignment_block", "assignment");
		}
		else //else dont output assignment block
			$this->t_body->set_var("assignment_block", ""); 
		//never output equipment block when resource is not a room
		$this->t_body->set_var("equipment_block", "");
	}
	
	
	$this->t_body->set_var("WEEK_SELECT", createWeekList(getCurrentWeek(mktime()), "week", ''));
	
	if($resource['perm'] == 1)
		$this->t_body->set_var("BOOK_RES", "<a href=\"modBooking.php?id={$resource['id']}\">make booking</a>");
	else if($resource['perm'] == 2)
		$this->t_body->set_var("BOOK_RES", "<a href=\"modBooking.php?id={$resource['id']}\">request booking</a>");
	else
		$this->t_body->set_var("BOOK_RES", "contact maintainer for bookings");
	
	
	if($_SESSION['su'] || $resource['owner'] == $_SESSION['id'])
		$this->t_body->parse("edit_link_block", "edit_link");
	else
		$this->t_body->set_var("edit_link_block", "");
		
	
	
	$this->t_body->pparse("finaloutput", "tplBODY");
}
?>