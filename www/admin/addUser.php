<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("user.php");

function displayPage()
{
	//nothing has been submitted
	//display input page..
	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "admin/addUser.htm");
	$this->t_body->set_var("TITLE", HEADING." - Add User");

	include_once("../include/page.php");	

	$db = new dbManager();

	$groupListHTML = "";
	$query = "SELECT * FROM groups ORDER BY name";
	$db->dbDoQuery($query);
	while($line = $db->dbGetNextArray() )
	{
		$groupListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
	}
	$this->t_body->set_var("GROUPLIST", $groupListHTML);

	$campusListHTML = "";
	$query = "SELECT * FROM luCampus WHERE id != ".ALLCAMPUS." ORDER BY name";
	$db->dbDoQuery($query);
	while($line = $db->dbGetNextArray() )
	{
		$campusListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
	}
	$this->t_body->set_var("CAMPUSLIST", $campusListHTML);
	$this->t_body->pparse("finaloutput", "tplBODY");
}

function parseCSV($file)
{
	global $userDb;
	print "Adding users<br>";
	if (is_file($file)) 
	{ 
		$lines = file($file); 
		foreach ($lines AS $line) 
		{ 
			$line = trim($line); 
			$user = explode(',', $line); 
			//username, firstname, lastname, synergy, group, campus, year, password
			if($userDb->addUser(mysql_real_escape_string(trim($user[0])), mysql_real_escape_string(trim($user[1])), mysql_real_escape_string(trim($user[2])), 
			mysql_real_escape_string(trim($user[3])), mysql_real_escape_string(trim($user[4])), mysql_real_escape_string(trim($user[5])), 
			mysql_real_escape_string(trim($user[6])), mysql_real_escape_string(trim($user[7]))))
				print "Added {$user[0]} succesfully<br>";
			else
				print "Unable to add {$user[0]}<br>";
			
		}	 
	} 
}


$userDb = new user;

//two types of upload.. either single user.. or via csv file..

if(isset($_POST['username']) && isset($_POST['group']) && isset($_POST['firstname']) && 
	isset($_POST['lastname']) && isset($_POST['campus']) && isset($_POST['year']) && $_POST['username'] && $_POST['firstname'] 
	&& $_POST['lastname'] && $_POST['group'] && $_POST['campus'] && $_POST['year'])
{
	// THIS SECTION MAY BE WRONG? BY DEFAULT, SET MAIL_HANDLER TO 1 IF ALT_EMAIL EXISTS?????
	if ($_POST['alt_email'] && isset($_POST['alt_email']))
		$mail_handler = 1;
	else
		$mail_handler = 0;
	//'username' and 'password' submitted so just add single user from those variables
	if($userDb->addUser($_POST['username'], $_POST['firstname'], $_POST['lastname'],
			 $_POST['synergyid'], $_POST['group'], $_POST['campus'], $_POST['year'], $_POST['local_email'], $_POST['alt_email'], $mail_handler))	
	{	
		localRedir("/admin/formValid.php?msg=User Succesfully Added&loc=/admin/addUser.php");
	}
	else
	{	
		localRedir("/admin/formValid.php?msg=User Already Exists!&loc=/admin/addUser.php");
	}
	
}
else if(isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name'])
{
	//file submitted
	//parse file
	parseCSV($_FILES['bulkadd']['tmp_name']);
	//localRedir("/formError.php?msg=Users Succesfully Added");
}	
else
{
	//not enough info submitted fail
	displayPage();
}

?>