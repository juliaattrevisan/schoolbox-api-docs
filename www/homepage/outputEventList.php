<?php
//Reset ON_OFF
$j =0;

//event output
$this->setBlock($componentHandle, "group_events", "group_events_block");

require_once '../library/calendar.php';
$calendarHandler = new Calendar;

$eventList = $calendarHandler->getAllInCategory($categoryID, $config);

if (is_array($eventList)) {
	foreach ($eventList as $event) {

		$event['colour'] = $calendarHandler->displayColour($event['priority']);

		$this->setVar("CAL_DATE", $event['outputDate']);
		$this->setVar("CAL_YEAR", $event['year']);
		$this->setVar("CAL_MONTH", $event['month']);
		$this->setVar("CAL_DAY", $event['day']);
		$this->setVar('ON_OFF', $j++%2? '' : 'dark');
		if($event['startTimeString'] == "0:00" || $event['startTimeString'] == "")
				$this->setVar("CAL_TIME", "");
		else if($event['endTimeString'] == "0:00")
				$this->setVar("CAL_TIME", $event['startTimeString']);
		else
				$this->setVar("CAL_TIME", $event['startTimeString']." - ".$event['endTimeString']);

		$this->setVar("CAL_COLOUR",$event['colour']);
		
		$this->setVar("CAL_NOTE", nl2br(scrubTEXT($event['note'])));

		$this->parse("group_events_block", "group_events", true);

	}
} else
	$this->setVar("group_events_block", '');


//setup block for submission button
$this->setBlock($componentHandle, "submit_event", "submit_event_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_event_block", "");
else
			$this->parse("submit_event_block", "submit_event");	
		
?>