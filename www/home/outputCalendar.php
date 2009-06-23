<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/calendar.php';

$this->setBlock($component['name'], "note_block", "note_listing");

$calendarHandler = new Calendar;

$currDay = time();

$this->setVar("DATE", date("l jS F"));

//first lets output submission events
$submissionList = $calendarHandler->getSubmissionEvents($currDay);
if (is_array($submissionList)) {
	foreach ($submissionList as $submission) {
	
		$output = array('TIME'				=> $submission['dueTime'],
						'NOTE_CATEGORY'		=> 'WORK DUE ' . $submission['category'] . (isset($submission['synergyCode']) ? ' ('.$submission['synergyCode'].')' : '') . ' - ',
						'NOTE_DESCRIPTION'	=> $submission['description'],
						'NOTE_COLOUR'		=> $calendarHandler->displayColour(1),
						);
			
		$this->setVar($output); 
		
		$this->parse("note_listing","note_block", true);
		$subout = 1;
	}
}
	

$eventList = $calendarHandler->getDayEvents($currDay);

if(!is_array($eventList))
{
	if(!$subout) $this->setVar("note_listing", "");
}
else
{
	foreach($eventList as $event)
	{
		if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
				$this->setVar("TIME", "");
		else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
				$this->setVar("TIME", $event['startTime']);
		else
				$this->setVar("TIME", $event['startTime']." - ".$event['endTime']);
		
		$output = array('NOTE_CATEGORY'		=> '',
						'NOTE_DESCRIPTION'	=> $event['note'],
						'NOTE_COLOUR'		=> $calendarHandler->displayColour($event['type']),
						);
		
		$this->setVar($output);
		$this->setVar('ON_OFF', ++$j%2? '' : 'dark'); 
		$this->parse("note_listing","note_block", true);

	}
}

$this->parse('POS'.$component['position'], $component['name']);
?>