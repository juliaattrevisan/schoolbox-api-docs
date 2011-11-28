<?php
//event output
$this->setBlock($componentHandle, "group_events", "group_events_block");

require_once SITE_root.'/library/calendar.php';
$calendarHandler = new Calendar;

$eventList = $calendarHandler->getAllInCategory($categoryID, $config);
$this->setVar('CALENDAR_TITLE', 'Group Calendar');
$this->setVar('group_events_block', '');

if (is_array($eventList)) {
	$userHandler = new User();

	foreach ($eventList as $event) {

		$event['colour'] = $calendarHandler->displayColour($event['type']);

		$calendarDate = new Alaress_Date_Format(strtotime($event['outputDate']), 'U');

        $output = array('CAL_TIME'		=> rtrim(
                                        ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                                           ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                                       "- "),
                        'CAL_DATE'	=> $calendarDate->getDateOnly(),
                        'CAL_ID'	=> $event['id'],
                        'CAL_NOTE'	=> Scrub::text($event['note']),
                        'CAL_LOCATION'	=> Scrub::text($event['location']),
                        'CAL_DETAIL'	=> Scrub::text($event['detail']),
                        'CAL_COLOUR'	=> $event['colour'],
                        'CAL_MODIFY'	=> $calendarHandler->getPerm($event['id']) > 1 ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=month\" title=\"Modify\">Modify</a>" : '',
                        'CAL_TYPE'      => $event['eventType'],
                        'CAL_AUTHOR'    => $userHandler->formatName($event),
                        'CAL_AUTHOR_ID' => $event['owner'],
                        'CAL_ID'	    => $event['id'],
                        'ON_OFF'	    => ++$j%2? '' : 'dark',
                      );
		$this->setVar($output);

		if($event['startTimeString'] == "12:00AM" || $event['startTimeString'] == "")
				$this->setVar("CAL_TIME", "");
		else if($event['endTimeString'] == "12:00AM")
				$this->setVar("CAL_TIME", $event['startTimeString']);
		else
				$this->setVar("CAL_TIME", $event['startTimeString']." till ".$event['endTimeString']);

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
