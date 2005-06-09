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
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() == 1) //ensure that only one user is deleted
		{
			$user = $this->db->dbGetNextArray();
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
		$this->db->dbDoQuery($query);

		if($this->db->dbCountRows() == 1 )
		{
			$user = $this->db->dbGetNextArray();
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
		$this->db->dbDoQuery($query);
		
	}
                        

	function transferOwnership($curr, $new)
	{

		//update all files
		$query = "UPDATE file SET owner = '$new' WHERE owner = '$curr'";
		$this->db->dbDoQuery($query);

		//update all links
		$query = "UPDATE link SET owner = '$new' WHERE owner = '$curr'";
		$this->db->dbDoQuery($query);

		//update all news
		$query = "UPDATE news_items SET item_owner = '$new' WHERE item_owner = '$curr'";
		$this->db->dbDoQuery($query);

		$query = "UPDATE subject_news SET owner = '$new' WHERE owner = '$curr'";
		$this->db->dbDoQuery($query);

		//update all forums
		$query = "UPDATE forum SET owner = '$new' WHERE owner = '$curr'";
		$this->db->dbDoQuery($query);
	
	}

	function cleanSubmissionBoxes($id)
	{
		//first select all users submission boxes
		$query = "SELECT id FROM submission_box WHERE owner = '$id'";
		$result = $this->db->dbDoQuery($query);

		//foreach submission box delete all student submissions
		while($box = $this->db->dbGetNextArray($result))
		{
			//select all student submissions
			$query = "SELECT id FROM student_submission WHERE box_id = '".$box['id']."'";
			$this->db->dbDoQuery($query);
			//for each student submission delete from hdd
			while($studsub = $this->db->dbGetNextArray() )
			{
				if(!@unlink(SUBMISSION_directory."/".$studsub['id'].".dat"))
					print "Failed to delete: ".SUBMISSION_directory."/".$studsub['id'].".dat";
			}
	
			//delete all student submissions from db
			$query = "DELETE FROM student_submission WHERE box_id = '".$box['id']."'";
			$this->db->dbDoQuery($query);

		}

		//delete all submission boxes from db
		$query = "DELETE FROM submission_box WHERE owner = '$id'";
		$this->db->dbDoQuery($query);
	}

	function cleanReturnedWork($id)	
	{
		$query = "SELECT id FROM submission_return WHERE owner = '$id'";
		$this->db->dbDoQuery($query);

		while($ret = $this->db->dbGetNextArray() )
		{
			if(!@unlink(RETURNS_directory."/".$ret['id'].".dat"))
				print "Failed to delete: ".RETURNS_directory."/".$ret['id'].".dat";
		} 

		$query = "DELETE FROM submission_return WHERE owner = '$id'";
		$this->db->dbDoQuery($query);
	}

	function cleanUserFromSystem($id)
	{
		//remove users bookmarks
		$query = "DELETE FROM bookmarks WHERE owner = '$id'";
		$this->db->dbDoQuery($query);

		//ph extension
		$query = "DELETE FROM ph_ext WHERE owner = '$id'";
		$this->db->dbDoQuery($query);

		//superusers
		$query = "DELETE FROM superusers WHERE id = '$id'";
		$this->db->dbDoQuery($query);

		//user access 
		$query = "DELETE FROM userAccess WHERE uid = '$id'";
		$this->db->dbDoQuery($query);

		//delete personal calendar events
		$query = "DELETE FROM event WHERE access = '1' AND owner = '$id'";
		$this->db->dbDoQuery($query);
		$query = "DELETE FROM eventWeekly WHERE access = '1' AND owner = '$id'";
		$this->db->dbDoQuery($query);
		$query = "DELETE FROM eventMonthly WHERE access = '1' AND owner = '$id'";
		$this->db->dbDoQuery($query);

		//delete log entries
		$query = "DELETE FROM homepage_log WHERE user = '$id'";
		$this->db->dbDoQuery($query);
		$query = "DELETE FROM submission_log WHERE user = '$id'";
		$this->db->dbDoQuery($query);
		
	}

	function updateUser($id, $username, $firstname, $lastname, $synergyid, $local_email, $alt_email, 
				$group, $password, $campusarray, $su, $year)
	{
		//update user info  
		if ($year == "")
			$year = "NULL";
		else
			$year = "'$year'";
		$query = "UPDATE user SET username='$username', firstname='$firstname', lastname='$lastname',
			synergy_id='$synergyid', local_email='$local_email', alt_email='$alt_email', group_id='$group', 
			password=PASSWORD('$password'), email_pass='$password', year=$year WHERE id ='$id'";

		$this->db->dbDoQuery($query);

		$campusquery = "DELETE FROM userCampus WHERE uid = '$id'";
		$this->db->dbDoQuery($campusquery);
	
		
		if(is_array($campusarray))
		{
			foreach($campusarray as $campus)
			{
				$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campus')";
				$this->db->dbDoQuery($campusquery);
			}
		}
		else
		{
			$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
			$this->db->dbDoQuery($campusquery);
		}
		
		$suquery = "DELETE FROM superusers WHERE id = '$id'";
		$this->db->dbDoQuery($suquery);
	
		if($su)
		{
			$suquery = "INSERT INTO superusers VALUES ('$id')";
			$this->db->dbDoQuery($suquery);
		}

	}

	function addUser($username, $firstname, $lastname, $synergyid, $group, $campusarray, $year = '', $password = '', $local_email = '', $alt_email = '', $mail_handler = '')
	{       
		//sanity checks
		//check username doesnt already exist
		$query = "SELECT username FROM user WHERE username = '$username'";
		$this->db->dbDoQuery($query);
		if($this->db->dbCountRows() > 0)
			return 0;
		
		//insert information into database
		$query = "INSERT INTO user (username, firstname, lastname, synergy_id, group_id, year, local_email, alt_email, mail_handler) VALUES
					  ('$username', '$firstname', '$lastname', '$synergyid', '$group', '$year', '$local_email', '$alt_email', '$mail_handler')";
		
		$this->db->dbDoQuery($query);
		$id = $this->db->dbInsertId();
		
		//if password has been set already set it
		if($password)
		{
			$query = "UPDATE user SET password = PASSWORD('$password'), email_pass = '$password' WHERE id = '$id'";
			$this->db->dbDoQuery($query);
		}
		
		if(is_array($campusarray))
		{
			foreach($campusarray as $campus)
			{
				$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campus')";
				$this->db->dbDoQuery($campusquery);
			}
		}
		else
		{
			$campusquery = "INSERT INTO userCampus (uid, campus) VALUES ('$id', '$campusarray')";
			$this->db->dbDoQuery($campusquery);
		}
				
		return $id;
	}
	
	function getUserInfo($id)
	{
		//return an array of user info
		$query = "SELECT username, firstname, lastname, email_pass, group_id, synergy_id, 
			superusers.id AS su, year, local_email, alt_email
			FROM user LEFT JOIN superusers ON user.id = superusers.id WHERE user.id = '$id'";
		
		return $this->db->dbGetSingleRow($query);
	}

	function getUserCampus($id)
	{
		//return a list of campuses that the user is involved in
		$query = "SELECT id, name FROM luCampus, userCampus WHERE userCampus.campus = luCampus.id AND userCampus.uid = '$id'";
		return $this->db->dbGetAllRows($query);
	} 

	function getUserGroups($id)
	{
		//return a list of groups that a user is involved in and the permission they have in that group
		
		$query = "SELECT id, category.name, userAccess.type FROM userAccess, category WHERE category.id = userAccess.sid 
				AND service = '".HOMEPAGESERVICE."' AND uid = '$id'";
		return $this->db->dbGetAllRows($query);
	}

	function getUserPermissions($id)
	{
		//return a list of permissions to services (does not include homepage service see getUserGroups for that)
		$query = "SELECT service, sid, type, name FROM userAccess, luServices WHERE service = id AND 
				service != '".HOMEPAGESERVICE."' AND uid = '$id'";
		return $this->db->dbGetAllRows($query);
	}	
 
}
?>
