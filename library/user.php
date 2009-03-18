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
						synergy_id, local_email, alt_email, userCampus.campus, IF(superusers.id, 1, 0) as su
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
					LEFT JOIN superusers ON user.id = superusers.id
				WHERE user.id = '$id'";
		return $this->db->getRow($query);
	
	}
	
	function getBySynergy($sid) {
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id,
						synergy_id, local_email, alt_email, userCampus.campus, year
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
				WHERE user.synergy_id = '$sid'";
		return $this->db->getRow($query);
	
	}
	
	function searchUser($keyword, $group = 0, $limit = false)
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
		
		if (is_numeric($limit))
			$query .= "LIMIT $limit";

		return $this->db->getAll($query);

	}

	function setPassword($userID, $oldPassword, $newPassword) {
		$oldPassword = mysql_real_escape_string($oldPassword);
		$newPassword = mysql_real_escape_string($newPassword);
		$query = "UPDATE `".$this->_table."` SET password = PASSWORD('$newPassword') WHERE `".$this->_index."` = $userID AND password = PASSWORD('$oldPassword')";
		$this->db->query($query);

		return $this->db->affectedRows();
	}

	function sendResetPasswordEmail($email) {
		if (false != ($user = $this->getByField('alt_email', $email))) {
			$hash = substr(md5(uniqid().time()),0, 25);
			$this->update(array('id' => $user['id'], 'hash' => $hash));
			
			require_once SITE_root . '/include/mailer.php';
			$mailHandler = new Mailer('reset-password.html', HEADING . ' Password Reset', $user['alt_email']);
			$mailHandler->setVar('NAME', $user['firstname']);
			$mailHandler->setVar('RESET_LINK', "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?id={$user['id']}&h=" . $hash);
			return $mailHandler->send();
		}
		return false;
		
	}
	
	function resetPassword($userID, $hash) {
		
		$password = substr(md5(uniqid().time()), 0, 8);
		$query = "UPDATE `" .$this->_table. "` SET password = PASSWORD(".$this->db->smartQuote($password)."), `hash` = NULL 
				  WHERE `".$this->_index."` = ".$this->db->smartQuote($userID)." AND `hash` = ".$this->db->smartQuote($hash)." AND `hash` IS NOT NULL";
		$this->db->query($query);
		
		if ($this->db->affectedRows()) {
			$user = $this->getByID($userID);
			require_once SITE_root . '/include/mailer.php';
			$mailHandler = new Mailer('new-password.html', HEADING . ' Login Details', $user['alt_email']);
			$mailHandler->setVar('NAME', $user['firstname']);
			$mailHandler->setVar('USERNAME', $user['username']);
			$mailHandler->setVar('PASSWORD', $password);
			$mailHandler->send();
			
			return true;
		} else
			return false;	
	}

	
	
}

?>