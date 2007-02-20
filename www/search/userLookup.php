<?php

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/permission.php");

class outUser
{
	function displayUser($id)
	{
		//display user description page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "search/outUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Address Book");

		include_once("../include/page.php");			

		$db = new dbManager;
		
		$query = "SELECT user.id, firstname, lastname, username, groups.name AS gname, group_id, extension, 
					synergy_id, local_email, alt_email
				FROM (user, groups) LEFT JOIN ph_ext ON user.id = ph_ext.owner
				WHERE user.group_id = groups.id AND user.id = '$id'";
		$user = $db->dbGetSingleRow($query);
		
		//fill out all fields
		
		$this->t_body->set_var("NAME", $user['firstname']." ".$user['lastname']);
		
			//create owner email address
			if($user['local_email']) {
				$emailAddress = $user['username']."@".MAIL_address;
				$email = "<a href=\"".$_SESSION['mailhandle']."$emailAddress\">$emailAddress</a>";
			}
			else if($user['alt_email']) {
				$emailAddress = $user['alt_email'];
				$email = "<a href=\"".$_SESSION['mailhandle']."$emailAddress\">$emailAddress</a>";
			}
			else
				$email = "No address available";
		
		$this->t_body->set_var("EMAIL_ADDRESS", $emailAddress);
		$this->t_body->set_var("EMAIL", $email);
		
		if($user['extension'] != "")
			$this->t_body->set_var("EXT", "<b>Extension</b>: ".$user['extension']);
		else
			$this->t_body->set_var("EXT", "");
		$this->t_body->set_var("POSTION", $user['gname']);
		$this->t_body->set_var("HOMEPAGE", "");
		
		$this->t_body->set_var("SYNERGY_ID", $user['synergy_id']);
		
		$this->t_body->set_block("tplBODY", "portrait", "portrait_block");
		$this->t_body->set_block("tplBODY", "student_synergy", "student_synergy_block");
		
		//advanced synergetic information
		if(SYNERGY_enabled)
		{
			require_once('../include/syn_userinfo.php');

			//only allow staff to see student information
			if(isStaff($_SESSION['gid']))
			{
				$this->t_body->parse("portrait_block", "portrait");
				
				if(isStudent($user['group_id']))
				{
					$synInfo = getStudentInfo($user['synergy_id']);
					
					list($date, $month, $year) = explode(' ', $synInfo['StudentBirthDate']);
					$birthdate = strtotime($date.' '.$month.' '.$year);
					
					$this->t_body->set_var("TUTOR", $synInfo['StudentTutor']);
					$this->t_body->set_var("YEAR_LEVEL", $synInfo['StudentYearLevel']);
					$this->t_body->set_var("BIRTHDATE", date('jS \o\f F, Y', $birthdate));
					$this->t_body->set_var("EMERGENCY_NAME", $synInfo['StudentEmergencyName']);
					$this->t_body->set_var("EMERGENCY_PHONE", $synInfo['StudentEmergencyPhone']);
					$this->t_body->set_var("EMERGENCY_ALT_PHONE", $synInfo['StudentEmergencyPhoneAlt']);
					$this->t_body->set_var("CONTACT_NAME", $synInfo['StudentContactTitle'].' '.$synInfo['StudentContactPreferred'].' '.$synInfo['StudentContactSurname']);
					$this->t_body->set_var("CONTACT_ADDRESS", $synInfo['StudentContactAddress1'].'<br>'.$synInfo['StudentContactSuburb'].', '.$synInfo['StudentContactState'].' '.$synInfo['StudentContactPostCode']);
					$this->t_body->set_var("CONTACT_HOME_PHONE", $synInfo['StudentContactPhoneActual']);
					$this->t_body->set_var("CONTACT_MOBILE_PHONE", $synInfo['StudentContactMobilePhone']);
					$this->t_body->set_var("CONTACT_OCCUP_PHONE", $synInfo['StudentContactOccupPhone']);
					$this->t_body->set_var("SPOUSE_NAME", $synInfo['StudentContactSpouseTitle'].' '.$synInfo['StudentContactSpousePreferred'].' '.$synInfo['StudentContactSpouseSurname']);
					$this->t_body->set_var("SPOUSE_MOBILE_PHONE", $synInfo['StudentContactSpouseMobilePhone']);
					$this->t_body->set_var("SPOUSE_OCCUP_PHONE", $synInfo['StudentContactSpouseOccupPhone']);
					
					$this->t_body->parse("student_synergy_block", "student_synergy");
				}
				else
				{
					$this->t_body->set_var("student_synergy_block", "");
				}
			}
			else
			{
				$this->t_body->set_var("student_synergy_block", "");
				$this->t_body->set_var("portrait_block", "");	
			}
		}
		else
		{
			$this->t_body->set_var("student_synergy_block", "");
			$this->t_body->set_var("portrait_block", "");	
		}
		//display list of groups owner is in charge of so they can add the user
		$this->t_body->set_block("tplBODY", "grouplist", "group_block");
		if($_SESSION['id'] != $id && $_SESSION['gid'] < 3)
		{
			$query = "SELECT service, sid, category.name FROM userAccess, category 
			WHERE sid = category.id AND uid = '".$_SESSION['id']."' AND type = '3'"; 
			
			$db->dbDoQuery($query);
			if($db->dbCountRows() > 0)
			{
				$this->t_body->set_var("GROUPTITLE", "Groups that you maintain that this user can be added to;");
				while($group = $db->dbGetNextArray())
				{
					$this->t_body->set_var("GROUP", 
					"<a href=\"/group/modUserGroup.php?uid=$id&service=".$group['service']."
					&sid=".$group['sid']."\">".$group['name']."</a>");
					$this->t_body->parse("group_block", "grouplist", true);
				}		
			}
			else
			{
				$this->t_body->set_var("GROUPTITLE", "");
				$this->t_body->set_var("group_block", "");
			}
			
		}
		else
		{
			$this->t_body->set_var("GROUPTITLE", "");
			$this->t_body->set_var("group_block", "");
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayFindUser()
	{
		//display search for user page
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "search/findUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Find User");
		include_once("../include/page.php");	

		//setup group drop down
		$db = new dbManager();

		$groupListHTML = "<option value=\"\"></option>";
		$query = "SELECT * FROM groups ORDER BY name";
		$db->dbDoQuery($query);
		while($line = $db->dbGetNextArray() )
		{
			$groupListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
 		}
		$this->t_body->set_var("GROUPS", $groupListHTML);		

		$this->t_body->pparse("finaloutput", "tplBODY");
	}

	function displayUserList($users)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "search/listUser.htm");
		$this->t_body->set_var("TITLE", HEADING." - Find User");
		include_once("../include/page.php");	
		$this->t_body->set_block("tplBODY", "userlist", "user_block");
		
		$db = new dbManager;
		
		foreach($users as $user)
		{
			$query = "SELECT firstname, lastname, username, groups.name AS gname, year, local_email, alt_email FROM user, groups 
				WHERE user.group_id = groups.id AND user.id = '$user'";  
			$userdetails = $db->dbGetSingleRow($query);
			$this->t_body->set_var("FIRSTNAME", $userdetails['firstname']);
			$this->t_body->set_var("LASTNAME", $userdetails['lastname']);
			$this->t_body->set_var("YEAR", $userdetails['year']);
			
			if($userdetails['local_email'])
				$email = "<a href=\"".$_SESSION['mailhandle'].$userdetails['username']."@".MAIL_address."\">".$userdetails['username']."@".MAIL_address."</a>";
			else if($userdetails['alt_email'])
				$email = "<a href=\"".$_SESSION['mailhandle'].$userdetails['alt_email']."\">".$userdetails['alt_email']."</a>";
			else
				$email = "";
			
			$this->t_body->set_var("EMAIL", $email);
			$this->t_body->set_var("GROUP", $userdetails['gname']);
			$this->t_body->set_var("ID", $user);
			$this->t_body->parse("user_block", "userlist", true);
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}
		
	function searchUser($keyword, $group)
	{
		//search for a user based on at least one of the above fields
		//assumes at least one contains something
	
		$query = "SELECT id FROM user WHERE ";

		if($keyword != "")
		{
			if( is_numeric( $keyword)) 
			{
				$query .= "( synergy_id = $keyword OR year = $keyword ) ";
			}
			else 
			{
				if(preg_match('/([[:alpha:]\']+) ([[:alpha:]\']+)/', $keyword, $name)) 
				{
					$query .= "( firstname like '%{$name[1]}%' and lastname like '%{$name[2]}%' ) ";
				}
				else 
				{
					$query .= "( firstname like '%$keyword%' or lastname like '%$keyword%' or username like '%$keyword%' ) ";
				}
			}
				
			if($group)
			{
				$query .= "AND group_id = $group ";
			}
		}
		else if($group)
		{
			$query .= "group_id = $group ";
		}
		else
		{
			error(0);
		}
		
		$query .= "ORDER BY group_id, year, lastname, firstname"; 

		$db = new dbManager();
		$db->dbDoQuery($query);
		//for each user put id in an array and return it.
		if($db->dbCountRows() > 0)
		{
			while($user = $db->dbGetNextArray())
			{
				$returnArray[] = $user['id'];
			}
			return $returnArray;
		} 
		else
			return 0;
	}

		
}

$myPage = new outUser;

if($_GET['keyword'] || $_GET['group'])
{
	$users = $myPage->searchUser($_GET['keyword'], $_GET['group']);
	//if no users found return to search page
	if($users == 0)
		error(31);
	else
	{ 
		//if more 1 user just display details else display list of found users
		if(count($users) == 1)
			$myPage->displayUser($users[0]);
		else
			$myPage->displayUserList($users);
	}			
}
else if($_GET['id'])		
{
	$myPage->displayUser($_GET['id']);
}
else
	$myPage->displayFindUser();



