<?php

require_once('../include/session.php');
require_once('../include/include.php');
require_once('../include/involved.php');
require_once('../include/syn_userinfo.php');
require_once('../include/syn_classlist.php');

class dueWorkPage 
{
	function dueWorkPage()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "submission/dueWork.htm");
		$this->t_body->set_var("TITLE", HEADING." - My Work");
		
		//due work
		$this->t_body->set_block("tplBODY", "user_block", "user_list");
		$this->t_body->set_block("tplBODY", "week_block", "week_list");
		$this->t_body->set_block("user_block", "user_heading", "user_heading_area");
		$this->t_body->set_block("user_block", "day_block", "day_list");
		$this->t_body->set_block("day_block", "due_heading_block", "due_heading_list"); 
		$this->t_body->set_block("day_block", "due_block", "due_list"); 
	
		//returned work
		$this->t_body->set_block("tplBODY", "returned_block", "returned_list");
		$this->t_body->set_block("returned_block", "ret_category_block", "ret_category_listing");
		$this->t_body->set_block("ret_category_block", "return_block", "return_list"); 
		
		include_once("../include/page.php");

		$this->db = new dbManager;

		$this->logPageView();
	}

	function displayDueWork($id = 0, $currYear=0, $currMonth=0, $currDay=0)
	{
	
		if(!$currYear) { $this->currYear = date("Y"); } else { $this->currYear = $currYear; }
		if(!$currMonth) { $this->currMonth = date("n"); } else { $this->currMonth = $currMonth; }
		if(!$currDay) { $this->currDay = date("j"); } else { $this->currDay = $currDay; }
	
		$this->listby = isset($_GET['listby']) ? $_GET['listby'] : 'all';
		if ($this->listby != 'week' && $this->listby != 'month' && $this->listby != 'all') {
			$this->listby = 'all';
		}
	
		if(isParent($_SESSION['gid']))
		{
			$this->outputCalendar();
			
			//if parent output childs work
			foreach($_SESSION['children'] as $child)
			{
				$flag=false;
				$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
				$user = $this->db->dbGetSingleRow($query);
				$this->t_body->set_var("USER_NAME", formatName($user));
				if($user['year'])
					$this->t_body->set_var("USER_YEAR", ', '.$user['year']);
				else
					$this->t_body->set_var("USER_YEAR", '');
				
				$this->t_body->parse("user_heading_area","user_heading");
				
				if($this->listby=='week')
					$this->outputWorkWeek($child['id'], $child['subjects']);
				else if($this->listby=='month')
					$this->outputWorkMonth($child['id'], $child['subjects']);
				else
					$this->outputWorkAll($child['id'], $child['subjects']);
				
			}
		}
		else if(is_numeric($id) && $id)
		{
			//if requested user check they are staff
			if(isStaff($_SESSION['gid']))
			{
				$this->outputCalendar();
				$query = "SELECT title, firstname, lastname, year, synergy_id, group_id FROM user WHERE id = $id";
				$user = $this->db->dbGetSingleRow($query);
				$this->t_body->set_var("USER_NAME", formatName($user));
				if($user['year'])
					$this->t_body->set_var("USER_YEAR", ', '.$user['year']);
				else
					$this->t_body->set_var("USER_YEAR", '');
				
				$this->t_body->parse("user_heading_area","user_heading");
				$subjects = getSubjectList($user['synergy_id'], $user['group_id']);
				
				if($this->listby=='week')
					$this->outputWorkWeek($id, $subjects);
				else if($this->listby=='month')
					$this->outputWorkMonth($id, $subjects);
				else
					$this->outputWorkAll($id, $subjects);
						
			}
			else
				error(19); //perm denied
		}
		else
		{
			$this->outputCalendar();
			$this->t_body->set_var('user_heading_area', '');
			//just output own due work
			if($this->listby=='week')
				$this->outputWorkWeek($_SESSION['id'], $_SESSION['subjects']);
			else if($this->listby=='month')
				$this->outputWorkMonth($_SESSION['id'], $_SESSION['subjects']);
			else
				$this->outputWorkAll($_SESSION['id'], $_SESSION['subjects']);
			
		}
	}

	function monthDropDown($selected)
	{
		$this->t_body->set_block("tplBODY", "month_drop", "month_drop_block");
		for($i=1; $i <= 12; $i++)
		{
			$this->t_body->set_var("DROP_MONTH_NUMBER", $i);
			$this->t_body->set_var("DROP_MONTH_NAME", date('F', mktime(0,0,0,$i,1)));
			if($i == $selected)
				$this->t_body->set_var("DROP_MONTH_SELECTED", "selected=\"selected\"");
			else
				$this->t_body->set_var("DROP_MONTH_SELECTED", "");
	
			$this->t_body->parse("month_drop_block", "month_drop",true);
		}
		
	}
	
	function yearDropDown($selected)
	{
		$this->t_body->set_block("tplBODY", "year_drop", "year_drop_block");
		for($i=date('Y')-2; $i <= date('Y')+1; $i++)
		{
			$this->t_body->set_var("DROP_YEAR_NUMBER", $i);
			if($i == $selected)
				$this->t_body->set_var("DROP_YEAR_SELECTED", "selected=\"selected\"");
			else
				$this->t_body->set_var("DROP_YEAR_SELECTED", "");
	
			$this->t_body->parse("year_drop_block", "year_drop",true);
		}
		
	}

	
	
	function outputCategories($id, $subjects, $day)
	{
	
			$this->t_body->set_var("due_list","");
	
			$flag = 0;
			$catList = involvedCategoryList($id, $subjects, 1);
			if(is_array($catList)) {
				foreach($catList as $cat)
				{
					if($this->outputCategoryWork($id, $cat['id'], $day))
						$flag=1;
				}
			}
			$this->t_body->set_var("due_heading_list","");
			
			if($flag) {
				$this->t_body->parse("due_heading_list","due_heading_block");
				return true;
			}
				
		return false;
			
	}
	
	
	function outputCategoryWork($id, $catId, $day)
	{
		$query = "
			SELECT submission_box.description, file.realname, file.id AS fid, file_type.name as type,
				DATE_FORMAT(submission_box.date, '%l:%i%p') AS date, 
				UNIX_TIMESTAMP(submission_box.date) AS overdue_date,
				UNIX_TIMESTAMP(submission_box.open_date) AS open_date, 
				submission_box.id, category.name, student_submission.owner, inline, category.code
			FROM (submission_box, category) 
				LEFT JOIN file ON submission_box.fid = file.id
				LEFT JOIN student_submission ON student_submission.box_id = submission_box.id AND student_submission.owner = $id
				LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
			 	LEFT JOIN file_type ON submission_box.type = file_type.id
			WHERE submission_box.category = category.id 
				AND submission_box.deleted = '0' AND category.id = '$catId' 
				AND submission_box.late_date > NOW()
				AND DATE_FORMAT(submission_box.date, '%d%m%Y') = '" . date('dmY', $day) . "'
			ORDER BY submission_box.date";
	
		$res = $this->db->dbDoQuery($query);

		$now = date("U");
		
		if($this->db->dbCountRows() > 0)
		{
			$completed = 0;
			$due = $this->db->dbCountRows($res);
			
			while($result = $this->db->dbGetNextArray($res) )
			{
			
				//if has not submitted response then output it
				if($result['owner'] == "")
				{
				
				
					if($result['overdue_date'] < $now)
						$this->t_body->set_var("COLOUR", "#FF0000");
					else
						$this->t_body->set_var("COLOUR", "#000000");
						
					$this->t_body->set_var("CATEGORY", $result['name']);
					$teacherID = getTeacherName($result['code']);
					if($teacherID[0]) {
						$query = "SELECT id, title, username, firstname, lastname, alt_email, local_email FROM user WHERE synergy_id = '{$teacherID[0]}'";
						$teacher=$this->db->dbGetSingleRow($query);
						$teacherName = formatName($teacher);
						$emailAddress = formatEmail($teacher);
						$email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$teacherName</a>";
						$this->t_body->set_var("TEACHER", $email);
					}
					else {
						$this->t_body->set_var("TEACHER", '');
						$this->t_body->set_var("TEACHER_EMAIL", '');
					}
				
					$this->t_body->set_var("ASSDESC", scrubTEXT($result['description']));
					$this->t_body->set_var("TYPE", scrubTEXT($result['type']));
					$divider = " / ";
					if($result['realname'] == "") {
						$this->t_body->set_var("VIEW", "");		
						$divider = "";
					} else
					{
						if($result['inline'] == 'true')
							$url = "<a href=\"/sendframe.php?id=".$result['fid']."\" target=\"_blank\">View</a>";
						else
							$url = "<a href=\"/send.php?id=".$result['fid']."&save=1\">View</a>";
						$this->t_body->set_var("VIEW", $url);
					}
					$this->t_body->set_var("DUEDATE", $result['date']);

					if($_SESSION['id'] == $id && $result['open_date'] < date('U') )
						$this->t_body->set_var("SUBMIT", $divider . "<a href=\"submitWork.php?box_id=".$result['id']."\">Submit</a>");
					else
						$this->t_body->set_var("SUBMIT", "");
				
					
					$this->t_body->parse("due_list","due_block",true);

				
				}
				else
					$completed++;
			}

			//if all due work not completed set havework flag and parse subject block
			if($due != $completed)
			{
				$this->havework = 1;
				return true;
			}
		} 
		return false;
	}

	function outputCalendar()
	{
		
		//intitalise real date for indication on table
		$realMonth = date("n");
		$realDay = date("j");
		$realYear = date("Y");


		
		//setup the movement buttons
		$nextMonth = mktime (0,0,0,$this->currMonth+1, $this->currDay, $this->currYear);
		$previousMonth = mktime (0,0,0,$this->currMonth-1, $this->currDay, $this->currYear); 
		$currentMonth = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);

		$this->t_body->set_var("BACK", "dueWork.php?Year=".date("Y",$previousMonth).
						"&Month=".date("m",$previousMonth)."&Day=".date("d", $previousMonth)."&listby=$this->listby" . (isset($_GET['id']) ? "&id=$id" : ""));
		$this->t_body->set_var("FORWARD", "dueWork.php?Year=".date("Y",$nextMonth).
						"&Month=".date("m",$nextMonth)."&Day=".date("d", $nextMonth)."&listby=$this->listby" . (isset($_GET['id']) ? "&id=$id" : ""));
		
		$this->yearDropDown($this->currYear);			
		$this->monthDropDown($this->currMonth);

					
		//get amount of days in month
		$this->numDaysinMonth = date("t", $currentMonth);
		
		//get first day of month
		$firstDay = date("w", mktime(0,0,0,$this->currMonth, 1, $this->currYear));

		$numWeeksMonth = ceil(($firstDay + $this->numDaysinMonth)/7);
		$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);
	
		//figure out which day of the week is the current day
		$dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
		//figure out which day from the current day is the start of the week
		$startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);
		$endOfWeek = $startOfWeek + 518400; 

		if(date("F", $startOfWeek) != date("F", $endOfWeek))
			$this->t_body->set_var("MONTH", date("F",$startOfWeek)." / ".date("F", $endOfWeek));
		else
			$this->t_body->set_var("MONTH", date("F",$startOfWeek));

		$this->t_body->set_var("YEAR", date("Y",$startOfWeek));
		$this->t_body->set_var("DAY", $this->currDay);

		//output the month calendar
		$monthDay = 1;
		$monthYear = date("Y",$currentMonth);
		$monthMonth = date("m",$currentMonth);
		

		for($i=0; $i < $totalDays; $i++)
		{
			$day = $i % 7;
		
			if($i >= $firstDay && $i < $this->numDaysinMonth+$firstDay)
			{

				//use css to indicate current day
				if($this->currDay == $monthDay)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableSelected");
				//else if (in_array($monthDay, $dueDates))
				//	$this->t_body->set_var("MONTABLE_CSS".$day, "calTableEntry");
				else if($realDay == $monthDay && $monthMonth == $realMonth && $monthYear == $realYear)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableCurrent");
				else
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTable"); 
				
				
				
				$url = "dueWork.php?Year=$monthYear&Month=$monthMonth&Day=$monthDay&listby={$this->listby}";		
				$url .= isset($_GET['id']) ? "&id=$id" : "";		

				$daylink = $monthDay;
				$this->t_body->set_var("DAY".$day, $daylink);
				$this->t_body->set_var("DAYURL".$day, $url);

				$monthDay++; 		
			}
			else
			{
				$this->t_body->set_var("DAY".$day, "&nbsp;");
				$this->t_body->set_var("MONTABLE_CSS".$day, "calTable"); 
			}
			if($day == 6)
				$this->t_body->parse("week_list","week_block", true);
		}

		$this->currentDay = $startOfWeek;

		$this->currentDay = $startOfWeek;
		
		//initialise the day values
		$this->currYear = date("Y",$this->currentDay);
		$this->currMonth = date("n",$this->currentDay);
		$this->currDay = date("j",$this->currentDay);
		
		
		
		$currentURL = "dueWork.php?Year={$this->currYear}&Month={$this->currMonth}&Day={$this->currDay}";
		
		$listByLink = "<a href='{$currentURL}&listby=all" . (isset($_GET['id']) ? "&id=$id" : "") ."'>All</a> | " .
 				  	  "<a href='{$currentURL}&listby=month" . (isset($_GET['id']) ? "&id=$id" : "") ."'>Month</a> | " .
				  	  "<a href='{$currentURL}&listby=week" . (isset($_GET['id']) ? "&id=$id" : "") ."'>Week</a>";
		$this->t_body->set_var('LISTBY', $listByLink);
		
	}
	
	function outputWorkWeek($id, $subjects) {
		
		$currentDay = $this->currentDay;
		$currYear = $this->currYear;
		$currMonth = $this->currMonth;
		$currDay = $this->currDay;
		//output work listing
		$this->t_body->set_var('day_list', '');
		
		for($j=0; $j < 7; $j++)
		{
			$this->t_body->set_var("DATE", date("l jS",$currentDay));
			
			if (isStaff($_SESSION['gid'])) {
				$this->t_body->set_var('ADD', "<a href=\"/submission/createSubmission.php?user=$id&Day=".$currDay.
										  "&Month=".$currMonth."&Year=".$currYear."\">add</a>");
			}
			else
				$this->t_body->set_var('ADD', '');
			
			$r = $this->outputCategories($id, $subjects, $currentDay);
			
			//increment date
			$currentDay = mktime (0,0,0,date("m",$currentDay), date("d",$currentDay)+1, date("Y",$currentDay));
			$currYear = date("Y",$this->currentDay);
			$currMonth = date("n",$this->currentDay);
			$currDay = date("j",$this->currentDay);
			
			if ($r)
				$this->t_body->parse("day_list","day_block", true);
			
		}
		
		if ($this->havework == 0) {
			$this->t_body->set_var("day_list", "<b>You have completed all work set via the portal for the selected week.</b>");	
		}
		
		$this->t_body->parse("user_list","user_block",true);
	}
	
	function outputWorkMonth($id, $subjects) {	
		
		$currYear = $this->currYear;
		$currMonth = $this->currMonth;
		$currDay = 1;
		$currentDay = mktime(0,0,0,$currMonth,$currDay,$currYear);
		
		//output work listing
		$this->t_body->set_var('day_list', '');
		
		for($j=1; $j <= $this->numDaysinMonth; $j++)
		{
			$this->t_body->set_var("DATE", date("l jS",$currentDay));
			
			
			if (isStaff($_SESSION['gid'])) {
			$this->t_body->set_var("ADD", "<a href=\"/submission/createSubmission.php?user=$id&Day=".$currDay.
										  "&Month=".$currMonth."&Year=".$currYear."\">add</a>");
			}
			else
				$this->t_body->set_var('ADD', '');
					
			$r = $this->outputCategories($id, $subjects, $currentDay);
			
			//increment date
			$currentDay = mktime (0,0,0,date("m",$currentDay), $j, date("Y",$currentDay));
			$currYear = date("Y",$currentDay);
			$currMonth = date("n",$currentDay);
			$currDay = date("j",$currentDay);
			
			if ($r)
				$this->t_body->parse("day_list","day_block", true);
			
		}
		
		if ($this->havework == 0) {
			$this->t_body->set_var("day_list", "<b>You have completed all work set via the portal for the selected month.</b>");	
		}
		
		$this->t_body->parse("user_list","user_block",true);
	}
	
	function outputWorkAll($id, $subjects) {

			
		// get all work due
		$query = "SELECT UNIX_TIMESTAMP(date) as day FROM (submission_box, category)
					WHERE submission_box.category = category.id 
					AND submission_box.deleted = '0' AND submission_box.late_date > NOW() ";
		$catList = involvedCategoryList($id, $subjects, 1);
		if(is_array($catList)) {
			$query .= " AND (1=2 ";
			foreach($catList as $cat) {
				if(is_numeric($cat['id'])) {
					$query .= "OR category.id = {$cat['id']} ";
				}
			}
				
			$query .= ")";
		}					
		
		$query .= "GROUP BY DATE_FORMAT(submission_box.date,'%d %b %Y') ORDER BY submission_box.date";	
		$res = $this->db->dbDoQuery($query);
		$dueDates = array();
		$this->t_body->set_var('day_list', '');
		
		while($data = $this->db->dbGetNextArray($res)) {
	
			$this->currentDay = $data['day'];

			$this->currYear = date("Y",$this->currentDay);
			$this->currMonth = date("n",$this->currentDay);
			$this->currDay = date("j",$this->currentDay);
			
			
			$this->t_body->set_var("DATE", date("l jS F, Y", $this->currentDay));
			
		
		
			if (isStaff($_SESSION['gid'])) {
			$this->t_body->set_var("ADD", "<a href=\"/submission/createSubmission.php?user=$id&Day=".$this->currDay.
										  "&Month=".$this->currMonth."&Year=".$this->currYear."\">add</a>");
			}
			else
				$this->t_body->set_var('ADD', '');
							
			$r = $this->outputCategories($id, $subjects, $this->currentDay);
			

	
			if ($r)
				$this->t_body->parse("day_list","day_block", true);
			
		}
		if ($this->havework == 0) {
			$this->t_body->set_var("day_list", "<b>You have completed all work set via the portal.</b>");	
		}			
		
		$this->t_body->parse("user_list","user_block",true);
	}
	

	function getMonthDueDates() {
	
		// get work due this month
		$query = "SELECT DISTINCT DATE_FORMAT(date, '%d') as day FROM (submission_box, category)
					WHERE submission_box.category = category.id 
					AND DATE_FORMAT(date, '%b%Y') = '" . date('MY', mktime(0,0,0,$this->currMonth,$this->currDay,$this->currYear)) . "' 
					AND submission_box.deleted = '0' AND submission_box.late_date > NOW() ";
		$catList = involvedCategoryList($id, $subjects, 1);
		if(is_array($catList)) {
			$query .= " AND (1=2 ";
			foreach($catList as $cat) {
				if(is_numeric($cat['id'])) {
					$query .= "OR category.id = {$cat['id']} ";
				}
			}
				
			$query .= ")";
		}					
		
		$r = $this->db->dbDoQuery($query);
		$dueDates = array();
		while($data = $this->db->dbGetNextArray($r)) {
			$dueDates[] = $data['day'];
		}
		
		return $dueDates;
		
	}			

	function getReturnedWork($id)
	{	
		//dont show returned work to people requesting to see this page.. only show returned work to themselves
		if(is_numeric($id) && $id > 0) {
			$this->t_body->set_var('returned_list', '');
			return;
		}
			 
		$query = "SELECT category.id as cid, category.name, submission_box.description, submission_return.mark, 
				submission_return.comment, GROUP_CONCAT(co.code) code, 
				submission_return.id, submission_return.filename, file_type.name as type 
				FROM (submission_return, category, submission_box)
				LEFT JOIN file ON file.id = submission_box.fid
				LEFT JOIN file_type ON submission_box.type = file_type.id
				LEFT JOIN categoryCode co ON co.categoryID = category.id
				WHERE submission_return.box_id = submission_box.id AND submission_box.category = category.id 
				AND submission_return.deleted = '0' 
				AND submission_return.owner = '".$_SESSION['id']."'
				GROUP BY submission_box.id, category.id
				ORDER BY cid, submission_return.id";
				
		$result = $this->db->dbDoQuery($query);

		if($this->db->dbCountRows() > 0)
		{
			while($return = $this->db->dbGetNextArray($result)) 
			{
				if(isset($prevCat['cid']) && $prevCat['cid'] != $return['cid'])
				{
					$teacherID = getTeacherName($prevCat['code']);
					if($teacherID[0]) {
						$query = "SELECT id, title, username, firstname, lastname, alt_email, local_email FROM user WHERE synergy_id = '{$teacherID[0]}'";
						$teacher=$this->db->dbGetSingleRow($query);
						$teacherName = formatName($teacher);
						$emailAddress = formatEmail($teacher);
						$email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$teacherName</a>";
						$this->t_body->set_var("TEACHER", $email);
					}
					else {
						$this->t_body->set_var("TEACHER", '');
						$this->t_body->set_var("TEACHER_EMAIL", '');
					}

					$this->t_body->set_var("CATEGORY", $prevCat['name']);
 					$this->t_body->parse("ret_category_listing", "ret_category_block", true);
					$this->t_body->set_var("return_list", "");
				}


				$this->t_body->set_var("ASSDESC", $return['description']);
				$this->t_body->set_var("TYPE", $return['type']);
				$this->t_body->set_var("MARK", $return['mark']);
				$this->t_body->set_var("COMMENT", $return['comment']);
				
				$this->t_body->set_var("DELETE", "dueWork.php?delete=".$return['id']);
				if($return['filename'] == "")
					$this->t_body->set_var("VIEW", "");
				else
					$this->t_body->set_var("VIEW", "<a href=\"dueWork.php?view={$return['id']}\">View</a>");
				
				$this->t_body->parse("return_list","return_block",true);
				
				$prevCat = $return;
			}


			$this->t_body->set_var("CATEGORY", $prevCat['name']);
			$teacherID = getTeacherName($prevCat['code']);
			if($teacherID[0]) {
				$query = "SELECT id, title, username, firstname, lastname, alt_email, local_email FROM user WHERE synergy_id = '{$teacherID[0]}'";
				$teacher=$this->db->dbGetSingleRow($query);
				$teacherName = formatName($teacher);
				$emailAddress = formatEmail($teacher);
				$email = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$teacherName</a>";
				$this->t_body->set_var("TEACHER", $email);
			}
			else {
				$this->t_body->set_var("TEACHER", '');
				$this->t_body->set_var("TEACHER_EMAIL", '');
			}
		
			$this->t_body->parse("ret_category_listing", "ret_category_block", true);

		}
		else
			$this->t_body->set_var("ret_category_listing", "<b>You currently have no returned work</b>");
		
		$this->t_body->parse('returned_list', 'returned_block');
	}		

	function deleteReturnedWork($id)
	{
		//mark work deleted but only when the work is owned by user
		$query = "UPDATE submission_return SET deleted = '1' WHERE id = '$id' AND owner = '".$_SESSION['id']."'";
		$this->db->dbDoQuery($query);
	}

	function viewReturnedWork($id, $save)
	{
		//sends students work to student only
		$query = "SELECT filename FROM submission_return WHERE owner = '".$_SESSION['id']."' AND id = '$id'";
		$this->db->dbDoQuery($query);
		
		if($this->db->dbCountRows() < 1) 
			error(5);
        
		$result = $this->db->dbGetNextArray();		
		if($save)
			$attach = "attachment;";
		else
			$attach = "";

		//all checks completed.. continue with file send
		$filename = RETURNS_directory . "/" . $id . ".dat";
		
		//send headers
		header ("Content-Type: application/octet-stream"); 
		header ("Content-Disposition: $attach filename=\"".$result['filename']."\""); 
		header ("Content-Length: ".filesize($filename));
		readfile($filename);
		exit;
	}

	function logPageView()
	{
		$testquery = "SELECT datetime FROM submission_log WHERE page = '1' 
		AND user = '".$_SESSION['id']."'";
		
		$this->db->dbDoQuery($testquery);

		if($row = $this->db->dbGetNextArray() )
		{
			$query = "UPDATE submission_log SET datetime = NOW() WHERE page = 
			'1' AND user = '".$_SESSION['id']."'";
		}
		else
		{
			$query = "INSERT INTO submission_log (datetime, user, page) VALUES ( NOW() ,
				'".$_SESSION['id']."', '1')";
		}
                
		$this->db->dbDoQuery($query);
	}

	function showPage()
	{
		$this->t_body->pparse("finaloutput", "tplBODY");
	}
}

$page = new dueWorkPage();

//if user requests to view send them the file
if(isset($_REQUEST['view']) && $_REQUEST['view'])
	$page->viewReturnedWork($_REQUEST['view'], $_REQUEST['save']);
//mark work deleted and then display
else if(isset($_REQUEST['delete']) && $_REQUEST['delete']) {
	$page->deleteReturnedWork($_REQUEST['delete']);
	localRedir('/submission/dueWork.php');
}

$page->displayDueWork($_GET['id'], $_GET['Year'], $_GET['Month'], $_GET['Day']);
$page->getReturnedWork($_GET['id']);
$page->showPage();
