<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
************************************************************/

// This page outputs a page that allows modification/adding of a resource

require("../../include/session.php");
require("../../include/include.php");
require("conf.php");
require("resource.php");
require("common.php");

class page
{
	function page()
	{
		if($_POST['id'] && $_POST['name'] && $_POST['type'] && $_POST['perm'] && $_POST['location'])
		{
			//modify existing resource
			$resdb = new Resource;
			$resdb->modResource($_POST['id'], $_POST['name'], $_FILES['file'], $_SESSION['id'], $_POST['perm'], $_POST['type'], $_POST['description'], $_POST['location']);
			localRedir("/modules/resource/viewResource.php?id={$_POST['id']}");
		
		}
		else if($_POST['name'] && $_POST['type'] && $_POST['perm'] && $_POST['location'])
		{
			if($_SESSION['su'])
			{
				//create a new resource
				$resdb = new Resource;	
				$id = $resdb->addResource($_POST['name'], $_SESSION['id'], $_FILES['file'], $_POST['perm'], $_POST['type'], $_POST['description'], $_POST['location']);
				localRedir("/modules/resource/viewResource.php?id={$id}");
			}
			else
				error(101);
		
		}
		else if($_GET['mod'])
		{
			//get resource and check it exists
			$resdb = new Resource;
			if(!$resource = $resdb->getResource($_GET['mod']))
				error(101);
		
			//make sure modify is owner or su
			if(!($_SESSION['su'] || $resource['owner'] == $_SESSION['id']))
				error(101);
				
			//output details of resource identified by id
			$this->t_body = new Template(TPL_directory);
			$this->t_body->set_file("tplBODY", "modules/resource/addResource.htm");
			include("../../include/page.php");
			$this->t_body->set_var("TITLE", HEADING." - Resource Booking System - Modify Resource");
		
			$this->t_body->set_block("tplBODY", "equipment", "equipment_block");
			$this->t_body->set_block("tplBODY", "assignment", "assignment_block");
		
			
			$db = new DeprecatedDatabase;
			$db->dbConnect();
			
			$typeDropDown = typeDropDown(&$db, "type", $resource['type']);
			$permDropDown = permDropDown("perm", $resource['perm']);
			$locationDropDown = locDropDown(&$db, "location", $resource['location']);
		
			$this->t_body->set_var("ACTION", "Modify");
			$this->t_body->set_var("NAME", $resource['name']);
			$this->t_body->set_var("TYPEHTML", $typeDropDown);
			$this->t_body->set_var("DESCRIPTION", $resource['description']);
			$this->t_body->set_var("PERMHTML", $permDropDown);
			$this->t_body->set_var("LOCHTML", $locationDropDown);
		
			if($resource['type'] == ROOM )
			{
				$equipList = $resdb->getAssignedResources($_GET['id']);
		
				if(is_array($equipList))
				{
					$equipmentList = "<ul>";
					foreach($equipList as $equipment)
					{
							$equipmentList .= "<li><a href=\"modResource.php?id={$equipment['id']}\">{$equipment['name']}</a></li>";
					}
					$equipmentList .= "</ul>";
					$this->t_body->set_var("EQUIPLIST", $equipmentList);
				}
				else
					$this->t_body->set_var("EQUIPLIST", "");
					
				$this->t_body->parse("equipment_block", "equipment");
				$this->t_body->set_var("assignment_block", "");
			}
			else
			{	
				$roomDropDown = roomDropDown(&$db, "assignment", $resource['room']); 
				$this->t_body->set_var("ASSIGNHTML", $roomDropDown);
				$this->t_body->parse("assignment_block", "assignment");
				$this->t_body->set_var("equipment_block", "");
			}
			
			$this->t_body->set_var("IDFIELD", "<input type=\"hidden\" name=\"id\" value=\"{$resource['id']}\">");
		
			$this->t_body->pparse("finaloutput", "tplBODY");
		}
		else
		{
			//only superusers can add equipment for now
			if(!$_SESSION['su'])
				error(101);
				
			//display an empty form to allow users to add new resource
			$this->t_body = new Template(TPL_directory);
			$this->t_body->set_file("tplBODY", "modules/resource/addResource.htm");
			include("../../include/page.php");
			$this->t_body->set_var("TITLE", HEADING." - Resource Booking System - Create Resource");
		
			$this->t_body->set_block("tplBODY", "equipment", "equipment_block");
			$this->t_body->set_block("tplBODY", "assignment", "assignment_block");
		
			$typeDropDown = typeDropDown(&$db, "type");
			$permDropDown = permDropDown("perm");
			$locationDropDown = locDropDown(&$db, "location");
		
			$this->t_body->set_var("ACTION", "Add");
			$this->t_body->set_var("NAME","");
			$this->t_body->set_var("TYPEHTML", $typeDropDown);
			$this->t_body->set_var("DESCRIPTION", "");
			$this->t_body->set_var("PERMHTML", $permDropDown);
			$this->t_body->set_var("LOCHTML", $locationDropDown);
			$this->t_body->set_var("IDFIELD", "");
			
			$this->t_body->set_var("equipment_block", "");
			$this->t_body->set_var("assignment_block", "");
			
			$this->t_body->pparse("finaloutput", "tplBODY");
		
		}
	}
}

$page = new page;

?>