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
require_once("../group/group.php");

class modUser
{
	function modUser()
	{
		$this->db = new dbManager();
	}

	function displayAddUser()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add User");
		include_once("../include/page.php");	
		$this->t_body->set_var('ACTION', 'Create');
		
		//blank all fields
		$this->t_body->set_var("TITLELIST", $this->titleList(0));
		$this->t_body->set_var("FIRSTNAME", '');
		$this->t_body->set_var("LASTNAME", '');
		$this->t_body->set_var("ESCAPE_FIRSTNAME", '');
		$this->t_body->set_var("ESCAPE_LASTNAME", '');
		$this->t_body->set_var("USERNAME", '');
		$this->t_body->set_var("PASSWORD", '');
		$this->t_body->set_var("SYNERGYID", '');
		$this->t_body->set_var("LOCAL_EMAIL", '');
		$this->t_body->set_var("ALT_EMAIL", '');		
		$this->t_body->set_var("YEAR", '');
		$this->t_body->set_var("ID", '');
		$this->t_body->set_var("SUPERUSER", '');
		$this->t_body->set_var("CAMPUSLIST", $this->campusList(0));
		$this->t_body->set_var("GROUPLIST", $this->groupList(0));
		
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->set_var("delete_button_block", "");
		
		//do not display group and permission management
		$this->t_body->set_block("tplBODY", "login", "login_section");
		$this->t_body->set_block("tplBODY", "groups", "group_section");
		$this->t_body->set_block("tplBODY", "perms", "perm_section");
		$this->t_body->set_block("tplBODY", "action_log", "action_section");
		$this->t_body->set_block("tplBODY", "login_log", "login_block");
		$this->t_body->set_var("login_section","");
		$this->t_body->set_var("group_section","");
		$this->t_body->set_var("perm_section","");
		$this->t_body->set_var("action_log","");
		$this->t_body->set_var("login_log","");
		
		
		$this->t_body->pparse("finaloutput", "tplBODY");
		
	}

	function displayUser($id)
	{
		//display user description page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/modUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify User");
		include_once("../include/page.php");	
		$this->t_body->set_var('ACTION', 'Update');

		//fill out all fields
		global $userDb;
		$user = $userDb->getUserInfo($id);
		$userCampus = $userDb->getUserCampus($id);
		
		$this->t_body->set_var("TITLELIST", $this->titleList($user['title']));
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

		$this->t_body->set_var("GROUPLIST", $this->groupList($user['group_id']));
		$this->t_body->set_var("CAMPUSLIST", $this->campusList($userCampus));

		$this->t_body->set_block("tplBODY", "login", "login_section");
		$this->t_body->parse("login_section", "login");

		$this->displayUserGroups($id);
		$this->displayUserPerms($id);
		$this->displayLogin($user['username']);
		$this->displayActivities($id);

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function titleList($titleName)
	{
		$titleListHTML = "";
		$query = "SELECT * FROM luTitle ORDER BY titleName";
		$this->db->dbDoQuery($query);
		while($line = $this->db->dbGetNextArray() )
		{		
			if($line['titleName'] == $titleName)
				$titleListHTML .= "<option value=\"".$line['titleName']."\" selected>".$line['titleName']."</option>";
			else
				$titleListHTML .= "<option value=\"".$line['titleName']."\">".$line['titleName']."</option>";
		}
		return $titleListHTML;
	}
	
	function campusList($userCampus)
	{
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
		return $campusListHTML;
	}

	function groupList($groupID)
	{
		$groupListHTML = "";
		$query = "SELECT * FROM groups ORDER BY name";
		$this->db->dbDoQuery($query);
		while($line = $this->db->dbGetNextArray() )
		{		
			if($line['id'] == $groupID)
				$groupListHTML .= "<option value=\"".$line['id']."\" selected>".$line['name']."</option>";
			else
				$groupListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
		}
		return $groupListHTML;
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
		$this->t_body->set_block("tplBODY", "groups", "group_section");
		$this->t_body->set_block("groups", "group_block", "group");
		
		global $userDb;
                $group = $userDb->getUserGroups($id);

		for($i=0;$group[$i]['id'];$i++)
		{
			$this->t_body->set_var("GROUP_NAME", $group[$i]['name']);
			
			//setup tick box
			if($group[$i]['type'] == 1)
				$this->t_body->set_var("BOX", 
				"<a href=\"/group/modUserGroup.php?write=$id&service=1&sid=".$group[$i]['id']."&user=$id\" border='0'>
				<img src=\"/images/emptybox.gif\" border=\"0\"></a>");
			else if($group[$i]['type'] == 2)
				$this->t_body->set_var("BOX", 
				"<a href=\"/group/modUserGroup.php?unwrite=$id&service=1&sid=".$group[$i]['id']."&user=$id\" border='0'>
				<img src=\"/images/greentick.gif\" border=\"0\"></a>");
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
		
		$this->t_body->parse("group_section","groups");

	}				

	function displayActivities($id) {
		$this->t_body->set_block("tplBODY", "action_log", "action_section");
		
		$query = "SELECT object, action, time, c.name as folder, f.description as file, l.description as link 
				FROM cmsLog 
				LEFT JOIN category c ON c.id = objectId 
				LEFT JOIN file f ON f.id = objectId
				LEFT JOIN link l ON l.id = objectId 
				WHERE user = $id
				ORDER BY time DESC
				LIMIT 0,10";
				
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0) {
			while($result = $this->db->dbGetNextArray()) {
				$output = array (	'ACTION_DATE' 	=> date('d/m/Y G:i',strtotime( $result['time'] )),
									'ACTION_ACTION' => $result['action'],
									'ACTION_OBJECT' => $result['object'],
									'ACTION_NAME'	=> $result[$result['object']],
								);
				$this->t_body->set_var($output);
				$this->t_body->parse('action_section', 'action_log', true);
			}		
		}
		else
			$this->t_body->set_var('action_section', '');
	}

	function displayLogin($username) {
		$this->t_body->set_block("tplBODY", "login_log", "login_block");
		
		$query = "SELECT date, ip, login FROM login_log WHERE username like '$username' ORDER BY date DESC LIMIT 0,10";
				
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0) {
			while($result = $this->db->dbGetNextArray()) {
				$output = array (	'LOGIN_DATE' 	=> date('d/m/Y G:i',strtotime( $result['date'] )),
									'LOGIN_IP' => $result['ip'],
									'LOGIN_RESULT' => $result['login']?'Success':'Failed',
								);
				$this->t_body->set_var($output);
				$this->t_body->parse('login_block', 'login_log', true);
			}
		}		
		else
			$this->t_body->set_var('login_block', '');
	}

	function displayUserPerms($id)
	{
		$this->t_body->set_block("tplBODY", "perms", "perm_section");
		$this->t_body->set_block("perms", "perm_block", "perm");
		
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
			
		$this->t_body->parse("perm_section","perms");

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
		$this->t_body->set_var("TITLE", HEADING." - Find User");
		include_once("../include/page.php");
		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayUserList($queryString)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "admin/listUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Find User");
		include_once("../include/page.php");	
		$this->t_body->set_block("tplBODY", "userlist", "user_block");
		//we assume that the connection is setup and ready to go.. yeah messy but optimized.

		$this->db->dbDoQuery($queryString);
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
			$this->displayUserList($query);
		}
		else
			error(31);  //no users match search
		
	}

		
}

$userDb = new user;
$myPage = new modUser;


if(isset($_POST['permSubmit']) && $_POST['permSubmit'] == 'Add')
{
	$groupPerm = new Group($_POST['service'], $_POST['sid']);
	$groupPerm->addMember($_POST['uid'], $_POST['type']);
}

if(isset($_POST['delete']) && isset($_POST['id']) && $_POST['delete'] && $_POST['id'])
{
	$userDb->deleteUser($_POST['id'], 1);
	localRedir("/admin/formValid.php?msg=User Deleted Sucessfully&loc=/admin/modUser.php");
}
else if($_POST['username'] && $_POST['firstname'] && $_POST['lastname'] && $_POST['group'] && $_POST['campus'])
{
	if($_POST['id'])
	{
		$userDb->updateUser($_POST['id'], $_POST['username'], $_POST['title'], $_POST['firstname'], $_POST['lastname'], $_POST['synergyid'], 
			$_POST['local_email'], $_POST['alt_email'], $_POST['group'], $_POST['password'], $_POST['campus'], 
			$_POST['su'], $_POST['year']);
		$myPage->displayUser($_POST['id']);
	}
	else
	{
		if ($_POST['alt_email'])
			$mail_handler = 1;
		else
			$mail_handler = 0;
	
		if($id = $userDb->addUser($_POST['username'], $_POST['title'], $_POST['firstname'], $_POST['lastname'],
			 $_POST['synergyid'], $_POST['group'], $_POST['campus'], $_POST['year'], $_POST['password'], 
			 $_POST['local_email'], $_POST['local_email'], $_POST['alt_email'], $mail_handler))	
		{	
			localRedir("/admin/modUser.php?id=$id");
		}
		else
		{	
			error(0);
		}
	}
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
else if($_GET['adduser'])
{
	$myPage->displayAddUser();
}
else
	$myPage->displayFindUser();




