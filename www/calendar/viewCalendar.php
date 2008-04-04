<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//calendar object.. controls calendar events and display

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/permission.php");
require_once("calendar.php");

class displayCalendar
{

	function displayCalendar($currYear=0, $currMonth=0, $currDay=0, $campus, $listBy = 'week')
	{
	
		$this->currYear = !$currYear ? date("Y") : $currYear;
		$this->currMonth = !$currMonth ? date("n") : $currMonth;
		$this->currDay = !$currDay ? date("j") : $currDay;
		
		$this->campus = $campus > 0 ? (int)$campus : 0;
		$this->listBy = $listBy == "month" ? $listBy : "week";
		
		$this->cal = new Calendar;
		$this->db = new dbManager;
	
	}

	function outputEvent($id, $startTime, $endTime, $priority, $note, $owner, $access, $sid, $type)
	{
		$startTime = strtolower($startTime);
		$endTime = strtolower($endTime);
		if($startTime == "0:00" || $startTime == "12:00am" || $startTime == "12:00 am" || $startTime == '')   
			$this->t_body->set_var("TIME", "");
		else if($endTime == "0:00" || $endTime == "12:00am" || $endTime == "12:00 am" || $endTime == '')
			$this->t_body->set_var("TIME", $startTime);
		else
			$this->t_body->set_var("TIME", $startTime." - ".$endTime);
		
		$this->t_body->set_var("NOTE", scrubTEXT($note));
		$this->t_body->set_var("PRIORITY", $this->cal->displayColour($priority));
		
		if($id && ($_SESSION['su'] || $owner == $_SESSION['id'] || $this->cal->checkSecurity($access, $sid) > 1))
			$this->t_body->set_var("MODIFY","<a href=\"addEvent.php?type=$type&id=$id\">Modify</a>");
		else
			$this->t_body->set_var("MODIFY", ""); 
		
		$this->t_body->parse("note_listing","note_block", true);
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
	
	function campusDropDown($selected)
	{
		$this->t_body->set_block("tplBODY", "campus_drop", "campus_drop_block");
		$query = "SELECT id, name FROM luCampus";
		$this->db->dbDoQuery($query);
		
		$this->t_body->set_var("DROP_CAMPUS_ID", 0);
		$this->t_body->set_var("DROP_CAMPUS_NAME", 'Show All');
		$this->t_body->parse("campus_drop_block", "campus_drop",true);
		
		while($campus = $this->db->dbGetNextArray() )
		{
			$this->t_body->set_var("DROP_CAMPUS_ID", $campus['id']);
			$this->t_body->set_var("DROP_CAMPUS_NAME", $campus['name']);
			if($campus['id'] == $selected)
				$this->t_body->set_var("DROP_CAMPUS_SELECTED", "selected=\"selected\"");
			else
				$this->t_body->set_var("DROP_CAMPUS_SELECTED", "");
	
			$this->t_body->parse("campus_drop_block", "campus_drop",true);
		}
	}
	
	function outputPage() {

		$this->t_body = new Template(TPL_directory);
	
		switch ($this->listBy) {
		
			case "month":
			
				$this->t_body->set_file("tplBODY", "calendar/month.htm");
				$this->t_body->set_block("tplBODY", "week_block", "week_listing");
				$this->t_body->set_block("tplBODY", "month_week", "month_week_listing");
				$this->t_body->set_block("month_week", "day_block", "day_listing");
				$this->t_body->set_block("day_block", "note_block", "note_listing");					
								
				include_once("../include/page.php");
				
				$this->outputCalendar();
				$this->outputMonthView();
				
				break;
			
			default:
			
				$this->t_body->set_file("tplBODY", "calendar/week.htm");
				$this->t_body->set_block("tplBODY", "week_block", "week_listing");
				$this->t_body->set_block("tplBODY", "day_block", "day_listing");
				$this->t_body->set_block("day_block", "note_block", "note_listing");	
				
				include_once("../include/page.php");
				
				$this->outputCalendar();
				$this->outputWeekView();
		}
		
		$this->t_body->pparse("finaloutput", "tplBODY");
	
	}
	
  
	function outputCalendar()
	{

		$currentURL = "/calendar/viewCalendar.php?Year=" . $this->currYear . "&Month=" . $this->currMonth . "&Day=" . $this->currDay;
		
		$this->t_body->set_var('LISTBY', '<a href="'. $currentURL.'&listby=week">Week</a> | 
										  <a href="'. $currentURL.'&listby=month">Month</a>');
		
		//intitalise real date for indication on table
		$realMonth = date("n");
		$realDay = date("j");
		$realYear = date("Y");
		
		//setup the movement buttons
		$nextMonth = mktime (0,0,0,$this->currMonth+1, $this->currDay, $this->currYear);
		$previousMonth = mktime (0,0,0,$this->currMonth-1, $this->currDay, $this->currYear); 
		$currentMonth = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);

		$this->t_body->set_var("BACK", "viewCalendar.php?Year=".date("Y",$previousMonth).
						"&Month=".date("m",$previousMonth)."&Day=".date("d", $previousMonth));
		$this->t_body->set_var("FORWARD", "viewCalendar.php?Year=".date("Y",$nextMonth).
						"&Month=".date("m",$nextMonth)."&Day=".date("d", $nextMonth));
		
		$this->yearDropDown($this->currYear);			
		$this->monthDropDown($this->currMonth);
		$this->campusDropDown($this->campus);
						
		//get amount of days in month
		$numDaysinMonth = date("t", $currentMonth);
		
		//get first day of month
		$firstDay = date("w", mktime(0,0,0,$this->currMonth, 1, $this->currYear));

		$numWeeksMonth = ceil(($firstDay + $numDaysinMonth)/7);
		$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);
	
		//figure out which day of the week is the current day
		$dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
		//figure out which day from the current day is the start of the week
		$startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);
		$endOfWeek = $startOfWeek + 518400; 

		if(date("F", $startOfWeek) != date("F", $endOfWeek)) {
			if ($this->listBy=='month')
				$this->t_body->set_var("MONTH", date("F",$endOfWeek));
			else
				$this->t_body->set_var("MONTH", date("F",$startOfWeek)." / ".date("F", $endOfWeek));
			
		} else
			$this->t_body->set_var("MONTH", date("F",$startOfWeek));

		$this->t_body->set_var("TITLE", HEADING." - Calendar");
		$this->t_body->set_var("YEAR", date("Y",$startOfWeek));
		$this->t_body->set_var("DAY", $this->currDay);
		
		//output the month calendar
		$monthDay = 1;
		$monthYear = date("Y",$currentMonth);
		$monthMonth = date("m",$currentMonth);
		
		for($i=0; $i < $totalDays; $i++)
		{
			$day = $i % 7;
		
			if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
			{

				//use css to indicate current day
				if($this->currDay == $monthDay)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableSelected");
				else if($realDay == $monthDay && $monthMonth == $realMonth && $monthYear == $realYear)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableCurrent");
				else
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTable"); 
				
				$url = "viewCalendar.php?Year=$monthYear&Month=$monthMonth&Day=$monthDay";				

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
				$this->t_body->parse("week_listing","week_block", true);
		}
		
	}
	
	function outputWeekView () {


		//figure out which day of the week is the current day
		$dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
		//figure out which day from the current day is the start of the week
		$startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);

		$this->currentDay = $startOfWeek;
	
		//initialise the day values
		$this->currYear = date("Y",$this->currentDay);
		$this->currMonth = date("n",$this->currentDay);
		$this->currDay = date("j",$this->currentDay);


		$sub = $this->cal->getWeekSubmissionEvents($this->currDay, $this->currMonth, $this->currYear);
		
		//output week listing
		for($x=0; $x < 7; $x++)
		{
			$this->t_body->set_var("DATE", date("l jS",$this->currentDay));
			
			$this->t_body->set_var("ADD", "<a href=\"addEvent.php?date=".$this->currDay."&month=".$this->currMonth."&year=".$this->currYear."\">add</a>");
			
			//first lets output submission events
			
			for($i=0; $sub[$x]['category'][$i]; $i++)
			{
				if($sub[$x]['synergyCode'][$i])
					$descr = $sub[$x]['category'][$i]." (".$sub[$x]['synergyCode'][$i].") - ".$sub[$x]['description'][$i];
				else
					$descr = $sub[$x]['category'][$i]." - ".$sub[$x]['description'][$i];
					
				$this->outputEvent(0, $sub[$x]['dueTime'][$i], '', 1, "WORK DUE ".$descr, 0,0,0,0); 
				$subout = 1;
			}

			if(SITE_location == "mgs" && SYNERGY_enable)
			{
				$birthdayList = $this->cal->getStudentBirthdays($this->currDay, $this->currMonth, $this->currYear);
				if(in_array(3 ,$_SESSION['campus']) && is_array($birthdayList))
				{
					foreach($birthdayList as $bDay)
					{
						$this->outputEvent(0, '', '', 3, 'Student Birthday - '.$bDay['name'].' in '.$bDay['form'].' turns '.$bDay['age']
							,0,0,0,0);
						$subout = 1;
					}
				}
			}

			$eventList = $this->cal->getAllDayEvents($this->currDay, $this->currMonth, $this->currYear, $this->campus);
			
			if(!is_array($eventList))
			{
				if(!$subout) $this->t_body->set_var("note_listing", "");
			}
			else
			{	
				foreach($eventList as $event)
				{
					$this->outputEvent($event['id'], $event['startTime'], $event['endTime'], 
						$event['priority'], $event['note'], $event['owner'], 
						$event['access'], $event['sid'], $event['type']);
				}

			}
			$this->t_body->parse("day_listing","day_block", true);
			$this->t_body->set_var("note_listing", "");
			
			//increment date
			$this->currentDay = mktime (0,0,0,date("m",$this->currentDay), date("d",$this->currentDay)+1, date("Y",$this->currentDay));
			$this->currYear = date("Y",$this->currentDay);
			$this->currMonth = date("n",$this->currentDay);
			$this->currDay = date("j",$this->currentDay);
		}
	
	}

	function outputMonthView () {

		//get amount of days in month
		$numDaysinMonth = date("t", mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear));
		
		//get first day of month
		$firstDay = date("w", mktime(0,0,0,$this->currMonth, 1, $this->currYear));
		
		// get top left starting day of calendar view
		$startDay = mktime(0,0,0,$this->currMonth, 1, $this->currYear) - strtotime("+$firstDay day", 0);

		$numWeeksMonth = ceil(($firstDay + $numDaysinMonth)/7);
		$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);	

		for ($i = 0; $i < $totalDays; $i++) {
			
			$thisDay = strtotime("+$i day", $startDay);
			
			$day = $i % 7;	
			
			if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
				$this->t_body->set_var('OTHER_MONTH', '');
			else
				$this->t_body->set_var('OTHER_MONTH', ' otherMonth');
				
			$this->currDay = date("j", $thisDay);
			$this->currMonth = date("n", $thisDay);
			$this->currYear = date("Y", $thisDay);
			
			$this->t_body->set_var('DATE', date("jS", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear)));
			
			$this->t_body->set_var("ADD", "<a href=\"addEvent.php?date=".$this->currDay."&month=".$this->currMonth."&year=".$this->currYear."\">add</a>");
			
			
			$sub = $this->cal->getWeekSubmissionEvents($this->currDay, $this->currMonth, $this->currYear);
			for($k=0; $sub[$x]['category'][$i]; $k++)
			{
				if($sub[$x]['synergyCode'][$k])
					$descr = $sub[$x]['category'][$k]." (".$sub[$x]['synergyCode'][$k].") - ".$sub[$x]['description'][$k];
				else
					$descr = $sub[$x]['category'][$k]." - ".$sub[$x]['description'][$k];
					
				$this->outputEvent(0, $sub[$x]['dueTime'][$k], '', 1, "WORK DUE ".$descr, 0,0,0,0); 
				$subout = 1;
			}

			if(SITE_location == "mgs" && SYNERGY_enable)
			{
				$birthdayList = $this->cal->getStudentBirthdays($this->currDay, $this->currMonth, $this->currYear);
				if(in_array(3 ,$_SESSION['campus']) && is_array($birthdayList))
				{
					foreach($birthdayList as $bDay)
					{
						$this->outputEvent(0, '', '', 3, 'Student Birthday - '.$bDay['name'].' in '.$bDay['form'].' turns '.$bDay['age']
							,0,0,0,0);
						$subout = 1;
					}
				}
			}

			$eventList = $this->cal->getAllDayEvents($this->currDay, $this->currMonth, $this->currYear, $this->campus);
			
			if(!is_array($eventList))
			{
				if(!$subout) $this->t_body->set_var("note_listing", "");
			}
			else
			{	
				foreach($eventList as $event)
				{
					$this->outputEvent($event['id'], $event['startTime'], $event['endTime'], 
						$event['priority'], $event['note'], $event['owner'], 
						$event['access'], $event['sid'], $event['type']);
				}

			}
			
			$this->t_body->parse('day_listing', 'day_block', true);
			$this->t_body->set_var('note_listing', '');
			
			if ($day == 6 && $i) {
				$this->t_body->parse('month_week_listing', 'month_week', true);
				$this->t_body->set_var('day_listing', '');
			}
		}	
	}
}

$cal = new displayCalendar($_GET['Year'], $_GET['Month'], $_GET['Day'], $_GET['campus'], $_GET['listby']);

$cal->outputPage();

?>
