<?php
#Portal Page to process bulk upload of calander information.
#written by luke cawood
#last updated 13/1/05 - added ability to upload as someone else
#-3nder

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/adminonly.php");

function displayPage()
{
	//nothing has been submitted
	//display input page..
	
	$this->t_body = new Template(TPL_directory);
	$this->t_body->set_file("tplBODY", "admin/uploadCal.htm");
	$this->t_body->set_var("TITLE", HEADING." - Upload Campus Calendar Infomation");

	include_once("../include/page.php");	
	
	$db = new dbManager();

	$groupListHTML = "";
	$query = "SELECT * FROM luCampus ORDER BY name";
	$db->dbDoQuery($query);
	while($line = $db->dbGetNextArray() )
	{
		$groupListHTML .= "<option value=\"".$line['id']."\">".$line['name']."</option>";
	}
	$this->t_body->set_var("CAMPUSHTML", $groupListHTML);
	
	$this->t_body->pparse("finaloutput", "tplBODY");

}

function processCal()
{

	$db = new dbManager();

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
			
	while(!feof($cal))
	{
        	list($date,$stime,$ftime,$comment) = explode(',', fgets($cal,4096), 4);
			
			$comment = trim($comment);
            
			# Skip empty comments and no dates
        	if($comment && $date)
			{
        		ereg('\"([^\"]*)\"|([^\"]*)'  , $comment, $regs);
        		if($regs[1])
                	$comment = $regs[1];
			
				$stime = ($stime) ? "'".$stime."'" : 'NULL';
				$ftime = ($ftime) ? "'".$ftime."'" : 'NULL';

        		$sql = "
                	INSERT
                	INTO    event
                        	(owner, access, sid, priority, note, date, startTime, endTime )
                	VALUES ('{$uid}','3','{$_POST['campus']}', '4', '".mysql_escape_string( trim($comment))."', '$date', $stime, $ftime)
                	";
			
        		if($db->dbDoQuery($sql))
					print "Successfully added event '$comment'<br>";
				else
					print "Failed to add event '$comment'<br>";
			}
	}
}

if ($_FILES['file']['tmp_name']){
	processCal();
}else{
	displayPage();
}
?>