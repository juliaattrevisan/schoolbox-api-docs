<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

require_once "include/permission.php";
require_once "../library/group.php";
require_once "../library/calendar.php";

$calendarHandler = new Calendar;
$groupHandler = new Group;

$this->setFile(TEMPLATE_content, 'calendar/month.html');
$this->setTab("calendar");

$this->setBlock(TEMPLATE_content, "month_week", "month_week_listing");
$this->setBlock("month_week", "day_block", "day_listing");
$this->setBlock("day_block", "note_block", "note_listing");
$this->setBlock("note_block", "time", "time_block");

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
$this->checkList('group', $groupHandler->involvedCategoryList($_SESSION['id']),$groupHandler->involvedCategoryList($_SESSION['id']), TEMPLATE_content, array('name'=>'GROUP_NAME','id'=>'GROUP_ID'));
$this->checkList('campus', $calendarHandler->getCampusList(),$_SESSION['campus']);
$this->checkList('event_type', $calendarHandler->getTypeList(), '', TEMPLATE_content, array('eventType'=>'EVENT_TYPE_NAME','eventTypeID'=>'EVENT_TYPE_ID', 'eventColour'=>'EVENT_TYPE_COLOUR') );
$this->setVar('BACK', 	 date("j-n-Y", strtotime("-1 month", $selectedDate)));
$this->setVar('FORWARD', date("j-n-Y", strtotime("+1 month", $selectedDate)));

// output calendar
$firstDay 		= date("w", mktime(0,0,0,$selectedMonth, 1, $selectedYear));
//$numDaysinMonth = date("t", $selectedMonth);
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $year)); // 
$numWeeksMonth 	= ceil(($firstDay + $numDaysinMonth)/7);
$totalDays 		= $numWeeksMonth * 7 + (6 - $firstDay);

// render rest of page
$dayOfWeek 		= date("w", mktime(0,0,0, $selectedMonth, $selectedDay, $selectedYear));
$startOfWeek 	= mktime (0,0,0,$selectedMonth, ($selectedDay-$dayOfWeek), $selectedYear);
$endOfWeek 	 	= strtotime("+6 days", $startOfWeek);


$this->setVar('MONTH', date("F", mktime(0,0,0,$selectedMonth, 1, $selectedYear)));

$output = array('YEAR'	 => date("Y",$startOfWeek),
				'TITLE'	 => HEADING." - Calendar",
				'DAY'	 => $selectedDay,
				);
$this->setVar($output);

// get top left starting day of calendar view

$startDay = mktime(3,0,0,$selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);

for ($i = 0; $i < $totalDays; $i++) {
	$currentDay = strtotime("+$i day", $startDay);
	
	$day = $i % 7;	
	
	if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
		$this->setVar('OTHER_MONTH', '');
	else
		$this->setVar('OTHER_MONTH', ' otherMonth');

	$output = array("DATE"		=> date("jS", $currentDay),
					"ADD"		=> "<a href=\"/calendar/modify.php?date=".date("d-m-Y", $currentDay)."&returnTo=month\">add</a>",
					"ON_OFF"	=> !($day%2) ? 'dark' : '',
					);

    if(date("d-m-Y", $selectedDate) == date("d-m-Y", $currentDay))
        $this->setVar("MONTABLE_CSS", "calTableSelected");
    else if(date("d-m-Y", $currentDay) == date("d-m-Y"))
        $this->setVar("MONTABLE_CSS", "calTableCurrent");
    else
        $this->setVar("MONTABLE_CSS", "calTable");

	$this->setVar($output);
	
	
	$submissionList = $calendarHandler->getSubmissionEvents($currentDay);
	if (is_array($submissionList)) {
		foreach ($submissionList as $submission) {	
	
			$output = array(
                            'TIME'        => $submission['dueTime'],
                            'NOTE'        => 'WORK DUE ' . $submission['category'] . (!empty($submission['synergyCode']) ? ' (' . $submission['synergyCode'] . ')' : '') . ' - ' . $submission['description'],
                            'COLOUR'      => $calendarHandler->displayColour(4),
                            'MODIFY'      => '<a class="btn go-to float-left" href="/submission/submitWork/' . $submission['id'] . '"><span></span>Submit</a>',
                            'LOCATION'    => '',
                            'DETAIL'      => '',
                            'EVENT_TYPE'  => 'submission',
                            'TYPE'        => 'Due Work',
                            'AUTHOR'      => formatName($submission),
                            'AUTHOR_ID'   => $submission['owner'],
                            'EVENT_LEVEL' => '',
                        );

            $this->setVar($output);
            $this->parse('time_block','time');
			$this->parse("note_listing", "note_block", true);
			$subout = 1;
		}
	}


	if(Cfg::get('site_location') == 'mgs' && Cfg::get('external_enable') && $groupHandler->isStaff($_SESSION['id'])) {
		$birthdayList = $calendarHandler->getStudentBirthdays($currentDay);
		if(is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
			foreach($birthdayList as $birthday) {
		
				$output = array('TIME'		=> '',
								'NOTE'		=> 'Student Birthday - '.$birthday['name'].' in '.$birthday['form'].' turns '.$birthday['age'],
                                'LOCATION'	=> '',
                                'DETAIL'    => '',
								'COLOUR'	=> $calendarHandler->displayColour(3),
								'MODIFY'	=> '',
                                'TYPE'      => 'Birthday',
                                'AUTHOR'    => '',
                    );
				$this->setVar($output);
                $this->setVar('time_block','');
				$this->parse("note_listing", "note_block", true);
			
				$subout = 1;
			}
		}
	}

	$eventList = $calendarHandler->getDayEvents($currentDay);
	if(is_array($eventList)) {
		foreach($eventList as $event) {

			$output = array('TIME' => rtrim(
                                    ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                                    ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                                    "- "),
                            'NOTE' => scrubTEXT($event['note']),
                            'LOCATION' => scrubTEXT($event['location']),
                            'DETAIL' => scrubTEXT($event['detail']),
                            'COLOUR' => '#' . $event['eventColour'],
                            'MODIFY'      => $calendarHandler->getPerm($event['id']) > 1 ? '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $event['id'] . '&returnTo=month"><span></span>Modify</a>' : '',
                            'EVENT_TYPE' => 'type' . $event['type'],
                            'TYPE' => $event['eventType'],
                            'AUTHOR' => formatName($event),
                        );

            if($event['access'] == 3 ) {
                if(stristr($event['campusList'], ',' )) {
                    foreach(explode(',',$event['campusList']) as $campus) {
                        $output['EVENT_LEVEL'] .= ' campus'.$campus;
                    }
                }
                else
                    $output['EVENT_LEVEL'] .= ' campus'.$event['campusList'];
            }
            else if($event['access'] == 2)
                $output['EVENT_LEVEL'] = 'group'.$event['sid'];
            else
                $output['EVENT_LEVEL'] = 'personal';


			$this->setVar($output);
            if($output['TIME'])
               $this->parse('time_block','time');
            else
               $this->setVar('time_block','');
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
