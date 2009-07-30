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
				$group, $password, $campusarray, $su, $year)
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
					);

		$this->db->update('user', 'id', $user);

		//if password has been set already set it
		//if($password)
		//{
			$query = "UPDATE user SET password = PASSWORD('$password'), email_pass = '$password' WHERE id = '$id'";
			$this->db->query($query);
		//}

		$campusquery = "DELETE FROM userCampus WHERE uid = '$id'";
		$this->db->query($campusquery);
	
		
		if(is_array($campusarray))
		{
			foreach($campusarray as $campus)
			{
				$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campus')";
				$this->db->query($campusquery);
			}
		}
		else
		{
			$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
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

	function addUser($username, $title, $firstname, $lastname, $synergyid, $group, $campusarray, $year = '', $password = '', $local_email = '', $alt_email = '', $mail_handler = '')
	{       
		//sanity checks
		//check username doesnt already exist
		$query = "SELECT username FROM user WHERE username = '$username'";
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
				
		return $id;
	}
	
	function getUserInfo($id)
	{
		//return an array of user info
		$query = "SELECT username, title, firstname, lastname, email_pass, group_id, synergy_id, 
			superusers.id AS su, year, local_email, alt_email
			FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE user.id = '$id'";
		
		return $this->db->getRow($query);
	}

	function getUserCampus($id)
	{
		//return a list of campuses that the user is involved in
		$query = "SELECT id, name FROM luCampus, userCampus WHERE userCampus.campus = luCampus.id AND userCampus.uid = '$id'";
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
	
}
?>
