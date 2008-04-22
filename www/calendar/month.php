<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once "include/permission.php";
require_once "../library/calendar.php";

$calendarHandler = new Calendar;

$this->setFile(TEMPLATE_content, 'calendar/month.html');

$this->setBlock(TEMPLATE_content, "month_week", "month_week_listing");
$this->setBlock("month_week", "day_block", "day_listing");
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

// output calendar
$firstDay 		= date("w", mktime(0,0,0,$selectedMonth, 1, $selectedYear));
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $year)); // date("t", $selectedMonth);
$numWeeksMonth 	= ceil(($firstDay + $numDaysinMonth)/7);
$totalDays 		= $numWeeksMonth * 7 + (6 - $firstDay);

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

// get top left starting day of calendar view
$startDay = mktime(0,0,0,$selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);

for ($i = 0; $i < $totalDays; $i++) {
	$currentDay = strtotime("+$i day", $startDay);
	
	$day = $i % 7;	
	
	if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
		$this->setVar('OTHER_MONTH', '');
	else
		$this->setVar('OTHER_MONTH', ' otherMonth');

	$this->setVar("DATE", date("jS", $currentDay));
	$this->setVar("ADD", "<a href=\"/calendar/modify/".date("d-m-Y", $currentDay)."/month\">add</a>");
	
	
	$sub = $calendarHandler->getSubmissionEvents($this->currDay, $this->currMonth, $this->currYear);
	// output submission events
	for($j=0; $sub[$j]; $j++) {
	
		if($sub[$i]['synergyCode'][$j])
			$descr = $sub[$j]['category']." (".$sub[$j]['synergyCode'].") - ".$sub[$j]['description'];
		else
			$descr = $sub[$j]['category']." - ".$sub[$j]['description'];
		
		$output = array('TIME'		=> $sub[$j]['dueTime'],
						'NOTE'		=> "WORK DUE ".$descr,
						'PRIORITY'	=> $calendarHandler->displayColour(1),
						'MODIFY'	=> "",
					  );					  
		
		$this->setVar($output);
		$this->parse("note_listing", "note_block", true);
		$subout = 1;
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

	$eventList = $calendarHandler->getAllDayEvents($currentDay);
	if(is_array($eventList)) {
		foreach($eventList as $event) {
			$output = array('TIME'		=> rtrim(
											($event['startTime'] != "0:00" ? $event['startTime'] : "") . " - " .
										   	($event['endTime'] != "0:00" ? $event['endTime'] : ""),
										   "- "),
							'NOTE'		=> $event['note'],
							'PRIORITY'	=> $calendarHandler->displayColour($event['priority']),
							'MODIFY'	=> $_SESSION['su'] || 
										   $event['owner'] == $_SESSION['id'] || 
										   $this->checkSecurity($event['access'], $event['sid']) > 1 ? "<a href=\"/calendar/modify/" . $event['id'] . "/month\">Modify</a>" : "",
						  );	
			
			$this->setVar($output);
			$this->parse("note_listing", "note_block", true);
		}

	} else
		if(!$subout) $this->setVar("note_listing", "");
	
	$this->parse('day_listing', 'day_block', true);
	$this->setVar('note_listing', '');
	
	if ($day == 6 && $i) {
		$this->parse('month_week_listing', 'month_week', true);
		$this->setVar('day_listing', '');
	}
}



?>