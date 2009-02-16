<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

define('HOMEPAGE_SERVICE', 	1);
define('CALENDAR_SERVICE', 	2);
define('NEWS_SERVICE', 		3);
define('TUTORIAL_SERVICE',	4);

define('DENY_ACCESS', 		0);
define('READ_ACCESS', 		1);
define('WRITE_ACCESS', 		2);
define('FULL_ACCESS', 		3);

define('GROUP_TYPE_RESTRICTED',	1);
define('GROUP_TYPE_MODERATED', 	2);
define('GROUP_TYPE_FREE', 		3);

class Group extends Model {

	function Group ($service = HOMEPAGE_SERVICE) {
		$this->_table 	= 'userGroups';
		$this->_index 	= 'cid';
		$this->_service = $service;
		
		parent::Model();
	}
	
	function setService($service = HOMEPAGE_SERVICE) {
		$this->_service = $service;
	}

	function getAllByType ($type) {
		$query = "SELECT name, id, userAccess.type accessType
				  FROM (category, userGroups )
				  LEFT JOIN userAccess ON cid = sid AND service = ".HOMEPAGE_SERVICE." AND uid = '".$_SESSION['id']."' 
				  WHERE cid = id AND userGroups.type = '$type'";
		return $this->db->getAll($query);	
	}

	function groupExists($relationID) {
		$query = "SELECT cid FROM userGroups WHERE cid = $relationID";
		$result = $this->db->getRow($query);
		if($result['cid'])
			return true;
		else
			return false;
	}

	function createGroup($relationID, $type = 1) {
		$data = array('cid'		=> $relationID,
					  'type'	=> $type
					  );
		return $this->db->insert('userGroups', $data);
	}

	function setType($relationID, $type = 1) {
		// change group type
		$data = array('type'	=> $type,
					  'cid'		=> $relationID,
					  );
		$this->update($data);
	}

	function getOwner ($relationID) {
	
		//get the owner(s) of the group
		$query = "SELECT title, firstname, lastname, uid 
				  FROM userAccess, user 
				  WHERE uid = id AND service = ".$this->_service." AND sid = $relationID AND type = '". FULL_ACCESS;
		return $this->db->getRow($query); //only one owner at the moment
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

	function disbandGroup($relationID) {
		//delete an entire group including owner

		$query = "DELETE FROM userAccess WHERE service = ".$this->_service." AND sid = $relationID";
		$this->db->query($query);
		
		$this->db->delete('userGroups', 'cid', $relationID);
		
		/* Delete groups calendar */
		$query = "DELETE FROM event WHERE access = 2 AND sid = '$relationID'";
		$this->db->query($query);
	}

	function getMemberList($relationID) {
		$query = "SELECT title, firstname, lastname, uid, type, group_id 
				  FROM userAccess, user 
				  WHERE uid = id AND service = ".$this->_service." AND sid = $relationID AND type < ".FULL_ACCESS." AND type > ".DENY_ACCESS;
		return $this->db->getAll($query);
	}
	
	function getWaitingList($relationID) {
		$query = "SELECT title, firstname, lastname, uid
				  FROM userAccess, user 
				  WHERE service = ".$this->_service." AND sid = $relationID AND type = ".DENY_ACCESS." AND user.id = uid"; 
		return $this->db->getAll($query);
	
	}

	function isUser($relationID, $userID) {
		$query = "SELECT uid FROM userAccess WHERE service = ".$this->_service." AND sid = $relationID AND uid = $userID";
		$this->db->query($query);
	
		if($this->db->countRows() > 0)
			return true;
		else
			return false;
	}

	function addUser($relationID, $userID, $type = READ_ACCESS) {
		if(!$this->isUser($relationID, $userID)) {
			$data = array('uid'		=> $userID,
						  'sid'		=> $relationID,
						  'service'	=> $this->_service,
						  'type'	=> $type,
						  );
			return $this->db->insert('userAccess', $data);
		} else
			return false;
	}

	function deleteUser($relationID, $userID) {
		//deletes a uid also can optionally delete a user based on its permission
		$query = "DELETE FROM userAccess WHERE service = ".$this->_service." AND sid = $relationID AND uid = $userID";
		return $this->db->query($query);	
	}

	function setUserPermission($relationID, $userID, $type = READ_ACCESS) {
		$this->deleteUser($relationID, $userID);
		return $this->addUser($relationID, $userID, $type);
	}

	function getPermission($relationID, $userID = 0) {
		
		// checks permissions for specified user
		// returns NO_ACCESS, READ_ACCESS, WRITE_ACCESS or FULL_ACCESS
		
		// default to current user
		if ($userID == 0)
			$userID = $_SESSION['id'];
		
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($userID);
		
		// if superuser just return highest permission type
		if($user['su'])
			return FULL_ACCESS;
	
		// check individual access
		$query = "SELECT type FROM userAccess WHERE service = ".$this->_service." AND sid = ".$relationID." AND uid = ".$userID."";
		$permission = $this->db->getOne($query);    
		$permission = (int)$permission;       

		
		// check group access (HOMEPAGE_SERVICE only)
		if($permission < WRITE_ACCESS && $this->_service == HOMEPAGE_SERVICE) {                       
			$query = "SELECT type FROM perms WHERE cid='".$relationID."' AND gid='".$user['group_id']."' ORDER BY type DESC";
			$groupPermission = $this->db->getOne($query);
			if($groupPermission) 
				$permission = $groupPermission;
		}
		
		// allow staff write access to NEWS_SERVICE by default
		if(isStaff($user['group_id']) && $this->_service == NEWS_SERVICE && $permission < WRITE_ACCESS)
			$permission = WRITE_ACCESS;
		
		
		return $permission;
	}

	function isStudent($userID = 0) {
		// checks if user is a student
		
		// default to current user
		if ($userID == 0)
			$userID = $_SESSION['id'];
		 
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($userID);
		
		if(in_array($user['group_id'], unserialize(STUDENTGROUPIDS)))
			return true;
		else
			return false;
	}
	
	function isStaff($userID = 0) {
		// checks if user is staff
		
		// default to current user
		if ($userID == 0)
			$userID = $_SESSION['id'];
		 
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($userID);
		
		if(in_array($user['group_id'], unserialize(STAFFGROUPIDS)))
			return true;
		else
			return false;
	}

	function isParent($userID = 0) {
		// checks if user is a parent
		
		// default to current user
		if ($userID == 0)
			$userID = $_SESSION['id'];
		 
		require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($userID);

		if(in_array($user['group_id'], unserialize(PARENTGROUPIDS)))
			return true;
		else
			return false;
		
	}
	
	function isParentOfChild($childID) {
		if(is_array($_SESSION['children'])) {
			foreach($_SESSION['children'] as $child) {
				if($child['id'] == $childID)
					return true;
			}
		}
		return false;
	}
	
}
?>