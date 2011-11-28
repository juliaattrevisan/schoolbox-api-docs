<?php
/* Output a campus calendar */
$this->setBlock($componentHandle, "group_events", "group_events_block");

require_once SITE_root.'/library/calendar.php';
$calendarHandler = new Calendar;

//event limit changed from posts to days
if($config['size'] != 0 && $config['size'] != '')
		$where = " AND date < ADDDATE(CURDATE(), INTERVAL {$config['size']} DAY) ";
else
		$where = "";

$this->setVar('CALENDAR_TITLE', 'Campus Calendar');

$query = "SELECT event.id, UNIX_TIMESTAMP(date) as outputDate, owner,
			TIME_FORMAT(startTime, '".Cfg::get('calendar_time_format')."') as startTimeString, TIME_FORMAT(endTime,
			'".Cfg::get('calendar_time_format')."') as endTimeString,priority, note, location, detail,
			user.id as uid, title, firstname, lastname,
			eventType, eventColour
		FROM event
		    LEFT JOIN user ON owner = user.id
		    LEFT JOIN luEventType ON type = eventTypeID
			JOIN eventCampus ON event.id = eventCampus.eventID
			JOIN eventGroup ON event.id = eventGroup.eventID
		WHERE access = 3
			AND eventCampus.campusID IN(".implode(",", $_SESSION['campus']).")
			AND eventGroup.groupID = {$_SESSION['gid']}
			AND ( date >= CURRENT_DATE() $where)
		ORDER BY date, startTime";

$eventList = $this->db->getAll($query);

$this->setVar('group_events_block', '');

if (is_array($eventList)) {
	$userHandler = new DeprecatedUser();

	foreach ($eventList as $event) {

        if($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '')
                $this->setVar("TIME", "");
        else if($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '')
                $this->setVar("TIME", $event['startTime']);
        else
                $this->setVar("TIME", $event['startTime']." - ".$event['endTime']);

		$event['colour'] = $calendarHandler->displayColour($event['type']);

		$calendarDate = new Alaress_Date_Format($event['outputDate'], 'U');

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
if($permission < WRITE_ACCESS)
	$this->setVar("submit_event_block", "");
else
	$this->parse("submit_event_block", "submit_event");

?>