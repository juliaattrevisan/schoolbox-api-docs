<?php

require_once dirname(__FILE__) . '/../include/model.php';

define('HOMEPAGE_SERVICE', 	1);
define('CALENDAR_SERVICE', 	2);
define('NEWS_SERVICE', 		3);
define('TUTORIAL_SERVICE',	4);
define('CMS_SERVICE',		5);

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
			return $this->isStaff($userID);
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
				  WHERE uid = id AND service = ".$this->_service." AND sid = $relationID AND type < ".FULL_ACCESS." AND type > ".DENY_ACCESS
				  ." ORDER BY lastname, firstname";
		return $this->db->getAll($query);
	}
	
	function getWaitingList($relationID) {
		$query = "SELECT title, firstname, lastname, uid
				  FROM userAccess, user 
				  WHERE service = ".$this->_service." AND sid = $relationID AND type = ".DENY_ACCESS." AND user.id = uid"; 
		return $this->db->getAll($query);
	
	}

	function isUser($relationID, $userID) {
		$query = "SELECT uid FROM userAccess WHERE service = '".$this->_service."' AND sid = '$relationID' AND uid = '$userID'";
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
		if ($userID == 0) {
                    if($_SESSION['id'])
			$userID = $_SESSION['id'];
                
                    else 
                        return false;
                }
		/* Cached results */
		if(isset($this->cache[$this->_service][$relationID][$userID])) {
			return $this->cache[$this->_service][$relationID][$userID];
		}
		
		require_once dirname(__FILE__) . '/user.php';
		$userHandler = new User;
		$user = $userHandler->getByID($userID);
		
		// if superuser just return highest permission type
		if($user['su'])
			return FULL_ACCESS;
        
		// check individual access
		
		$query = "SELECT type FROM userAccess WHERE uid = ".$userID." AND ( ( service = ".$this->_service." ";
		
		if (in_array($this->_service, array(HOMEPAGE_SERVICE, CALENDAR_SERVICE)))
			$query .= "AND sid = ".$relationID." ";
		
		$query .= " ) ";
		
		if ($this->_service == HOMEPAGE_SERVICE)
			$query .= "OR service = ".CMS_SERVICE." ";
			
		$query .= ") ORDER BY `type` DESC LIMIT 1 ";
		
		$permission = $this->db->getOne($query);    
		$permission = (int)$permission;       

		// check group access (HOMEPAGE_SERVICE only)
		if($permission < WRITE_ACCESS && $this->_service == HOMEPAGE_SERVICE) {                       
			$query = "SELECT type FROM perms WHERE cid='".$relationID."' AND gid='".$user['group_id']."' ORDER BY type DESC";
			$groupPermission = $this->db->getOne($query);
			if($groupPermission) 
				$permission = $groupPermission;
		}
		
		// allow staff write access to NEWS_SERVICE by default except at shore...
		if(($this->isStaff($userID) && SITE_location != 'shore') && $this->_service == NEWS_SERVICE && $permission < WRITE_ACCESS)
			$permission = WRITE_ACCESS;
		
		// check synergy tutor list
		if ($permission < WRITE_ACCESS && $this->_service == TUTORIAL_SERVICE && SYNERGY_enable) {
			$tutorialPermission = $this->getTutorialPermission($userID);
			if ($tutorialPermission)
				$permission = $tutorialPermission;
		}
		
		/* Set cache */
		$this->cache[$this->_service][$relationID][$userID] = $permission;
		
		return $permission;
	}
	
	function getTutorialPermission($userID) {
		
		require_once dirname(__FILE__) . '/user.php';
		require_once dirname(__FILE__) . '/mssql.php';
		$userHandler = new User;
		$mssql = new MSSQL;
		
		$user = $userHandler->getByID($userID);
		
		$query = "SELECT ID
					  FROM SynergyOne.dbo.vCommunityAddressesConstit vCommunityAddressesConstit
					  WHERE ((vCommunityAddressesConstit.ConstitCode='TUTRES2009') OR (vCommunityAddressesConstit.ConstitCode='TUTNONRES2009')) 
					  AND ID = " . $user['synergy_id'];
		
		$tutor = $mssql->getRow($query);
		
		if (isset($tutor['ID']))
			return WRITE_ACCESS;
		else
			return DENY_ACCESS;
	}

	function isStudent($userID = 0) {
		// checks if user is a student
		
		// default to current user
		if($userID > 0 ) {
			require_once dirname(__FILE__) .'/user.php';
			$userHandler = new User;
			$user = $userHandler->getByID($userID);
			$groupID = $user['group_id'];
		}
        else if (is_numeric($_SESSION['gid']) ) {
			$groupID = $_SESSION['gid'];
		} else
			return false;

		if(in_array($groupID, unserialize(STUDENTGROUPIDS)))
			return true;
		else
			return false;
	}

   /*
    * isSuperuser
    * Find out if the current id belongs to a superuser.
    */
   function isSuperuser($userID = 0) {

		// default to current user
		if ($userID > 0) {
         require_once dirname(__FILE__) .'/user.php';
         $userHandler = new User;
         $user = $userHandler->getByID($userID);

         if($user['su']) {
         	return true;
         }
         else {
            return false;
         }
     }
     else
        return $_SESSION['su']==1?true:false;
   }
	
	function isStaff($userID = 0) {
		// checks if user is staff
		
		// default to current user
		if ($userID > 0) {
			require_once dirname(__FILE__)  . '/user.php';
			$userHandler = new User;
			$user = $userHandler->getByID($userID);
			$groupID = $user['group_id'];
		} elseif (is_numeric($_SESSION['gid']) ) { 
			$groupID = $_SESSION['gid'];
		} else
			return false;
		
		if(in_array($groupID, unserialize(STAFFGROUPIDS)))
			return true;
		else
			return false;
	}

	function isParent($userID = 0) {
		// checks if user is a parent
		
		// default to current user
		if ($userID > 0) {
			require_once dirname(__FILE__) .'/user.php';
			$userHandler = new User;
			$user = $userHandler->getByID($userID);
			$groupID = $user['group_id'];
		} elseif (is_numeric($_SESSION['gid']) ) { 
			$groupID = $_SESSION['gid'];
		} else
			return false;

		if(in_array($groupID, unserialize(PARENTGROUPIDS)))
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
	
	function involvedInGroup($uid, $cid) {
		//returns true on user being involved in group	
		$query = "SELECT category.id FROM userAccess, category 
			WHERE userAccess.service = '".$this->_service."' AND userAccess.uid = '$uid'
			AND userAccess.sid = category.id AND category.id = '$cid'";
		return $this->db->getOne($query);
	}
	
	function involvedInSubject($code) {
		//returns true on user being involved in subject (case-insensitive)
		
		if(is_array($_SESSION['subjects'])) {
			//$code = strtoupper($code);
			foreach($_SESSION['subjects'] as $subject) {
				if(is_numeric($code) && $code == $subject['id'])
					return true;
				if(strtoupper($code) == strtoupper($subject['code']))
					return true;
			}
		}
		return false;
	}
	
	function involvedCategoryList($id, $subjects = '', $accessType=READ_ACCESS) {
		//returns a list of categories that the user is involved in
		//optional accessType specifies read=1 , write=2, ownership=3 access levels
	
		if(is_array($subjects)) {
			foreach($subjects as $subject) {
				//convert code to id and get name
				$query = "SELECT name, id, cc.code FROM category JOIN categoryCode cc ON id = categoryID WHERE cc.code = '".$subject['code']."'";
				if(is_array($catTmp = $this->db->getRow($query)))
					$catList[] = $catTmp;
			}
		}

		//get list of groups involved in
		$query = "SELECT category.name, category.id FROM userAccess, category 
		WHERE userAccess.uid = '$id' AND userAccess.sid = category.id AND userAccess.service = '".$this->_service."'
		AND userAccess.type >= '$accessType'
        ORDER BY category.name";

		$this->db->query($query);
		while($result = $this->db->getNextArray()) {
			$catList[] = $result;
		}

		return $catList;
	}
	
}
?>