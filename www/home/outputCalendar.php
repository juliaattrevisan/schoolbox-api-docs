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
	
        $output = array('TIME'			=> $submission['dueTime'],
                        'NOTE'			=> 'WORK DUE ' . $submission['category'] . (!empty($submission['synergyCode']) ? ' ('.$submission['synergyCode'].')' : '') . ' - ' . $submission['name'],
                        'COLOUR'		=> $calendarHandler->displayColour(4),
                        'MODIFY'		=> '',
                        'LOCATION'		=> '',
                        'DETAIL'        => '',
                        'EVENT_TYPE'    => 'submission',
                        'TYPE'          => 'Due Work',
                        'AUTHOR'        => formatName($submission),
                        'AUTHOR_ID'     => $submission['owner'],
                        'EVENT_LEVEL'   => '',
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
		
        $output = array('TIME'		=> rtrim(
                                        ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                                           ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                                       "- "),
                        'NOTE'		=> scrubTEXT($event['note']),
                        'LOCATION'	=> scrubTEXT($event['location']),
                        'DETAIL'	=> scrubTEXT($event['detail']),
                        'COLOUR'	=> '#'.$event['eventColour'],
                        'MODIFY'	=> $calendarHandler->getPerm($event['id']) > 1 ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=month\" title=\"Modify\">Modify</a>" : '',
                        'EVENT_TYPE'    => 'type'.$event['type'],
                        'TYPE'          => $event['eventType'],
                        'AUTHOR'        => formatName($event),
                        'AUTHOR_ID'        => $event['owner'],
                      );
		
		$this->setVar($output);
		$this->parse("note_listing","note_block", true);

	}
}

$this->parse('POS'.$component['position'], $component['name']);
?>