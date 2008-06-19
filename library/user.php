<?php

require_once '../include/model.php';

class User extends Model {

	function User () {
	
		$this->_table = "user";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getAllInCategory($categoryID) {
		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email 
				  FROM user, userAccess
				  WHERE id = uid AND service = '" . HOMEPAGESERVICE . "' AND sid = '".$categoryID."' AND type > 0 
				  ORDER BY lastname, firstname";		
		return $this->db->getAll($query);
	}
	
	function getManagers($categoryID) {
		$query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email FROM user, userAccess
		WHERE id = uid AND service = '" . HOMEPAGESERVICE . "' AND sid = '".$categoryID."' and type in(3,2) ORDER BY lastname, firstname";
		return $this->db->getAll($query);
	}
	
	function getByID($id) {
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id,
						synergy_id, local_email, alt_email, userCampus.campus
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
				WHERE user.id = '$id'";
		return $this->db->getRow($query);
	
	}
	
}

?>