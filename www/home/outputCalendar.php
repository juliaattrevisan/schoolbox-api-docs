<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/calendar.php';

$this->setBlock($component['name'], "note_block", "note_listing");

$this->setVar(array(
	'note_listing' => ''
));

$calendarHandler = new Calendar();

$currDay = time();

$this->setVar("DATE", date("l jS F"));

//first lets output submission events
$submissionList = $calendarHandler->getSubmissionEvents($currDay);
$userHandler = new User();

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
                        'AUTHOR'        => $userHandler->formatName($submission),
                        'AUTHOR_ID'     => $submission['owner'],
                        'EVENT_LEVEL'   => '',
                      );

		$this->setVar($output);
		$this->parse("note_listing","note_block", true);
		$subout = 1;
	}
}


$eventList = $calendarHandler->getDayEvents($currDay);
$iterator = $eventList->createIterator();

for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
	$currentEvent = $iterator->current();

	if ($currentEvent->getStartTime() == "0:00" || strtolower($currentEvent->getStartTime()) == "12:00am" || strtolower($currentEvent->getStartTime()) == "12:00 am" || $currentEvent->getStartTime() == '') {
		$this->setVar("TIME", "");
	}
	else if ($currentEvent->getEndTime() == "0:00" || strtolower($currentEvent->getEndTime()) == "12:00am" || strtolower($currentEvent->getEndTime()) == "12:00 am" || $currentEvent->getEndTime() == '') {
		$this->setVar("TIME", $currentEvent->getStartTime());
	}
	else {
		$this->setVar("TIME", $currentEvent->getStartTime()." - ".$currentEvent->getEndTime());
	}

	$this->setVar(array(
		'TIME' => $currentEvent->getDerivedTime(),
		'NOTE' => $currentEvent->getNote(),
		'LOCATION' => $currentEvent->getLocation(),
		'DETAIL' => $currentEvent->getDetail(),
		'COLOUR' => $currentEvent->getColour(),
		'EVENT_TYPE' => 'type' . $currentEvent->getType(),
		'TYPE' => $currentEvent->getEventType(),
		'AUTHOR' => $currentEvent->getAuthor(),
		'AUTHOR_ID' => $currentEvent->getAuthorId(),
	));

	$this->setVar(array(
		'MODIFY' => $calendarHandler->getPerm($event['id']) > 1 ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=month\" title=\"Modify\">Modify</a>" : '',
	));

	$this->parse("note_listing", "note_block", true);
}

$this->parse('POS'.$component['position'], $component['name']);
