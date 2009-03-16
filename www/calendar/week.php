<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once  $_SERVER['DOCUMENT_ROOT'] ."/../library/calendar.php";

$calendarHandler = new Calendar;

$this->setFile(TEMPLATE_content, 'calendar/week.html');
$this->setTab("calendar");

$this->setBlock(TEMPLATE_content, "week_block", "week_listing");
$this->setBlock(TEMPLATE_content, "day_block", "day_listing");
$this->setBlock("day_block", "note_block", "note_listing");	

list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->arg[0]);

$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
if ($selectedDate <= 0) 
	$selectedDate = time();

$selectedDay	= date("j", $selectedDate);
$selectedMonth 	= date("n", $selectedDate);
$selectedYear 	= date("Y", $selectedDate);

$this->setVar('SELECTED_DAY', $selectedDay);
$this->setVar('SELECTED_MONTH', $selectedMonth);
$this->setVar('SELECTED_YEAR', $selectedYear);

// setup movement buttons
$this->dropDown('month_drop', $calendarHandler->getMonthList(), $selectedMonth);
$this->dropDown('year_drop', $calendarHandler->getYearList(), $selectedYear);
$this->setVar('BACK', 	 date("j-n-Y", strtotime("-1 month", $selectedDate)));
$this->setVar('FORWARD', date("j-n-Y", strtotime("+1 month", $selectedDate)));

// output mini calendar
$firstDay 		= date("w", mktime(0,0,0,$selectedMonth, 1, $selectedYear));
$numDaysinMonth = date("t", $selectedDate); // date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $year)); 
$numWeeksMonth 	= ceil(($firstDay + $numDaysinMonth)/7);
$totalDays 		= $numWeeksMonth * 7 + (6 - $firstDay);

for($i = 0; $i < $totalDays; $i++) {
	$day = $i % 7;

	if($i >= $firstDay && $i < $numDaysinMonth+$firstDay) {
		$monthDay++; 	
		
		// use css to indicate current day
		if(date("d-m-Y", $selectedDate) == date("d-m-Y", mktime(0,0,0, $selectedMonth, $monthDay, $selectedYear)))
			$this->setVar("MONTABLE_CSS" . $day, "calTableSelected");
		else if(date("d-m-Y", mktime(0,0,0,$selectedMonth, $monthDay, $selectedYear)) == date("d-m-Y"))
			$this->setVar("MONTABLE_CSS" . $day, "calTableCurrent");
		else
			$this->setVar("MONTABLE_CSS" . $day, "calTable"); 
		
		$url = "/calendar/week/{$monthDay}-{$selectedMonth}-{$selectedYear}";				

		$daylink = $monthDay;
		$this->setVar("DAY".$day, $daylink);
		$this->setVar("DAYURL".$day, $url);

			
	} else {
	
		$this->setVar("DAY".$day, "&nbsp;");
		$this->setVar("MONTABLE_CSS".$day, "calTable"); 
	}
	if($day == 6)
		$this->parse("week_listing","week_block", true);
}

// render rest of page
$dayOfWeek 		= date("w", mktime(0,0,0, $selectedMonth, $selectedDay, $selectedYear));
$startOfWeek 	= mktime (0,0,0,$selectedMonth, ($selectedDay-$dayOfWeek), $selectedYear);
$endOfWeek 	 	= strtotime("+6 days", $startOfWeek);

if (date("m", $startOfWeek) != date("m", $endOfWeek))
	$this->setVar('MONTH', date("F",$startOfWeek)." / ".date("F", $endOfWeek));
else
	$this->setVar('MONTH', date("F",$startOfWeek));

$output = array('YEAR'	 => date("Y",$startOfWeek),
				'TITLE'	 => HEADING." - Calendar",
				'DAY'	 => $selectedDay,
				);
$this->setVar($output);

// week list
for($i=0; $i < 7; $i++) {

	$currentDay = strtotime("+$i day", $startOfWeek);

	$this->setVar("DATE", date("l jS", $currentDay));
	$this->setVar("ADD", "<a href=\"/calendar/modify.php?date=".date("d-m-Y", $currentDay)."\">add</a>");
	
	// output submission events
	$submissionList = $calendarHandler->getSubmissionEvents($currentDay);
	if (is_array($submissionList)) {
		foreach ($submissionList as $submission) {	
	
			$output = array('TIME'			=> $submission['dueTime'],
							'NOTE'			=> 'WORK DUE ' . $submission['category'] . (!empty($submission['synergyCode']) ? ' ('.$submission['synergyCode'].')' : '') . ' - ' . $submission['description'],
							'PRIORITY'		=> $calendarHandler->displayColour(1),
							'MODIFY'		=> "",
						  );					  
			
			$this->setVar($output);
			$this->parse("note_listing", "note_block", true);
			$subout = 1;
		}
	}


	if(SITE_location == "mgs" && SYNERGY_enable) {
		$birthdayList = $calendarHandler->getStudentBirthdays($currentDay);
		if(is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
			foreach($birthdayList as $birthday) {
		
				$output = array('TIME'		=> '',
								'NOTE'		=> 'Student Birthday - '.$birthday['name'].' in '.$birthday['form'].' turns '.$birthday['age'],
								'PRIORITY'	=> $calendarHandler->displayColour(3),
								'MODIFY'	=> "",
							  );					  
				$this->setVar($output);
				$this->parse("note_listing", "note_block", true);
			
				$subout = 1;
			}
		}
	}

	$eventList = $calendarHandler->getDayEvents($currentDay);
	
	if(is_array($eventList)) {
		foreach($eventList as $event) {
			$output = array('TIME'		=> rtrim(
											($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
										   	($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
										   "- "),
							'NOTE'		=> $event['note'],
							'PRIORITY'	=> $calendarHandler->displayColour($event['priority']),
							'MODIFY'	=> $calendarHandler->getPerm($event['id']) > 1 ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "\">Modify</a>" : "",
						  );	
			
			$this->setVar($output);
			$this->parse("note_listing", "note_block", true);
		}

	} else
		if(!$subout) $this->setVar("note_listing", "");
	
	$this->parse("day_listing", "day_block", true);
	$this->setVar("note_listing","");
}

?>