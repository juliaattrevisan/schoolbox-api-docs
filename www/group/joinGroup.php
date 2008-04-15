<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//displays a list of groups available for membership

require_once("../include/session.php");
require_once("../include/include.php");

require_once("group.php");

class OutPut
{
	function showPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "group/joinGroup.htm");

		$this->t_body->set_var("TITLE", HEADING." - Manage Groups");
		
		include("../include/page.php");

		$this->t_body->set_block("tplBODY", "free_block", "free");	
		$this->t_body->set_block("tplBODY", "mod_block", "mod");	

		//setup db
		$this->db = new dbManager;

		//first output free groups
		$this->outputGroups(3);
	
		//now output moderated groups
		$this->outputGroups(2);


		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function outputGroups($type)
	{
		//type is the type of group to select 
		// 2 = moderated
		// 3 = free

		if($type == 2)
			$var = "mod";
		else if($type == 3)
			$var = "free";
		else 
			error(0);  //programming error

		
		$query = "SELECT name, id, userAccess.type FROM category, userGroups 
				LEFT JOIN userAccess ON cid = sid AND service = '1' AND uid = '".$_SESSION['id']."' 
				WHERE cid = id AND userGroups.type = '$type'";
		$this->db->query($query);
	
		if($this->db->countRows() > 0 )
		{
			for($x = 0; $row = $this->db->getNextArray(); $x++)
			{
				//if in group or awaiting authorisation tick box
				if(is_numeric($row['type'])) 
					$output = "<a href=\"joinGroup.php?leave=".$row['id']."\">
						<img src=\"/images/greentick.gif\" border=\"0\"></a>".$row['name'];
				else	
					$output = "<a href=\"joinGroup.php?join=".$row['id']."\">
						<img src=\"/images/emptybox.gif\" border=\"0\"></a>".$row['name'];
				
				$this->t_body->set_var(strtoupper($var), $output);
				$this->t_body->parse($var, $var."_block", true);
			}
		}
		else
			$this->t_body->set_var($var, "<tr><td>No groups to display</td></tr>");
	}

}

if($_GET['join'])
{
	$group = new Group(1, $_GET['join']);

	$type = $group->getGroupType();
	
	if($type == 2)	//if moderated group place in waiting
		$group->addMember($_SESSION['id'], 0);
	else if($type == 3) //if free just add
		$group->addMember($_SESSION['id'], 1);
	else
		error(37);
}
if($_GET['leave'])
{
	$group = new Group(1, $_GET['leave']);
	$group->deleteMember($_SESSION['id']);
}

$myPage = new OutPut();

$myPage->showPage();
?>
