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

        function displayCalendar($currYear=0, $currMonth=0, $currDay=0)
        {
                if(!$currYear) { $this->currYear = date("Y"); } else { $this->currYear = $currYear; }
                if(!$currMonth) { $this->currMonth = date("n"); } else { $this->currMonth = $currMonth; }
                if(!$currDay) { $this->currDay = date("j"); } else { $this->currDay = $currDay; }

		$this->cal = new Calendar;

        }

        function outputEvent($block, $id, $startTime, $endTime, $priority, $note, $owner, $access, $sid, $type)
        {
		$startTime = strtolower($startTime);
		$endTime = strtolower($endTime);
		if($startTime == "0:00" || $startTime == "12:00am" || $startTime == "12:00 am")   
			$this->t_body->set_var("TIME", "");
		else if($endTime == "0:00" || $endTime == "12:00am" || $endTime == "12:00 am")
                	$this->t_body->set_var("TIME", $startTime);
                else
			$this->t_body->set_var("TIME", $startTime." - ".$endTime);
 
                $this->t_body->set_var("NOTE", htmlentities($note));
                $this->t_body->set_var("PRIORITY", $this->cal->displayColour($priority));


                if($owner == $_SESSION['id'] || $this->cal->checkSecurity($access, $sid) > 1 || $_SESSION['su'])
                        $this->t_body->set_var("MODIFY","<a href=\"addEvent.php?type=$type&id=$id\">Modify</a>");
                else
                        $this->t_body->set_var("MODIFY", ""); 

                $this->t_body->parse("note_listing".$block,"note_block".$block, true);
        }

	function displayMonth()
	{
		$this->t_body = new Template(TPL_directory);
		$this->t_body->set_file("tplBODY", "calendar/monthly.htm");

		$this->t_body->set_block("tplBODY", "week_block", "week_listing");
		$this->t_body->set_block("tplBODY", "note_block", "note_listing");

		include_once("../include/page.php");

		$nextMonth = mktime (0,0,0,$this->currMonth+1, $this->currDay, $this->currYear);
		$previousMonth = mktime (0,0,0,$this->currMonth-1, $this->currDay, $this->currYear); 
		$currentMonth = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);

		//intitalise real date for indication on table
		$realMonth = date("n");
		$realDay = date("j");
		$realYear = date("Y");

		//get amount of days in month
		$numDaysinMonth = date("t", $currentMonth);
		//get first day of month
		$firstDay = date("w", mktime(0,0,0,$this->currMonth, 1, $this->currYear));

		$numWeeksMonth = ceil(($firstDay + $numDaysinMonth)/7);
		$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);

		$this->t_body->set_var("MONTH", date("F", $currentMonth));
		$this->t_body->set_var("TITLE", HEADING." - Calendar for ".date("F",$currentMonth)." ".$this->currYear);
                $this->t_body->set_var("YEAR", date("Y",  $currentMonth));

                $this->t_body->set_var("BACK", "viewCalendar.php?Year=".date("Y",$previousMonth).
                                "&Month=".date("m",$previousMonth)."&Day=".date("d", $previousMonth)."&view=1");
                $this->t_body->set_var("FORWARD", "viewCalendar.php?Year=".date("Y",$nextMonth).
                                "&Month=".date("m",$nextMonth)."&Day=".date("d", $nextMonth)."&view=1");
		$this->t_body->set_var("VIEWLINK", "viewCalendar.php?Year=".date("Y",$currentMonth).
                                "&Month=".date("m",$currentMonth)."&Day=".date("d", $currentMonth));

		$monthDay = 1;

		for($i=0; $i < $totalDays; $i++)
		{
			$day = $i % 7;
		
			if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
			{

				//use css to indicate current day
				if($this->currDay == $monthDay)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableSelected");
				else if($realDay == $monthDay && $this->currMonth == $realMonth && $this->currYear == $realYear)
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTableCurrent");
				else
					$this->t_body->set_var("MONTABLE_CSS".$day, "calTable"); 
				
				$url = "viewCalendar.php?Year=".date("Y",$currentMonth).   
                                "&Month=".date("m",$currentMonth)."&Day=".$monthDay."&view=1";				

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

		
		$this->t_body->set_var("DATE", date("l jS", $currentMonth));
		
		$this->t_body->set_var("ADD", "<a href=\"addEvent.php?date=".$this->currDay."&month=$this->currMonth\">add</a>");

		//setup query for events
		$queryDate = date("Y-m-d", $currentMonth);
		
		//first lets output submission events
		$sub = $this->cal->getSubmissionEvents($this->currDay, $this->currMonth, $this->currYear);
		for($i=0; $sub['category'][$i]; $i++)
		{
			$this->outputEvent("", 0, 0, 0, 1, "*DUE WORK* ".$sub['category'][$i]." - ".$sub['name'][$i], 0,0,0,""); 
			$subout = 1;
		}

		$event = $this->cal->getAllDayEvents($this->currDay, $this->currMonth, $this->currYear);

		if(count($event['note']) < 1 && !$subout) {$this->t_body->set_var("note_listing", "");}
		else
		{
			for($i=0; $event['note'][$i]; $i++)
			{
	
				$this->outputEvent("", $event['id'][$i], $event['startTime'][$i], $event['endTime'][$i], 
				$event['priority'][$i], $event['note'][$i], $event['owner'][$i], 
				$event['access'][$i], $event['sid'][$i], $event['type'][$i]);
			}
		}

		$this->t_body->pparse("finaloutput", "tplBODY");
	}
  

        function displayWeek()
        {
                //output week to template
                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "calendar/week.htm");

                //setup blocks
                $this->t_body->set_block("tplBODY", "note_block1", "note_listing1");
                $this->t_body->set_block("tplBODY", "note_block2", "note_listing2");
                $this->t_body->set_block("tplBODY", "note_block3", "note_listing3");
                $this->t_body->set_block("tplBODY", "note_block4", "note_listing4");
                $this->t_body->set_block("tplBODY", "note_block5", "note_listing5");
                $this->t_body->set_block("tplBODY", "note_block6", "note_listing6");
                $this->t_body->set_block("tplBODY", "note_block7", "note_listing7");

		include_once("../include/page.php");

                //setup database
                $db = new dbManager;
                $db->dbConnect();

                //figure out which day of the week is the current day
                $dayOfWeek = date("w", mktime(0,0,0,$this->currMonth, $this->currDay, $this->currYear));
                //figure out which day from the current day is the start of the week
                $startOfWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-$dayOfWeek), $this->currYear);
                $endOfWeek = $startOfWeek + 518400; 

		if(date("F", $startOfWeek) != date("F", $endOfWeek))
                	$this->t_body->set_var("MONTH", date("F",$startOfWeek)." / ".date("F", $endOfWeek));
		else
                	$this->t_body->set_var("MONTH", date("F",$startOfWeek));

                $this->t_body->set_var("TITLE", HEADING." - Calendar for ".date("F",$startOfWeek)." ".$this->currYear);
                $this->t_body->set_var("YEAR", date("Y",$startOfWeek));


                $currentDay = $startOfWeek;

                //setup the movement buttons
                $previousWeek = mktime (0,0,0,$this->currMonth, ($this->currDay-7), $this->currYear);
                $nextWeek = mktime (0,0,0,$this->currMonth, ($this->currDay+7), $this->currYear);
                $currentWeek = mktime (0,0,0,$this->currMonth, $this->currDay, $this->currYear);

                $this->t_body->set_var("BACK", "viewCalendar.php?Year=".date("Y",$previousWeek).
                                "&Month=".date("m",$previousWeek)."&Day=".date("d", $previousWeek));
                $this->t_body->set_var("FORWARD", "viewCalendar.php?Year=".date("Y",$nextWeek).
                                "&Month=".date("m",$nextWeek)."&Day=".date("d", $nextWeek));
		$this->t_body->set_var("VIEWLINK", "viewCalendar.php?Year=".date("Y",$currentWeek ).
                                "&Month=".date("m",$currentWeek )."&Day=".date("d", $currentWeek )."&view=1" );


		//initialise the day values
                $this->currYear = date("Y",$currentDay);
                $this->currMonth = date("n",$currentDay);
                $this->currDay = date("j",$currentDay);


                //for each day of the week
                for($x=1; $x <= 7; $x++)
                {
                        $this->t_body->set_var("DATE".$x, date("l jS",$currentDay));

			$this->t_body->set_var("ADD".$x, "<a href=\"addEvent.php?date=".$this->currDay."&month=".$this->currMonth."\">add</a>");

			//first lets output submission events
			$sub = $this->cal->getSubmissionEvents($this->currDay, $this->currMonth, $this->currYear);
			for($i=0; $sub['category'][$i]; $i++)
			{
				$this->outputEvent($x, 0, 0, 0, 1, "*DUE WORK* ".$sub['category'][$i]." - ".$sub['name'][$i], 0,0,0); 
				$subout = 1;
			}

			$event = $this->cal->getAllDayEvents($this->currDay, $this->currMonth, $this->currYear);
			
			if(count($event['id']) < 1 && !$subout) {$this->t_body->set_var("note_listing".$x, "");}
                        else
                        {	
                                for($i=0; isset($event['id'][$i]); $i++)
                                {
					$this->outputEvent($x, $event['id'][$i], $event['startTime'][$i], $event['endTime'][$i], 
					$event['priority'][$i], $event['note'][$i], $event['owner'][$i], 
					$event['access'][$i], $event['sid'][$i], $event['type'][$i]);
                                }

                        }
                        //increment date
                        $currentDay = mktime (0,0,0,date("m",$currentDay), date("d",$currentDay)+1, date("Y",$currentDay));
                	$this->currYear = date("Y",$currentDay);
                	$this->currMonth = date("n",$currentDay);
                	$this->currDay = date("j",$currentDay);
		}


                $this->t_body->pparse("finaloutput", "tplBODY");
        }

}

$cal = new displayCalendar($_GET['Year'], $_GET['Month'], $_GET['Day']);


if($_GET['view'] == 'month')
{
	$cal->displayMonth();
}
else if($_GET['view'] == 'week')
{
	$cal->displayWeek();
}
else
{
	if(CALENDAR_default_view == 'month')
		$cal->displayMonth();
	else
		$cal->displayWeek();
}

?>
