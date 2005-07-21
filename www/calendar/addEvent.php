<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//displays addEvent templates and inputs form information to calendar class

require_once("../include/session.php");
require_once("../include/include.php");
require_once("../include/html-time.php");
require_once("../include/permission.php");
require_once("calendar.php");

class addEvent
{
	function addEvent()
	{
		$this->db = new dbManager;
		$this->db->dbConnect();
	}

	function campusHTMLList($name, $selected) 
	{
		if($_SESSION['su'])
			$SQL_QUERY = "SELECT id, name FROM luCampus";
		else
	        	$SQL_QUERY = "SELECT id, name FROM luCampus, userAccess WHERE luCampus.id = userAccess.sid AND 
			userAccess.service = '2' AND uid = ".$_SESSION['id']." AND type > '1'";   

		$this->db->dbDoQuery($SQL_QUERY);
	        $i = 1;
	        $allCampus = "<select name=\"".$name."\" class=\"formfield\">";  	
        	while ($thisCampus = $this->db->dbGetNextArray()) 
		{
			if($selected == $thisCampus['id'])
	        	        $allCampus .= "<option value=\"".$thisCampus['id']."\" selected>".$thisCampus['name']."</option>";
        		else	
		        	$allCampus .= "<option value=\"".$thisCampus['id']."\">".$thisCampus['name']."</option>";
        	}
		$allCampus .= "</select>";        

		return $allCampus;
	}  

	function groupHTMLList($name, $selected) 
	{
		//get a list of all groups the user has write access to

	        $SQL_QUERY = "SELECT id, name FROM userAccess, category WHERE uid = '".$_SESSION['id']."' AND service = '1'
				AND type > 1 AND sid = id";   
	        $this->db->dbDoQuery($SQL_QUERY);
	        $i = 1;
	        $allGroup = "<select name=\"".$name."\" class=\"formfield\">";  	
        	while ($thisGroup = $this->db->dbGetNextArray()) 
		{
			if($selected == $thisGroup['id'])
	        	        $allGroup .= "<option value=\"".$thisGroup['id']."\" selected>".$thisGroup['name']."</option>";
			else
	        	        $allGroup .= "<option value=\"".$thisGroup['id']."\">".$thisGroup['name']."</option>";
        	}
		$allGroup .= "</select>";        

		return $allGroup;
	}  

	function setSelectedHTML($name, $selected, $start, $finish)
	{
		//use this function to set selected variables in a static drop down menu in html
		//if selected is outside the start finish range no value will be marked selected
		
		for($i = $start; $i <= $finish; $i++)
		{
			if($i == $selected)
				$this->t_body->set_var($name.$i, "selected"); 
			else
				$this->t_body->set_var($name.$i, "");
		}
	} 
	
	function setTimeVars($time, $totime)
	{
		if($time != "" && $time != "00:00:00") 
		{
			$hour = substr($time, 0, 2);
			if($hour > 12)
			{
				$hour = $hour - 12;
				$this->t_body->set_var("PM", "selected");
				$this->t_body->set_var("AM", "");
			}
			else
			{
				$this->t_body->set_var("AM", "");
				$this->t_body->set_var("PM", "");
			}
			$min = substr($time, 3, 2);

			$this->t_body->set_var("HOUR", $hour);
			$this->t_body->set_var("MIN", $min);
		}
		else
		{
			$this->t_body->set_var("HOUR", "");
			$this->t_body->set_var("MIN", "");
			$this->t_body->set_var("AM", "");
			$this->t_body->set_var("PM", "");
		}
		
	
		if($totime !="" && $totime != "00:00:00")
		{
			$tohour = substr($totime, 0, 2);
			if($tohour > 12)
			{
				$tohour = $tohour - 12;
				$this->t_body->set_var("PMTO", "selected");
				$this->t_body->set_var("AMTO", "");
			}
			else
			{
				$this->t_body->set_var("AMTO", "");
				$this->t_body->set_var("PMTO", "");
			}

			$tomin = substr($totime, 3, 2);

			$this->t_body->set_var("TOHOUR", $tohour);
			$this->t_body->set_var("TOMIN", $tomin);
		}
		else
		{
			$this->t_body->set_var("AMTO", "");
			$this->t_body->set_var("PMTO", "");
			$this->t_body->set_var("TOHOUR", "");
			$this->t_body->set_var("TOMIN", "");
		}

	}

	function setDropdowns($levSelected, $campSelected, $groupSelected)
	{
		//function sets the level drop down
		//only gives access to appropriate drop downs

                $leveldrop = "";

		if($levSelected == 1)
                            $leveldrop = "<option value=\"1\" selected >Personal</option>";
		else
                            $leveldrop = "<option value=\"1\">Personal</option>";

		$campusHTML = $this->campusHTMLList("campus", $campSelected);
		$groupHTML = $this->groupHTMLList("group", $groupSelected);

		//if the campus drop down contains campuses then output it and the option to select it in level
		if(strlen($groupHTML) > 50)
		{
			$this->t_body->set_var("GROUP", $groupHTML);
			if($levSelected == 2)
				$leveldrop .= "<option value=\"2\" selected>Group</option>";
			else
				$leveldrop .= "<option value=\"2\">Group</option>";
		}
		else if(($groupSelected && $levSelected == 2) )
			error(0);   //no longer has access to modify even though owner?
		else
			$this->t_body->set_var("GROUP", $groupHTML);


		//if the campus drop down contains campuses then output it and the option to select it in level
		if(strlen($campusHTML) > 50)
		{
			$this->t_body->set_var("CAMPUS", $campusHTML);
			if($levSelected == 3)
				$leveldrop .= "<option value=\"3\" selected>Campus</option>";
			else
				$leveldrop .= "<option value=\"3\">Campus</option>";
		}
		else if(($campSelected && $levSelected == 3) && !$_SESSION['su'])
			error(0);   //no longer has access to modify even though owner?
		else
			$this->t_body->set_var("CAMPUS", "");
			

		$this->t_body->set_var("LEVEL", $leveldrop);

	}

	function displayAddSingleEvent($date=0, $month=0, $level=0, $sid=0)
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "calendar/addSingleEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add Single Calender Event");
		
		$this->t_body->set_var("FUNCTION", "Add");
		
		include_once("../include/page.php");
		
		if($date && $month && checkdate($month,$date, date('Y')))
			$this->t_body->set_var("DATE", "$date/$month/".date('Y'));
		else
			$this->t_body->set_var("DATE", date('d/m/Y'));
		
		$this->setTimeVars("", "");
		
		$this->setDropDowns($level,$sid,$sid);
		
		$this->setSelectedHTML("PRIORITY", 0, 1, 4);
		$this->setSelectedHTML("LEVEL", 0, 1, 4);
		
		$this->t_body->set_var("DESCRIPTION", "");
		$this->t_body->set_var("HIDDEN", "");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->set_var("delete_button_block", "");
		
		$this->t_body->pparse("final", "tplBODY");
	}

	function displayModSingleEvent($id)
	{
	    $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "calendar/addSingleEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify Single Calender Event");
	
  		include_once("../include/page.php");
		$cal = new Calendar();
		$event = $cal->getSingleEvent($id, "single");
		
		//check security perms
		if($event['access'] == 1 && $_SESSION['id'] != $event['owner'])
		{
			if($cal->checkSecurity($event['access'], $id) < 2)
				error(34);
		}

		$this->t_body->set_var("FUNCTION", "Update");
		
		//$this->t_body->set_var("DATE", createDateList(substr($event['date'],8,2), "date"));
		//$this->t_body->set_var("MONTH", createMonthList(substr($event['date'],5, 2), "month"));
		$this->t_body->set_var("DATE", date('d/m/Y', strtotime($event['date'])));

		$this->setTimeVars($event['startTime'], $event['endTime']);

		$this->setSelectedHTML("PRIORITY", $event['priority'], 1, 4);
		$this->setDropDowns($event['access'],$event['sid'],$event['sid']);

		$this->t_body->set_var("DESCRIPTION", $event['note']);
		$this->t_body->set_var("HIDDEN", "<input type=\"hidden\" name=\"id\" value=\"".$event['id']."\">");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->parse("delete_button_block", "delete_buton");

		$this->t_body->pparse("final", "tplBODY");

	}		
	
	function displayAddMonthlyEvent($date, $level=0, $sid=0)
	{
        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "calendar/addMonthlyEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add Monthly Calender Event");
		$this->t_body->set_var("FUNCTION", "Add");

		include_once("../include/page.php");
		
		$this->setSelectedHTML("DAYOCC", 0, 1, 5);
		$this->setSelectedHTML("DAY", 0, 1, 7);

		if($date)
			$this->t_body->set_var("DATE", createDateList($date, "date"));
		else
			$this->t_body->set_var("DATE", createDateList(date("j"), "date"));

		$this->setTimeVars("", "");

		$this->t_body->set_var("BEGIN_DATE", createDateList(date("j"), "begin_date"));
		$this->t_body->set_var("BEGIN_MONTH", createMonthList(date("n"), "begin_month"));
		$this->t_body->set_var("END_DATE", createDateList(date("j"), "end_date"));
		$this->t_body->set_var("END_MONTH", createMonthList(date("n"), "end_month"));

		$this->setDropDowns($level,$sid,$sid);

		$this->t_body->set_var("DESCRIPTION", "");
		$this->t_body->set_var("HIDDEN", "");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->set_var("delete_button_block", "");

		$this->t_body->pparse("final", "tplBODY");

	}				

	function displayModMonthlyEvent($id)
	{
        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "calendar/addMonthlyEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify Monthly Calender Event");
		$this->t_body->set_var("FUNCTION", "Update");

		include_once("../include/page.php");
		
		$cal = new Calendar();
		
		$event = $cal->getSingleEvent($id, "month");

		//check security perms
		if($event['access'] == 1 && $_SESSION['id'] != $event['owner'])
		{
			if($cal->checkSecurity($event['access'], $id) < 2)
				error(34);
		}

		$this->setSelectedHTML("DAYOCC", $event['dayocc'], 1, 5);
		$this->setSelectedHTML("DAY", $event['day'], 1, 7);
		
		$this->t_body->set_var("DATE", createDateList(substr($event['date'],8,2), "date"));

		$this->setTimeVars($event['startTime'], $event['endTime']);


		$this->t_body->set_var("BEGIN_DATE", createDateList(substr($event['startDuration'], 8, 2), "begin_date"));
		$this->t_body->set_var("BEGIN_MONTH", createMonthList(substr($event['startDuration'], 5, 2), "begin_month"));
		$this->t_body->set_var("END_DATE", createDateList(substr($event['endDuration'], 8, 2), "end_date"));
		$this->t_body->set_var("END_MONTH", createMonthList(substr($event['endDuration'], 5, 2), "end_month"));


		$this->setSelectedHTML("PRIORITY", $event['priority'], 1, 4);
		$this->setDropDowns($event['acess'],$event['sid'],$event['sid']);

		$this->t_body->set_var("DESCRIPTION", $event['note']);
		$this->t_body->set_var("HIDDEN", "<input type=\"hidden\" name=\"id\" value=\"".$event['id']."\">");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->parse("delete_button_block", "delete_buton");

		$this->t_body->pparse("final", "tplBODY");

	}		
	

	
	function displayAddWeeklyEvent($date, $level=0, $sid=0)
	{
        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "calendar/addWeeklyEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Add Weekly Calender Event");
		$this->t_body->set_var("FUNCTION", "Add");

		include_once("../include/page.php");

		$this->setTimeVars("", "");

		$this->t_body->set_var("BEGIN_DATE", createDateList(date("j"), "begin_date"));
		$this->t_body->set_var("BEGIN_MONTH", createMonthList(date("n"), "begin_month"));
		$this->t_body->set_var("END_DATE", createDateList(date("j"), "end_date"));
		$this->t_body->set_var("END_MONTH", createMonthList(date("n"), "end_month"));

		$this->setDropDowns($level,$sid,$sid);

		$this->t_body->set_var("DESCRIPTION", "");
		$this->t_body->set_var("HIDDEN", "");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->set_var("delete_button_block", "");

		$this->t_body->pparse("final", "tplBODY");

	}

	function displayModWeeklyEvent($id)
	{
        $this->t_body = new Template(TPL_directory);
        $this->t_body->set_file("tplBODY", "calendar/addWeeklyEvent.htm");
		$this->t_body->set_var("TITLE", HEADING." - Modify Weekly Calender Event");
		$this->t_body->set_var("FUNCTION", "Update");

		include_once("../include/page.php");
		
		$cal = new Calendar();
	
		$event = $cal->getSingleEvent($id, "week");

		//check security perms
		if($event['access'] == 1 && $_SESSION['id'] != $event['owner'])
		{
			if($cal->checkSecurity($event['access'], $id) < 2)
				error(34);
		}

		$this->setSelectedHTML("DAY", $event['day'], 1, 7);
		
		$this->setTimeVars($event['startTime'], $event['endTime']);

		$this->t_body->set_var("BEGIN_DATE", createDateList(substr($event['startDuration'], 8, 2), "begin_date"));
		$this->t_body->set_var("BEGIN_MONTH", createMonthList(substr($event['startDuration'], 5, 2), "begin_month"));
		$this->t_body->set_var("END_DATE", createDateList(substr($event['endDuration'], 8, 2), "end_date"));
		$this->t_body->set_var("END_MONTH", createMonthList(substr($event['endDuration'], 5, 2), "end_month"));

		$this->setSelectedHTML("PRIORITY", $event['priority'], 1, 4);
		$this->setDropDowns($event['acess'],$event['sid'],$event['sid']);

		$this->t_body->set_var("DESCRIPTION", $event['note']);
		$this->t_body->set_var("HIDDEN", "<input type=\"hidden\" name=\"id\" value=\"".$event['id']."\">");
		$this->t_body->set_block("tplBODY", "delete_button", "delete_button_block");
		$this->t_body->parse("delete_button_block", "delete_buton");

		$this->t_body->pparse("final", "tplBODY");

	}		
	

	
	function checkDay($day)
	{
		if( $day < 1 || $day > 7)
			error(9, "Invalid day selected");
	}

	function checkDateNum($date)
	{
		if($date < 1 || $date > 31)
			error(9, "Invalid date selected: $date");
	}

	function checkTime($hour, $minute)
	{
		if ($hour < 0 || $hour > 12 || $minute < 0 || $minute >= 60 )
			error(9, "Invalid time selected: $hour:$minute");
	}		

	function createTime($hour, $minute, $period)
	{
		//in this strange world we live in 12:00 am is actually 00:00 not 12:00 and 12:00 pm is actually 12:00 no 24:00 or 0:00  
		if($period == 'am') //am time period
		{
			$time = ($hour%12).":".$minute.":00";
		}
		else if($period == 'pm') //pm period
		{

			$time = (($hour%12)+12).":".$minute.":00";
		}
		else
			error(9, "Invalid time selected");
		
		return $time;
	}
}


$page = new addEvent();

if(isset($_POST['priority']) && $_POST['priority'] && isset($_POST['note']) && $_POST['note'])
{
	$cal = new Calendar();

	if(isset($_POST['hour']) && $_POST['hour'] )
	{
		$page->checkTime($_POST['hour'], $_POST['minute']);
		$startTime = $page->createTime($_POST['hour'], $_POST['minute'], $_POST['period']);
	}
	else
		$startTime = "00:00:00";

	if(isset($_POST['tohour']) && $_POST['tohour'] )
	{
		$page->checkTime($_POST['tohour'], $_POST['tominute']);	
		$endTime = $page->createTime($_POST['tohour'], $_POST['tominute'], $_POST['toperiod']);
	}
	else
		$endTime = "00:00:00";

	//make sure level is correct if just default to personal
	if( $level < 1 || $level > 3 )
		$level = 1;


	//format startDuration and endDuration
	if($_POST['begin_date'] && $_POST['begin_month'])
		$startDuration = mktime(0,0,0,$_POST['begin_month'], $_POST['begin_date'], date("Y"));
	if($_POST['end_date'] && $_POST['end_month'])
		$endDuration = mktime(0,0,0,$_POST['end_month'], $_POST['end_date'], date("Y"));

	//convert level options into sid
	if($_POST['level'] == 2)
		$sid = $_POST['group'];
	else if($_POST['level'] == 3)
		$sid = $_POST['campus'];

	//add/modify single event
	if($_POST['date'])
	{
		list($date,$month,$year) = explode('/',$_POST['date'],3); 
		if( !checkdate($month, $date, $year) )		
			error(9);

		if($_POST['delete']){
			$cal->deleteSingleEvent($_POST['id'], $_POST['level'], $sid);
		}
		else if($_POST['id']){
			//modify event
			$cal->modSingleEvent($_POST['id'], mktime(0,0,0, $month, $date, $year),
                        $startTime, $endTime, $_POST['priority'], $_POST['note'],
                                $_POST['level'], $sid);	
		}else{
		//insert event 
			$cal->addSingleEvent(mktime(0,0,0, $month, $date, $year), 
			$startTime, $endTime, $_POST['priority'], $_POST['note'], 
				$_POST['level'], $sid); 
		}

		//redirect back to where they came from
		$back = -2;
		include("../include/back.php");
		exit;
	}
	//add/modify monthly event with just a date
	else if( isset($_POST['date']) && $_POST['date'] )
	{
		$page->checkDateNum($_POST['date']);	
		if($_POST['delete']){
			$cal->deleteMonthlyEvent($_POST['id'], $_POST['level'], $sid);
		}
		else if($_POST['id']){
			$cal->modMonthlyDateEvent($_POST['id'], $_POST['date'], $startTime,
                        $endTime, $startDuration, $endDuration, $_POST['priority'], $_POST['note'], $_POST['level'], 
			$sid);
		}else{
			$cal->addMonthlyDateEvent($_POST['date'], $startTime, 
			$endTime, $startDuration, $endDuration, $_POST['priority'], $_POST['note'], $_POST['level'], 
			$sid); 
		}

		//redirect back to where they came from
		$back = -2;
		include("../include/back.php");
		exit;
	}
	//add/modify/delete monthly event with day occ
	else if( isset($_POST['dayofweek']) && $_POST['dayofweek'] && isset($_POST['dayocc']) && $_POST['dayocc'] )
	{
		$page->checkDay($_POST['dayofweek']);
		if($dayocc < 1 || $dayocc > 5)
		{
			error(9, "Incorrect occurence number");
		}

		if($_POST['delete']){
			$cal->deleteMonthlyEvent($_POST['id'], $_POST['level'], $sid);
		}
		else if($_POST['id']){
			$cal->modMonthlyDayEvent($_POST['id'], $_POST['dayofweek'], $_POST['dayocc'], 
                        $startTime, $endTime, $startDuration, $endDuration, $_POST['priority'], $_POST['note'], 
			$_POST['level'], $sid);
		}else{
			$cal->addMonthlyDayEvent($_POST['dayofweek'], $_POST['dayocc'], 
			$startTime, $endTime, $startDuration, $endDuration, $_POST['priority'], $_POST['note'], 
			$_POST['level'], $sid); 
		}
		//redirect back to where they came from
		$back = -2;
		include("../include/back.php");
		exit;
	}
	//add/modify/delete weekly event
	else if( isset($_POST['dayofweek']) && $_POST['dayofweek'] )
	{
		$page->checkDay($_POST['dayofweek']);
		
		if($_POST['delete']){
			$cal->deleteWeeklyEvent($_POST['id'], $_POST['level'], $sid);
		}
		else if($_POST['id']){
			$cal->modWeeklyEvent($_POST['id'], $_POST['dayofweek'], $startTime, $endTime,
                        $startDuration, $endDuration, $_POST['priority'], $_POST['note'], $_POST['level'], $sid);
		}else{
		$cal->addWeeklyEvent($_POST['dayofweek'], $startTime, $endTime, 
			$startDuration, $endDuration, $_POST['priority'], $_POST['note'], $_POST['level'], $sid); 
		}
		//redirect back to where they came from
		$back = -2;
		include("../include/back.php");
		exit;

	}
	else 
		error(2);
	
}
//if nothing submitted display pages for adding events, default to single event
else if($_GET['type'] == "month")
{	
	if(isset($_GET['id']))
		$page->displayModMonthlyEvent($_GET['id']);
	else
		$page->displayAddMonthlyEvent($_GET['date'], $_GET['level'], $_GET['sid']);
}
else if($_GET['type'] == "week")
{
	if(isset($_GET['id']))
		$page->displayModWeeklyEvent($_GET['id']);
	else
		$page->displayAddWeeklyEvent($_GET['date'], $_GET['level'], $_GET['sid']);
}
else
{
	if(isset($_GET['id']))
		$page->displayModSingleEvent($_GET['id']);
	else
		$page->displayAddSingleEvent($_GET['date'], $_GET['month'], $_GET['level'], $_GET['sid']);
}

?>
