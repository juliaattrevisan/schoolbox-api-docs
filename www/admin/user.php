<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

/* Class defines interface between user database and software.         *
 * The following actions can be performed; delete, add, update, return */

class user
{
	function user()
        {
                //always gonna need the db so lets create one..
                $this->db = new dbManager();
        }

	function deleteSynUser($id, $trans)
	{
		$query = "SELECT id FROM user WHERE synergy_id = '$id'";
		$this->db->query($query);
		if($this->db->countRows() == 1) //ensure that only one user is deleted
		{
			$user = $this->db->getNextArray();
			$this->deleteUser($user['id'], $trans);
			
			return 1;
		}
		else
		{
			print "More than one user match aborting\n";
			return 0;
		}
	}

	function deleteUsername($username, $trans)
	{
		$query = "SELECT id FROM user WHERE username = '$username'";
		$this->db->query($query);

		if($this->db->countRows() == 1 )
		{
			$user = $this->db->getNextArray();
			$this->deleteUser($user['id'], $trans);
			
			return 1;
		}
		else
		{
			print "More than one user match aborting\n";
			return 0;
		}
	}

	function deleteUser($id, $trans)
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
		$query = "DELETE FROM user WHERE id = '$id'";
		$this->db->query($query);
		
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
				if(!@unlink(SUBMISSION_directory."/".$studsub['id'].".dat"))
					print "Failed to delete: ".SUBMISSION_directory."/".$studsub['id'].".dat";
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
			if(!@unlink(RETURNS_directory."/".$ret['id'].".dat"))
				print "Failed to delete: ".RETURNS_directory."/".$ret['id'].".dat";
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

	function updateUser($id, $username, $title, $firstname, $lastname, $synergyid, $local_email, $alt_email, 
				$group, $password, $campusarray, $su, $year, $eportfolio_enabled)
	{
		//insert information into database
		$user  = array('id'			=> $id,
						'username'	=> $username,
						'title'		=> $title,
						'firstname'	=> $firstname,
						'lastname'	=> $lastname,
						'synergy_id' => $synergyid,
						'group_id'	=> $group,
						'year'		=> $year,
						'local_email' => $local_email,
						'alt_email' => $alt_email,
						'mail_handler'=> $mail_handler,
                        'eportfolio_enabled' => $eportfolio_enabled,
					);

		$this->db->update('user', 'id', $user);

		//if password has been set already set it
		//if($password)
		//{
			$query = "UPDATE user SET password = PASSWORD('$password'), email_pass = '$password' WHERE id = '$id'";
			$this->db->query($query);
		//}


        
		if(is_array($campusarray))
		{
                        $campusquery = "DELETE FROM userCampus WHERE uid = '$id'";
                        $this->db->query($campusquery);
            
			foreach($campusarray as $campus)
			{
				$defaultCampus = $campus == $_POST['defaultCampus'] ? 1 : 0;
				$campusquery = "INSERT INTO userCampus (uid, campus, defaultCampus) VALUES ('$id', '$campus', '$defaultCampus')";
				$this->db->query($campusquery);
			}
		}
		else
		{
            /* Note that this can insert multiple campus if the update only specified a single value like in a bulk load */
			$campusquery = "INSERT IGNORE INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
			$this->db->query($campusquery);
		}
		
		$suquery = "DELETE FROM superusers WHERE id = '$id'";
		$this->db->query($suquery);
	
		if($su)
		{
			$suquery = "INSERT INTO superusers VALUES ('$id')";
			$this->db->query($suquery);
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
	
	function getUserInfo($id)
	{
		//return an array of user info
		$query = "SELECT username, title, firstname, lastname, email_pass, group_id, synergy_id, 
			superusers.id AS su, year, local_email, alt_email, eportfolio_enabled
			FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE user.id = '$id'";
		
		return $this->db->getRow($query);
	}

	function getUserCampus($id)
	{
		//return a list of campuses that the user is involved in
		$query = "SELECT id, name, defaultCampus FROM luCampus, userCampus WHERE userCampus.campus = luCampus.id AND userCampus.uid = '$id' ORDER BY defaultCampus DESC";
		return $this->db->getAll($query);
	} 

	function getUserGroups($id)
	{
		//return a list of groups that a user is involved in and the permission they have in that group
		
		$query = "SELECT id, category.name, userAccess.type FROM userAccess, category WHERE category.id = userAccess.sid 
				AND service = '".HOMEPAGESERVICE."' AND uid = '$id'";
		return $this->db->getAll($query);
	}

	function getUserPermissions($id)
	{
		//return a list of permissions to services (does not include homepage service see getUserGroups for that)
		$query = "SELECT service, sid, type, name FROM userAccess, luServices WHERE service = id AND 
				service != '".HOMEPAGESERVICE."' AND uid = '$id'";
		return $this->db->getAll($query);
	}	
	
	function getCampusID($name)
	{
		if(empty($this->campus)) {
			$query = "SELECT * FROM luCampus";
			foreach($this->db->getAll($query) as $curCampus) {
				$this->campus[$curCampus['name']] = $curCampus['id'];
			}
		}
		
		return isset($this->campus[$name]) ? $this->campus[$name] : false;
	}
	
	function getGroupID($name)
	{
		if(empty($this->groups)) {
			$query = "SELECT id, name FROM groups";
			foreach($this->db->getAll($query) as $group) {
				$this->groups[$group['name']] = $group['id'];
			}
		}
		
		return isset($this->groups[$name]) ? $this->groups[$name] : false;
	}
}
?>
