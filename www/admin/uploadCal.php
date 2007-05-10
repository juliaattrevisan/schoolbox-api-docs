<?php
// Portal Page to process bulk upload of calander information.
// original version jo treweek
// written by luke cawood
// last updated 13/1/05 - added ability to upload as someone else

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");
require_once("../include/permission.php");
require_once("../calendar/calendar.php");

class uploadCal {

function displayPage($response = "")
{
	//nothing has been submitted
	//display input page..
	
	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "admin/uploadCal.htm");
	$this->t_body->set_var("TITLE", HEADING." - Upload Campus Calendar Infomation");
	$this->t_body->set_block("tplBODY", "user_group_check_list", "user_group_chck_lst");
	$this->t_body->set_block("tplBODY", "campus_check_list", "campus_chck_lst");
	
	$db = new dbManager();
	$query = "SELECT * FROM luCampus ORDER BY name";
	$db->dbDoQuery($query);
	while($thisCampus = $db->dbGetNextArray())
	{
		$this->t_body->set_var("CAMPUSNAME", $thisCampus['name']);
		$this->t_body->set_var("CAMPUSID", $thisCampus['id']);
		$this->t_body->parse("campus_chck_lst", "campus_check_list", true);
	}

	$db = new dbManager();
	$query = "SELECT * FROM groups ORDER BY name";
	$db->dbDoQuery($query);
	while($thisCampus = $db->dbGetNextArray())
	{
		$this->t_body->set_var("USERGROUPNAME", $thisCampus['name']);
		$this->t_body->set_var("USERGROUPID", $thisCampus['id']);
		$this->t_body->parse("user_group_chck_lst", "user_group_check_list", true);
	}
	include_once("../include/page.php");
	$this->t_body->set_var("UPLOADRESULTS", $response);
	$this->t_body->pparse("finaloutput", "tplBODY");
	
}

function processCal()
{

	$db = new dbManager();
	$calObj = new Calendar();
	$cal = fopen($_FILES['file']['tmp_name'], 'r');
	if (!$cal) 
	{
		die("unable to open file");
	}

	//set defaults for id and campus if they are not submitted
	if($_POST['userid'] == "")
		$uid = $_SESSION['id'];
	else
		$uid = $_POST['userid'];
		
	if($_POST['campus'] == "")
		$campus = $_SESSION['campus'][0];
	else
		$campus = $_POST['campus'];
	$output	= "";		
	while(!feof($cal))
	{

		list($date,$stime,$ftime,$comment,$priority) = fgetcsv($cal,4096);
        
        	$comment = trim($comment);
        
        	# Skip empty comments and no dates
        	if($comment && $date)
        	{
        	    if($priority < 1 && $priority > 4)
        	        $priority = 4;
        
        	    $stime = ($stime) ? "'".$stime."'" : 'NULL';
        	    $ftime = ($ftime) ? "'".$ftime."'" : 'NULL';
	
        	    $sql = "INSERT INTO event (owner, access, priority, note, date, startTime, endTime)
               		 VALUES ('{$uid}',3, '$priority', '".mysql_escape_string(trim($comment))."', '$date', $stime, $ftime)"; 
				
				
			if($db->dbDoQuery($sql))
			{
				$newEventID = $db->dbInsertId();
				$calObj->addPerms($newEventID, $_POST['userGroup'], $_POST['campus']);
				$output .= "<div style=\"color: #66CC33;\">Successfully added event '$comment'<br /></div>";
				
			}
			else
				$output .= "<div style=\"color: #CC3300;\">Failed to add event '$comment'<br /></div>";
					
		}
	}
	$this->displayPage($output);
}
}

$page = new uploadCal;

if ($_FILES['file']['tmp_name'])
{
	$page->processCal();
}else
{
	$page->displayPage();
}
?>
