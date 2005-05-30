<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//File allows the search and modifcation of users

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("user.php");

class modUser
{
	function modUser()
	{
		$this->db = new dbManager();
	}

	function displayUser($id)
	{
		//display user description page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify User");
		include_once("../include/page.php");	


		//fill out all fields
		global $userDb;
		$user = $userDb->getUserInfo($id);
		$this->t_body->set_var("FIRSTNAME", $user['firstname']);
		$this->t_body->set_var("LASTNAME", $user['lastname']);
		$this->t_body->set_var("ESCAPE_FIRSTNAME", addslashes($user['firstname']));
		$this->t_body->set_var("ESCAPE_LASTNAME", addslashes($user['lastname']));
		$this->t_body->set_var("USERNAME", $user['username']);
		$this->t_body->set_var("PASSWORD", $user['email_pass']);
		$this->t_body->set_var("SYNERGYID", $user['synergy_id']);
		if($user['local_email'])
			$this->t_body->set_var("LOCAL_EMAIL", "checked");
		else
			$this->t_body->set_var("LOCAL_EMAIL", "");
		$this->t_body->set_var("ALT_EMAIL", $user['alt_email']);		
		$this->t_body->set_var("YEAR", $user['year']);
		$this->t_body->set_var("ID", $id);
		if($user['su'] != "")
			$this->t_body->set_var("SUPERUSER", "checked");
		else
			$this->t_body->set_var("SUPERUSER", "");

		$groupListHTML = "";
		$query = "SELECT * FROM groups ORDER BY name";
		$this->db->dbDoQuery($query);
		while($line = $this->db->dbGetNextArray() )
		{		
			if($line['id'] == $user['group_id'])
				$groupListHTML .= "<option value=\"".$line['id']."\" selected>".$line['name']."</option>";
			else
				$groupListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
		}
		$this->t_body->set_var("GROUPLIST", $groupListHTML);

		$userCampus = $userDb->getUserCampus($id);
		$campusListHTML = "";
		$query = "SELECT * FROM luCampus WHERE id != '".ALLCAMPUS."' ORDER BY name";
		$this->db->dbDoQuery($query);
		while($line = $this->db->dbGetNextArray() )
		{
			if($this->isInCampus($userCampus, $line['id']))
				$campusListHTML .= "<input type=\"checkbox\" name=\"campus[]\" value=\"".$line['id']."\" checked> ".$line['name']."<br>";	
			else
				$campusListHTML .= "<input type=\"checkbox\" name=\"campus[]\" value=\"".$line['id']."\"> ".$line['name']."<br>";	
		}
		
		$this->t_body->set_var("CAMPUSLIST", $campusListHTML);

		$this->displayUserGroups($id);
		$this->displayUserPerms($id);

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function isInCampus($userCampus, $campus)
	{
		//quick n dirty to check if user is in a campus
		if(is_array($userCampus))
		{
			foreach($userCampus as $uCamp)
			{
				if($uCamp['id'] == $campus)
					return 1;
			}
		}
		return 0;
	}

	function displayUserGroups($id)
	{
		$this->t_body->set_block("tplBODY", "group_block", "group");
		
		global $userDb;
                $group = $userDb->getUserGroups($id);

		for($i=0;$group[$i]['id'];$i++)
		{
			$this->t_body->set_var("GROUP_NAME", $group[$i]['name']);
			
			//setup tick box
			if($group[$i]['type'] == 1)
				$this->t_body->set_var("BOX", 
				"<a href=\"/group/modUserGroup.php?write=$id&service=1&sid=".$group[$i]['id']."&user=$id\" border='0'>
				<img src=\"/newimages/emptybox.gif\" border=\"0\"></a>");
			else if($group[$i]['type'] == 2)
				$this->t_body->set_var("BOX", 
				"<a href=\"/group/modUserGroup.php?unwrite=$id&service=1&sid=".$group[$i]['id']."&user=$id\" border='0'>
				<img src=\"/newimages/greentick.gif\" border=\"0\"></a>");
			else
				$this->t_body->set_var("BOX", "");

			//setup owner button
			if($group[$i]['type'] == 3)
				$this->t_body->set_var("OWNER", "owner");
			else
				$this->t_body->set_var("OWNER", 
				"<a href=\"/group/modUserGroup.php?owner=$id&service=1&sid=".$group[$i]['id']."&user=$id\">
				make owner</a>");

			$this->t_body->set_var("REMOVE", 
				"<a href=\"/group/modUserGroup.php?delete=$id&service=1&sid=".$group[$i]['id']."&user=$id\">
				remove</a>");
		
			$this->t_body->parse("group", "group_block", true);
		}
		
		//if user in no groups just display nothing
		if($i==0)
			$this->t_body->set_var("group", "");

		//ouput group list
		$this->t_body->set_var("GROUP_DROP_DOWN", $this->getHTMLgroupList());

	}				

	function displayUserPerms($id)
	{
		$this->t_body->set_block("tplBODY", "perm_block", "perm");
		
		global $userDb;
                $perm = $userDb->getUserPermissions($id);

		for($i=0;$perm[$i]['service'];$i++)
		{
			$this->t_body->set_var("SERVICE_NAME", $perm[$i]['name']);
			$this->t_body->set_var("SERVICE_ID", $perm[$i]['sid']);
			$this->t_body->set_var("SERVICE_TYPE", $perm[$i]['type']);

			$this->t_body->set_var("SERVICE_REMOVE", 
				"<a href=\"/group/modUserGroup.php?delete=$id&service=".$perm[$i]['service']."&sid=".$perm[$i]['sid']."&user=$id\">remove</a>");
		
			$this->t_body->parse("perm", "perm_block", true);
		}
		
		$query = "SELECT * FROM luServices";
		$this->db->dbDoQuery($query);
		$serviceHTML = "<select name=\"service\" class=\"formfield\">";
		$serviceHTML .= "<option value=\"0\"><- Please Select a Service -></option>"; 

		while($line = $this->db->dbGetNextArray() )
		{
			$serviceHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
		}
		
		$this->t_body->set_var("SERVICE_DROP_DOWN", $serviceHTML);
		
		
		//if user in no groups just display nothing
		if($i==0)
			$this->t_body->set_var("perm", "");


	}				

	function getHTMLgroupList()
	{
		//make a drop down of all available folder groups
		$query = "SELECT name, id FROM category, userGroups WHERE cid = id ORDER BY name";

		$this->db->dbDoQuery($query);

		$groupHTML = "<select name=\"sid\" class=\"formfield\">";
		$groupHTML .= "<option value=\"0\"><- Please Select a Group -></option>"; 

		while($line = $this->db->dbGetNextArray() )
		{
			$groupHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
		}
	
		$groupHTML .= "</select>";

		return $groupHTML;

	}

	function displayFindUser()
	{
		//display search for user page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/findUser.htm");
		$this->t_body->set_var("TITLE", "MGS Portal - Find User");
		include_once("../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayUserList()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/listUser.htm");
		$this->t_body->set_var("TITLE", "MGS Portal - Find User");
		include_once("../include/page.php");	
		$this->t_body->set_block("tplBODY", "userlist", "user_block");
		//we assume that the connection is setup and ready to go.. yeah messy but optimized.
		while($userdetails = $this->db->dbGetNextArray())
		{
			$this->t_body->set_var("FIRSTNAME", $userdetails['firstname']);
			$this->t_body->set_var("LASTNAME", $userdetails['lastname']);
			$this->t_body->set_var("USERNAME", $userdetails['username']);
			$this->t_body->set_var("GROUP", $userdetails['group']);
			$this->t_body->set_var("SYNERGYID", $userdetails['synergy_id']);
			$this->t_body->set_var("ID", $userdetails['id']);
			$this->t_body->parse("user_block", "userlist", true);
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}
		
	function searchUser($keyword)
	{
		trim($keyword);

		if(is_numeric($keyword)) //synergy id search
			$query = "SELECT user.id, firstname, lastname, username, groups.name as `group`, synergy_id FROM user LEFT JOIN groups ON user.group_id = groups.id WHERE synergy_id = '$keyword'";
		else
			$query = "SELECT user.id, firstname, lastname, username, groups.name as `group`, synergy_id FROM user LEFT JOIN groups ON user.group_id = groups.id WHERE lastname LIKE '%".$keyword."%' || 
				username LIKE '%".$keyword."%' || firstname LIKE '%".$keyword."%'";

		$this->db->dbDoQuery($query);
		$matchedUsers = $this->db->dbCountRows();
		if($matchedUsers == 1)
		{
			$user = $this->db->dbGetNextArray();
			$this->displayUser($user['id']);
		}
		else if($matchedUsers > 1)
		{
			$this->displayUserList();
		}
		else
			error(31);  //no users match search
		
	}

		
}

$userDb = new user;
$myPage = new modUser;


if(isset($_POST['delete']) && isset($_POST['id']) && $_POST['delete'] && $_POST['id'])
{
	$userDb->deleteUser($_POST['id'], 1);
	localRedir("/admin/formValid.php?msg=User Deleted Sucessfully&loc=/admin/modUser.php");
}
else if(isset($_POST['username']) && isset($_POST['group']) && isset($_POST['firstname']) && 
		isset($_POST['lastname']) && isset($_POST['campus']) && isset($_POST['id']) && $_POST['id'] && 
		$_POST['username'] && $_POST['firstname'] && $_POST['lastname'] && $_POST['group'] && $_POST['campus'])
{
	$userDb->updateUser($_POST['id'], $_POST['username'], $_POST['firstname'], $_POST['lastname'], $_POST['synergyid'], 
			$_POST['local_email'], $_POST['alt_email'], $_POST['group'], $_POST['password'], $_POST['campus'], 
			$_POST['su'], $_POST['year']);
	$myPage->displayUser($_POST['id']);
}
else if(isset($_GET['search']) && $_GET['search'])
{
	$users = $myPage->searchUser($_GET['search']);
}
else if(isset($_POST['addgroup']) && $_POST['addgroup'])
{
	include("../group/group.php");
	$group = new Group(1, $_POST['addgroup']);

	$group->addMember($_POST['id'], 1);
	$myPage->displayUser($_POST['id']);

}
else if(isset($_GET['id']) && $_GET['id'])		
{
	$myPage->displayUser($_GET['id']);
}
else
	$myPage->displayFindUser();



