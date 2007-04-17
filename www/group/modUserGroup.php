<?php

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/staffonly.php");
require_once("../include/permission.php");

require_once("../admin/user.php");
require_once("group.php");

class modUserGroup
{

	function modUserGroup($service, $sid)
	{
		$this->db = new dbManager;
		
		$this->service = $service;
		$this->sid = $sid;
	}

	function displayBulk()
	{
		//display user description page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "group/bulkUserAdd.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add Bulk Members");
		include_once("../include/page.php");	
		
		//get title of group
		$query = "SELECT name FROM category WHERE id = '$this->sid'";
		$heading = $this->db->dbGetSingleRow($query);

		$this->t_body->set_var("HEADING", $heading['name']);	

		$this->t_body->set_var("SERVICE", $this->service);
		$this->t_body->set_var("SID", $this->sid);
		
		$this->t_body->pparse("finaloutput", "tplBODY");

	}

	function displayGroup()
	{
		//display user description page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "group/modUserGroup.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify User Group");
		include_once("../include/page.php");	

	
		//get title of group and type
		$query = "SELECT name, type FROM category, userGroups WHERE id = '$this->sid' AND cid = id";
		$heading = $this->db->dbGetSingleRow($query);

		$this->t_body->set_var("HEADING", $heading['name']);	

		//set type selected.
		for($x = 1; $x <= 3; $x++)
		{
			if($heading['type'] == $x)
				$this->t_body->set_var("TYPE".$x, "checked");
			else
				$this->t_body->set_var("TYPE".$x, "");
		}			

		global $group;
		$owner = $group->getOwner();
		if($owner != 0)
		{
			$this->t_body->set_var("OWNER", formatName(NULL, $owner));
			$this->t_body->set_var("OWNERID", $owner['uid']);
		}
		else
			$this->t_body->set_var("OWNER", "No Owner, Please contact Helpdesk");

		$this->t_body->set_var("SERVICE", $this->service);
		$this->t_body->set_var("SID", $this->sid);

		//get members of group
		$query = "SELECT title, firstname, lastname, uid, type FROM userAccess, user WHERE uid = id AND service = '$this->service'
			AND sid = '$this->sid' AND type < '3' AND type > '0'";
		$this->db->dbDoQuery($query);

		//setup member block
		$this->t_body->set_block("tplBODY", "member", "member_block");		
		if($this->db->dbCountRows() > 0)
		{
			while($member = $this->db->dbGetNextArray())
			{
				$this->t_body->set_var("MEMBER", formatName(NULL, $member));
				$this->t_body->set_var("MEMBER_JAVA", addslashes(formatName(NULL, $member)));
				$this->t_body->set_var("MEMBERID", $member['uid']);

				if($member['type'] == 1)
				{
					$this->t_body->set_var("WRITE", 
					"<a href=\"modUserGroup.php?
					write=".$member['uid']."&service=".$this->service."&sid=".$this->sid."\">
					<img src=\"/images/emptybox.gif\" width=\"13\" height=\"12\" border=\"0\"></a>");
				}
				else
				{
					$this->t_body->set_var("WRITE", 
					"<a href=\"modUserGroup.php?
					unwrite=".$member['uid']."&service=".$this->service."&sid=".$this->sid."\">
					<img src=\"/images/greentick.gif\" width=\"13\" height=\"12\" border=\"0\"></a>");
				}
				
				$this->t_body->parse("member_block", "member", true);
			}
		}
		else
			$this->t_body->set_var("member_block", "");
		
		//output users if any awaiting confirmation
		$this->displayAwaitingUsers();


		$this->t_body->pparse("finaloutput", "tplBODY");
	
		
	}

	function displayAwaitingUsers()
	{
		//get the list of users awaiting authorisation into group
		$query = "SELECT title, firstname, lastname, id FROM userAccess, user WHERE service = '$this->service' 
				AND sid = '$this->sid' AND type = '0' AND user.id = uid"; 
		$this->db->dbDoQuery($query);
	
		//output users
		$this->t_body->set_block("tplBODY", "authorisation", "authorisation_block");		
		$this->t_body->set_block("authorisation", "waiting", "waiting_block");

		if($this->db->dbCountRows() > 0 )
		{
			while($row = $this->db->dbGetNextArray() )
			{
				$this->t_body->set_var("WAITING_MEMBER", formatName(NULL, $row));
				$this->t_body->set_var("WAITINGID", $row['id']);
				
				$this->t_body->parse("waiting_block", "waiting", true);
			}
			
			$this->t_body->parse("authorisation_block", "authorisation");
		}
		else
		{
			//no users awaiting confirmation
			$this->t_body->set_var("waiting_block", "");
			$this->t_body->set_var("authorisation_block", "");
		}
	}


	function bulkAdd($string)
	{
		global $group;
		//get rid of newlines
		$string = preg_replace("(\r\n|\n|\r)", ",", $string);
		
		//split the list into an array
		$list = split('[,; ]', $string);
		
		foreach($list as $key)
		{
			trim($key);
			if(is_numeric($key))
			{
				//numeric assume its a synergy id
				$query = "SELECT id FROM user WHERE synergy_id = $key";
				$userid = $this->db->dbGetSingleRow($query);
				if($userid['id'])
				{
					$group->addMember($userid['id']);
				}
			}
			else
			{
				//word, assume its a username
				$query = "SELECT id FROM user WHERE username like '$key'";
				$userid = $this->db->dbGetSingleRow($query);
				if($userid['id'])
				{
					$group->addMember($userid['id']);
				}
			}
			
		}
	}		

}

if(isset($_REQUEST['service']) && $_REQUEST['service'] && isset($_REQUEST['sid']) && $_REQUEST['sid'])
{

	$group = new Group($_REQUEST['service'], $_REQUEST['sid']);

	//check accessor is owner if not a superuser
	if(!$_SESSION['su'])
	{
		$curOwner = $group->getOwner();
		if($curOwner['uid'] && $_SESSION['id'] != $curOwner['uid'] )
		{
			error(19);
		}
	}

	$page = new modUserGroup($_REQUEST['service'], $_REQUEST['sid']);

	//if update group type
	if(isset($_POST['type']) && $_POST['type'])
		$group->updateGroupType($_POST['type']);
	
	//if delete member
	if(isset($_GET['delete']) && $_GET['delete'])
		$group->deleteMember($_GET['delete']);

	//or change write permissions
	else if(isset($_GET['write']) && $_GET['write'])
		$group->addMemberWrite($_GET['write']);
	else if(isset($_GET['unwrite']) && $_GET['unwrite'])
		$group->removeMemberWrite($_GET['unwrite']);

  	//or add a member
	else if(isset($_REQUEST['uid']) && $_REQUEST['uid'])
		$group->addMember($_REQUEST['uid']);

  	//or add an awaiting member
	else if(isset($_GET['add']) && $_GET['add'])
		$group->addAwaitingUser($_GET['add']);

	//upgrade a member to a owner check
	else if(isset($_GET['owner']) && $_GET['owner'])
	{	
		//check if member is allowed to become a owner
		if($group->allowOwner($_GET['owner']))
		{
			//if we are accessing group for first time create group
			if(!$group->groupExists())
				$group->createGroup();
			
			$curOwner = $group->getOwner();
			if($curOwner != 0)
			{
				$group->deleteMember($curOwner['uid']);
				$group->addMember($curOwner['uid']);
			}
			
			//make new person an owner
			$group->deleteMember($_GET['owner']);
			$group->addOwner($_GET['owner']);
		}
		else
		{
			error(30);
		}
		//redirect user changing ownership to homepage if they have lost ownership
		$curowner = $group->getOwner();
		if($_SESSION['id'] != $curowner['uid'] && !$_SESSION['su'])
			localRedir("/homepage.php?category=".$_GET['sid']);
	}
	else if(isset($_GET['disband']) && $_GET['disband'])
	{
		$group->disbandGroup();
		localRedir("/home.php");
	}
	else if(isset($_POST['bulkstring']) && $_POST['bulkstring'])
	{	//add bulk chunk of users to group
		$page->bulkAdd($_POST['bulkstring']);
	}	
	else if(isset($_GET['bulk']) && $_GET['bulk'])
	{
		$page->displayBulk($_GET['bulk']);
		$displayed = 1;
	}	
	else     //display
	{
		$page->displayGroup();
		$displayed = 1;
	}
	if(!$displayed && $_GET['user'])
	{
		localRedir("/admin/modUser.php?id=".$_GET['user']);
	}
	else if(!$displayed)
	{
		localRedir("/group/modUserGroup.php?service=".$_REQUEST['service']."&sid=".$_REQUEST['sid']);
	}
}
else 
{
	error(2);
}
