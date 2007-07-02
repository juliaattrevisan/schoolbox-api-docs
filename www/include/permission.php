<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	This file provides permission functions for checking 	*
*	what level of access the user has to services.			*
*   $Id: permission.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $						    
*                                                           *
************************************************************/

function perm($sid, $service)
{
	//Function to check permissions on specified user in specified service
	//Returns 0 on no permission
	//	  1 on read
	//	  2 on write
	//	  3 on owner

	//if superuser just return highest permission type
	if($_SESSION['su'] == 1)
		return 3;

		$db = new dbManager;

	//individual access
	$userQuery = "SELECT type FROM userAccess 
	WHERE service = ".$service." AND sid = ".$sid." AND uid = ".$_SESSION['id']."";
     
	$result = $db->dbGetSingleRow($userQuery);            
	$perm = $result['type'];
	if($perm == "")
		$perm = 0;
	
	//if user has write access or is owner or service doesnt include category check group permission
	if($perm < 2 && $service == 1)
	{                       
		$permQuery = "SELECT type FROM perms 
		WHERE cid='".$sid."' AND gid='".$_SESSION['gid']."' ORDER BY type DESC";
		$result = $db->dbGetSingleRow($permQuery);
		if($result['type']) 
		{
			$perm = $result['type'];
		}
		else
		{
			//if they dont have user permission deny access
			if($perm != 1)
				$perm = 0;
		}
	}
	return $perm;
}

function isMember($id, $uid)
{
	// Checks if user is member of the supplied group id (for category groups only, use perm for others) 
	$db = new dbManager;
	$service = HOMEPAGESERVICE;
	
	//individual access
	$userQuery = "SELECT type FROM userAccess 
	WHERE service = $service AND sid = $id AND uid = $uid";
     
	$result = $db->dbGetSingleRow($userQuery);   

	if($result['type'])
		return true;
	else
		return false; 
}


function isStudent($gid)
{
	//Function check wether $id belongs to student
	//Returns 1 if student else 0

	if(in_array($gid, unserialize(STUDENTGROUPIDS)))
		return true;
	else
		return false;
}

function isStaff($gid)
{
	//Function check wether $id belongs to staff
	//Returns 1 if student else 0

	if(in_array($gid, unserialize(STAFFGROUPIDS)))
		return true;
	else
		return false;
}

function isParent($gid)
{
	//Function check if parent
	if(in_array($gid, unserialize(PARENTGROUPIDS)))
		return true;
	else
		return false;
	
}

function isParentOfChild($id) {
	if(is_array($_SESSION['children'])) {
		foreach($_SESSION['children'] as $child) {
			if($child['id'] == $id)
				return true;
		}
	}
	return false;
}

?>
