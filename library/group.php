<?php

define('HOMEPAGE_SERVICE', 1);
define('CALENDAR_SERVICE', 2);
define('NEWS_SERVICE', 	3);

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Group extends Model {
	function Group ($service = HOMEPAGE_SERVICE) {
		$this->_table = 'userGroups';
		$this->_index = 'cid';
		$this->_service = $service;
		
		parent::Model();
	}

	function groupExists($categoryID) {
		$query = "SELECT cid FROM userGroups WHERE cid = $categoryID";
		$result = $this->db->getRow($query);
		if($result['cid'])
			return true;
		else
			return false;
	}

	function getOwner ($categoryID) {
	
		//get the owner(s) of the group
		$query = "SELECT title, firstname, lastname, uid FROM userAccess, user WHERE uid = id AND service = ".$this->_service." 
				AND sid = $categoryID AND type = '3'";
		return $this->db->getRow($query); //only one owner at the moment
	}
	
	function getMemberList($categoryID) {
		$query = "SELECT title, firstname, lastname, uid, type, group_id 
				  FROM userAccess, user 
				  WHERE uid = id AND service = ".$this->_service." AND sid = $categoryID AND type < '3' AND type > '0'";
		return $this->db->getAll($query);
	
	}
	
	function getWaitingList($categoryID) {
		$query = "SELECT title, firstname, lastname, uid
				  FROM userAccess, user 
				  WHERE service = ".$this->_service." AND sid = $categoryID AND type = '0' AND user.id = uid"; 
		return $this->db->getAll($query);
	
	}

	function isMember($userID, $categoryID) {
		$query = "SELECT uid FROM userAccess WHERE service = ".$this->_service." AND sid = $categoryID AND uid = $userID";
		$this->db->query($query);
	
		if($this->db->countRows() > 0)
			return true;
		else
			return false;
	}

	function addUser($userID, $relationID, $type = 1) {
		if(!$this->isMember($userID, $relationID)) {
			$data = array('uid'		=> $userID,
						  'sid'		=> $relationID,
						  'service'	=> $this->_service,
						  'type'	=> $type,
						  );
			return $this->db->insert('userAccess', $data);
		} else
			return false;
	}

	function deleteUser($userID, $relationID, $type = 0) {
		//deletes a uid also can optionally delete a user based on its permission
		$query = "DELETE FROM userAccess WHERE service = ".$this->_service." AND sid = $relationID AND uid = $userID";
		if($type)
			$query .= " AND type = '$type'";
		
		return $this->db->query($query);	
	}
	
	function acceptMember($userID, $categoryID) {
		$data = array('uid'		=> $userID,
					  'sid'		=> $categoryID,
					  'service'	=> $this->_service,
					  'type'	=> 1,
					  );
		return $this->db->update('userAccess', array('uid','sid','service'), $data);
	}	

	function allowOwner($userID) {
		//checks if the user can become the owner of group
		if(GROUP_staff_only_owner) {
			//only staff may be owners
			$query = "SELECT group_id FROM user WHERE id = '$userID'";
			$group = $this->db->getRow($query);
			return isStaff($group['group_id']);
		} else
			return true;
	}

	function createGroup($categoryID, $type = 1) {
		$data = array('cid'		=> $categoryID,
					  'type'	=> $type
					  );
		return $this->db->insert('userGroups', $data);
	}

	function disbandGroup($categoryID) {
		//delete an entire group including owner
		$query = "DELETE FROM userAccess WHERE service = ".$this->_service." AND sid = $categoryID";
		$this->db->query($query);
		
		$query = "DELETE FROM userGroups WHERE cid = $categoryID";
		$this->db->query($query);

	}
}
?>