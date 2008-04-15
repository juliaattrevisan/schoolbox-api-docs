<?php

require_once("calendar/calendar.php");

$this->setBlock($component['name'], "note_block", "note_listing");

$cal = new Calendar;

$currDay = date("d");
$currMonth = date("m");
$currYear = date("Y");

$this->setVar("DATE", date("l jS F"));

//first lets output submission events
$sub = $cal->getSubmissionEvents($currDay, $currMonth, $currYear);
for($i=0; $sub['category'][$i]; $i++)
{
	$this->setVar("TIME", $sub['dueTime'][$i]);
	if($sub['synergyCode'][$i])
		$descr = $sub['category'][$i]." (".$sub['synergyCode'][$i].") - ".$sub['description'][$i];
	else
		$descr = $sub['category'][$i]." - ".$sub['description'][$i];
		
	$this->setVar("NOTE", "WORK DUE ".$descr); 
	$this->setVar("NOTE_COLOUR", $cal->displayColour(1));
	
	$this->parse("note_listing","note_block", true);
	$subout = 1;
}

$eventList = $cal->getAllDayEvents($currDay, $currMonth, $currYear);

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
		
		$this->setVar("NOTE_COLOUR", $cal->displayColour($event['priority']));
		$this->setVar("NOTE", $event['note']);
		$this->parse("note_listing","note_block", true);

	}
}

$this->parse('POS'.$component['position'], $component['name']);
?>