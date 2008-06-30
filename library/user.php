<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

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
	
	function getBySynergy($sid) {
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id,
						synergy_id, local_email, alt_email, userCampus.campus
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
				WHERE user.synergy_id = '$sid'";
		return $this->db->getRow($query);
	
	}
	
	function searchUser($keyword, $group = 0)
	{
		//search for a user based on at least one of the above fields
		//assumes at least one contains something
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, year, local_email, alt_email 
					FROM user LEFT JOIN groups ON user.group_id = groups.id WHERE ";

		if($keyword != "")
		{
			if( is_numeric( $keyword)) 
				$query .= "( synergy_id = $keyword OR year = $keyword ) ";
			else 
			{
				if(preg_match('/([[:alpha:]\']+) ([[:alpha:]\']+)/', $keyword, $name)) 
					$query .= "( firstname like '%{$name[1]}%' and lastname like '%{$name[2]}%' ) ";
				else 
					$query .= "( firstname like '%$keyword%' or lastname like '%$keyword%' or username like '%$keyword%' ) ";
			}
				
			if($group)
				$query .= "AND group_id = $group ";
		}
		else if($group)
			$query .= "group_id = $group ";
		else
			error(2);

		$query .= "ORDER BY group_id, year, lastname, firstname"; 

		return $this->db->getAll($query);

	}
	
	
}

?>