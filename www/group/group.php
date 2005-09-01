<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//object for group manipulation


class Group
{
	function Group($service, $sid)
	{

		$this->db = new dbManager;
	
		$this->service = $service;
		$this->sid = $sid;
	}

	function createGroup($type = 1)
	{
		$query = "INSERT INTO userGroups (cid, type) VALUES ('$this->sid', '$type')";
		$this->db->dbDoQuery($query);
	}

	function groupExists()
	{
		$query = "SELECT cid FROM userGroups WHERE cid = '$this->sid'";
		$result = $this->db->dbGetSingleRow($query);
		if($result['cid'])
			return true;
		else
			return false;
	}

	function deleteMember($uid, $type = 0)
	{
		//deletes a uid also can optionally delete a user based on its permission
		$query = "DELETE FROM userAccess WHERE service = '$this->service' AND sid = '$this->sid' AND uid = '$uid'";
		if($type)
				$query .= " AND type = '$type'";
		$this->db->dbDoQuery($query);
	
	}
	
	function getOwner()
	{
	
		//get the owner(s) of the group
		$query = "SELECT firstname, lastname, uid FROM userAccess, user WHERE uid = id AND service = '$this->service' 
				AND sid = '$this->sid' AND type = '3'";
		return $this->db->dbGetSingleRow($query); //only one owner at the moment
	}
	
	function memberExist($uid)
	{
		//function to check if a user already exists, returns true if exists
		$query = "SELECT uid FROM userAccess WHERE service = '$this->service' AND sid = '$this->sid' AND uid = '$uid'";
		$this->db->dbDoQuery($query);
	
		if($this->db->dbCountRows() > 0)
				return true;
		else
				return false;
	}
	
	function addOwner($uid)
	{
		$query = "INSERT INTO userAccess (service, sid, uid, type) 
		VALUES ('$this->service', '$this->sid', '$uid', '3')";
		$this->db->dbDoQuery($query);
	}
	
	function addMember($uid, $write = 1)
	{
		if(!$this->memberExist($uid))
		{
				$query = "INSERT INTO userAccess (service, sid, uid, type) 
		VALUES ('$this->service', '$this->sid', '$uid', '$write')";
				$this->db->dbDoQuery($query);
		}
	}
	
	function allowOwner($id)
	{
		//checks if the user can become the owner of group
		if(GROUP_staff_only_owner)
		{
			//only staff may be owners
			$query = "SELECT group_id FROM user WHERE id = '$id'";
			$group = $this->db->dbGetSingleRow($query);
			return isStaff($group['group_id']);
			
		}
		else
		{
			return true;
		}
	}
	
	function addMemberWrite($uid)
	{
		//first delete them no matter what
		$this->deleteMember($uid, 1);
	
		//now readd them with write permissions
		$this->addMember($uid, 2);
	}
	
	function removeMemberWrite($uid)
	{
		//first delete them no matter what
		$this->deleteMember($uid, 2);
	
		//now readd them as a normal user
		$this->addMember($uid);
	
	}
	
	function disbandGroup()
	{
		//delete an entire group including owner
		$query = "DELETE FROM userAccess WHERE service = '$this->service' AND sid = '$this->sid'";
		$this->db->dbDoQuery($query);
		
		$query = "DELETE FROM userGroups WHERE cid = '$this->sid'";
		$this->db->dbDoQuery($query);
	
	}
	
	function getGroupType()
	{
		if($this->service != 1)
			error(0); //this only works on folder groups!!??
		
		$query = "SELECT type FROM userGroups WHERE cid = '$this->sid'";
		$line = $this->db->dbGetSingleRow($query);
	
		return $line['type'];
	}	
	
	function addAwaitingUser($uid)
	{
		$query = "UPDATE userAccess SET type = '1' WHERE uid = '$uid' AND sid = '$this->sid' 
				AND service = '$this->service' AND type = '0'";
		$this->db->dbDoQuery($query);
	}
	
	function updateGroupType($type)
	{
		//updates the type of group
		// 1 = restricted
		// 2 = modertated
		// 3 = free
	
		if($this->service != 1)
				error(); //this only works on folder groups!!??
	
		$query = "UPDATE userGroups SET type = '$type' WHERE cid = '$this->sid'";
		$this->db->dbDoQuery($query);
	}
}
?>
