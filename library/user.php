<?php

require_once dirname(__FILE__) . '/../include/model.php';

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
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, eportfolio_enabled,
						synergy_id, local_email, alt_email, email_pass, userCampus.campus, IF(superusers.id, 1, 0) as su
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
					LEFT JOIN superusers ON user.id = superusers.id
				WHERE user.id = '$id'";
		return $this->db->getRow($query);
	
	}

        function getByUsername($username) {

		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, eportfolio_enabled,
						synergy_id, local_email, alt_email, email_pass, userCampus.campus, IF(superusers.id, 1, 0) as su
				FROM user
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
					LEFT JOIN superusers ON user.id = superusers.id
				WHERE user.username = ".$this->db->smartQuote($username);
		return $this->db->getRow($query);

	}

        function getSuperusers(){
            $query = "SELECT user.id uid, title, firstname, lastname, username, local_email, alt_email
                FROM superusers JOIN user ON superusers.id = user.id";
            return $this->db->getAll($query);
        }

        function getUserCampus($id) {

            $campusquery = "SELECT campus FROM userCampus WHERE uid = '$id' ORDER BY defaultCampus DESC";

            $this->db->query($campusquery);
            if($this->db->countRows() > 0) {
                while($campus = $this->db->getNextArray() ) {
                    if($campus['campus'])
                        $campusList[] = $campus['campus'];
                }
            }
            else {
                //Place user in campus 1 by default in not assigned
                $campusList[] = 1;
            }

            return $campusList;
        }

	
	function getBySynergy($sid) {
	
		$query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id,
						synergy_id, local_email, alt_email, userCampus.campus, year, eportfolio_enabled
				FROM user 
					LEFT JOIN groups ON user.group_id = groups.id
					LEFT JOIN userCampus ON user.id = userCampus.uid
				WHERE user.synergy_id = '$sid'";
		return $this->db->getRow($query);
	
	}
	
        function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'lastname', $sort_dir = 'desc') {
            $keyword = mysql_real_escape_string($keyword);

            // retreive results
            $query = "SELECT user.*, groups.name AS group_name, luCampus.name AS campus_name
                        FROM `user`
                        LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                        LEFT JOIN `luCampus` ON `user`.campus = `luCampus`.id
                        WHERE CONCAT_WS(' ',firstname,lastname) like '%$keyword%'
                        OR username like '%$keyword%' OR alt_email like '%$keyword%'";

            $query .= " ORDER BY `$sort_by` $sort_dir
                        LIMIT $offset, $limit;";

            // retreive count
            $count_query = "SELECT COUNT(*)
                                FROM `user`
                                LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                                LEFT JOIN `luCampus` ON `user`.campus = `luCampus`.id
                                WHERE CONCAT_WS(' ',firstname,lastname) like '%$keyword%'
                                OR username like '%$keyword%' OR alt_email like '%$keyword%'";

            $count = $this->db->getOne($count_query);

            return $this->db->getAll($query);
        }

	function ajaxSearch($keyword, $group = 0, $limit = false)
	{
		//search for a user based on at least one of the above fields
		//assumes at least one contains something
        $keyword = mysql_real_escape_string($keyword);
        
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

			if(is_array($group) )
				$query .= "AND group_id IN (".implode(',',$group).") ";
			else if(is_numeric($group) && $group > 0)
				$query .= "AND group_id = '$group' ";
		}
		else if($group)
			$query .= "group_id = '$group' ";
		else
			error(2);

		$query .= "ORDER BY group_id, year, lastname, firstname";

		if (is_numeric($limit))
			$query .= " LIMIT $limit";

		return $this->db->getAll($query);

	}

	function setPassword($userID, $oldPassword, $newPassword) {
		$oldPassword = mysql_real_escape_string($oldPassword);
		$newPassword = mysql_real_escape_string($newPassword);
		$query = "UPDATE `".$this->_table."` SET password = PASSWORD('$newPassword'), email_pass = '$newPassword' WHERE `".$this->_index."` = $userID AND password = PASSWORD('$oldPassword')";
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
			$mailHandler->setVar('RESET_LINK', "http://" . $_SERVER['HTTP_HOST'] . "/reset.php?id={$user['id']}&h=" . $hash);
			return $mailHandler->send();
		}
		return false;
		
	}
	
	
	
	/*function sendPassword($email) {
				if (empty($email))
					return 0;
					
					/* Only parents can make this work. Look up their username because
					 * alt_email is an optional field, and username is their email address
					 */
					/*$groupHandler = new Group();
					
					$user = $this->getByField('alt_email', $email);

					if (isset($user['id'])) {
							if ($groupHandler->isParent($user['id'])) {
									require_once SITE_root . '/include/mailer.php';
									$mailHandler = new Mailer('new-password.html', 'Password reminder for Shore Parent Services', $user['alt_email']);
									$mailHandler->setVar('PASSWORD', $user['email_pass']);
									$mailHandler->send();
									
									return 1;
							}
							else {
								return 2;
							}
					}
					else {
						return 0;
					}
    }*/
		
		
		
		
    function sendPassword($email) {
		if (empty($email))
			return false;
			
		$user = $this->getByField('alt_email', $email);
		if (isset($user['id'])) {
		
			require_once SITE_root . '/include/mailer.php';
			$mailHandler = new Mailer('new-password.html', HEADING . ' Password Reminder', $user['alt_email']);
			$mailHandler->setVar('NAME', $user['firstname']);
			$mailHandler->setVar('USERNAME', $user['username']);
			$mailHandler->setVar('PASSWORD', $user['email_pass']);
			$mailHandler->send();
			
			return true;
		} else
			return false;	
	}
	

    /**
     * Gets the profile information for the user.
     * 
     * @param <type> $userID
     * @return <type> 
     */
    function getProfile($userID) {
        $query = "SELECT `year`, luCampus.name AS campus, house.house
                  FROM `user`
                  LEFT JOIN house ON user.house = house.id
                  LEFT JOIN luCampus ON user.campus = luCampus.id
                  WHERE user.id = '$userID'";

        return $this->db->getRow($query);
	}


    function getCampusList() {
        $query = "SELECT * FROM luCampus";
        return $this->db->getAll($query);
    }

    function getGroupList() {
        $query = "SELECT * FROM groups";
        return $this->db->getAll($query);
    }

    function getTitleList() {
        $query = "SELECT * FROM luTitle";
        return $this->db->getAll($query);
    }

    function getUserActivity($uid, $limit = 10) {
            $query = "SELECT object, action, time, c.name as folder, f.description as file, l.description as link
                    FROM cmsLog
                    LEFT JOIN category c ON c.id = objectId
                    LEFT JOIN file f ON f.id = objectId
                    LEFT JOIN link l ON l.id = objectId
                    WHERE user = $uid
                    ORDER BY time DESC
                    LIMIT 0,$limit";
            return $this->db->getAll($query);

    }

    function getUserLogins($username, $limit = 10) {
        $query = "SELECT date, ip, login FROM login_log WHERE username like ".$this->db->smartQuote($username)." ORDER BY date DESC LIMIT 0,$limit";
        return $this->db->getAll($query);

    }

    function delete($id, $trans)
	{

		//first transfer ownership of date to another user
		$this->transferOwnership($id, $trans);

		//remove all submission boxes owned by user
		$this->cleanSubmissionBoxes($id);

		//remove all work returned to user
		$this->cleanReturnedWork($id);

		//remove all remaining traces of user
		$this->cleanUserFromSystem($id);

		//remove user from database
        parent::delete($id);

	}


	function transferOwnership($curr, $new)
	{

		//update all files
		$query = "UPDATE file SET owner = '$new' WHERE owner = '$curr'";
		$this->db->query($query);

		//update all links
		$query = "UPDATE link SET owner = '$new' WHERE owner = '$curr'";
		$this->db->query($query);

		//update all news
		$query = "UPDATE news_items SET item_owner = '$new' WHERE item_owner = '$curr'";
		$this->db->query($query);

		$query = "UPDATE subject_news SET owner = '$new' WHERE owner = '$curr'";
		$this->db->query($query);

		//update all forums
		$query = "UPDATE forum SET owner = '$new' WHERE owner = '$curr'";
		$this->db->query($query);

	}

	function cleanSubmissionBoxes($id)
	{
		//first select all users submission boxes
		$query = "SELECT id FROM submission_box WHERE owner = '$id'";
		$result = $this->db->query($query);

		//foreach submission box delete all student submissions
		while($box = $this->db->getNextArray($result))
		{
			//select all student submissions
			$query = "SELECT id FROM student_submission WHERE box_id = '".$box['id']."'";
			$this->db->query($query);
			//for each student submission delete from hdd
			while($studsub = $this->db->getNextArray() )
			{
				if(!@unlink(Cfg::get('submission_dir')."/".$studsub['id'].".dat"))
					print "Failed to delete: ".Cfg::get('submission_dir')."/".$studsub['id'].".dat";
			}

			//delete all student submissions from db
			$query = "DELETE FROM student_submission WHERE box_id = '".$box['id']."'";
			$this->db->query($query);

		}

		//delete all submission boxes from db
		$query = "DELETE FROM submission_box WHERE owner = '$id'";
		$this->db->query($query);
	}

	function cleanReturnedWork($id)
	{
		$query = "SELECT id FROM submission_return WHERE owner = '$id'";
		$this->db->query($query);

		while($ret = $this->db->getNextArray() )
		{
			if(!@unlink(Cfg::get('returns_dir')."/".$ret['id'].".dat"))
				print "Failed to delete: ".Cfg::get('returns_dir')."/".$ret['id'].".dat";
		}

		$query = "DELETE FROM submission_return WHERE owner = '$id'";
		$this->db->query($query);
	}

	function cleanUserFromSystem($id)
	{
		//remove users bookmarks
		$query = "DELETE FROM bookmarks WHERE owner = '$id'";
		$this->db->query($query);

		//superusers
		$query = "DELETE FROM superusers WHERE id = '$id'";
		$this->db->query($query);

		//user access
		$query = "DELETE FROM userAccess WHERE uid = '$id'";
		$this->db->query($query);

		//delete personal calendar events
		$query = "DELETE FROM event WHERE access = '1' AND owner = '$id'";
		$this->db->query($query);

		//delete log entries
		$query = "DELETE FROM homepage_log WHERE user = '$id'";
		$this->db->query($query);
		$query = "DELETE FROM submission_log WHERE user = '$id'";
		$this->db->query($query);

	}

	function update($user)
	{

        $campusArray = $user['campus'];
        $defaultCampus = $user['defaultCampus'];
        $su = $user['su'];

        unset($user['campus']);
        unset($user['defaultCampus']);
        unset($user['su']);

        $user['password'] = 'PASSWORD("'.$user['password'].'")';

        parent::update($user);

		if(is_array($campusArray)) {
         
            $this->db->delete('userCampus','uid', $user['id']);

			foreach($campusArray as $campus)
			{
                $insertCampus = array('uid' => $user['id'], 'campus'=>$campus, 'defaultCampus'=>$campus == $defaultCampus ? 1 : 0);
                $this->db->insert('userCampus', $insertCampus);
			}
		}
		else
		{
            /* Note that this can insert multiple campus if the update only specified a single value like in a bulk load */
			$campusquery = "INSERT IGNORE INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
			$this->db->query($campusquery);
		}

        $this->db->delete('superusers', 'id', $user['id']);

		if($su) {
			$this->db->insert('superusers', array('id'=>$user['id']));
		}

	}

	function addUser($username, $title, $firstname, $lastname, $synergyid, $group, $campusarray, $year = '', $password = '', $local_email = '', $alt_email = '', $mail_handler = '', $su = 0)
	{
		//sanity checks
		//check username doesnt already exist
		$query = "SELECT username FROM user WHERE username = '".mysql_real_escape_string($username)."'";
		$this->db->query($query);
		if($this->db->countRows() > 0)
			return 0;

		//insert information into database
		$user  = array('username'	=> $username,
						'title'		=> $title,
						'firstname'	=> $firstname,
						'lastname'	=> $lastname,
						'synergy_id' => $synergyid,
						'group_id'	=> $group,
						'year'		=> $year,
						'local_email' => $local_email,
						'alt_email' => $alt_email,
						'mail_handler'=> $mail_handler,
					);
		$this->db->insert('user', $user);

		$id = $this->db->insertID();

		//if password has been set already set it
		if($password)
		{
            $password = mysql_real_escape_string($password);
			$query = "UPDATE user SET password = PASSWORD('$password'), email_pass = '$password' WHERE id = '$id'";
			$this->db->query($query);
		}

		if(is_array($campusarray) && $id)
		{
			foreach($campusarray as $campus)
			{
				$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campus')";
				$this->db->query($campusquery);
			}
		}
		else if($id && $campusarray)
		{
			$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
			$this->db->query($campusquery);
		}

      /* If ticked, add the new user into the Superusers table as well.
       */
      if ($su == 1) {
         $suquery = "INSERT INTO superusers (id) VALUES ('$id')";
         $this->db->query($suquery);
      }

		return $id;
	}


    function update($user) {

    }

    function create($user) {

    }
}
?>
