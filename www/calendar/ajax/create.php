<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$calendarHandler = new Calendar();
$page = new SitePage();

/* Javascript returns date as milliseconds from 1/1/1970
 * When creating via the js calendar ui, assume single-day events so
 * startDate and endDate are the same.
 */
$dateObj      = new Alaress_Date_Format($_POST['date']/1000, 'U');
$publish_date = new Alaress_Date_Format(new DateTime(), 'U');

$event = array(
	'owner' => $_SESSION['id'],
	'access' => 1,
	'sid' => $_SESSION['id'],
	'campusList' => array(),
	'groupList' => array(),
	'type' => $_POST['type'],
	'note' => $_POST['note'],
	'startDate' => $dateObj->getDatabaseDate(),
	'startTime' => '09:00:00',
	'endDate' => $dateObj->getDatabaseDate(),
	'endTime' => '10:00:00',
	'location' => $_POST['location'],
	'attendance' => $_POST['attendance'],
	'detail' => $_POST['detail'],
	'publish_date' => $publish_date->getDatabaseDate(),
	'completed' => $_POST['completed'],
);

if ($eventId = $calendarHandler->create($event)) {
	$json =  array(
		'title' => $event['note'],
		'start' => strtotime($event['startDate'] . ' ' . $event['startTime']),

		/* Whether you can edit this event or not
		 */
		'editable' => true,

		/* Whether it's an all-day event or not
		 * If allDay is true, then you can't resize it
		 *
		 * Schoolbox usage would be that all events are not
		 * allDay events
		 */
		'allDay' => false,

		/* Colour of the event
		 */
		'color' => '#aaaaaa',

		/* Extra data that the Event Object doesn't directly support
		 */
		'data' => array(
			'meta' => array(
				'eventId' => $eventId,
				'detail' => $event['detail'],
				'location' => $event['location'],
				'eventType' => '',
				'eventTypeId' => $event['type'],
				'type' => $event['type'],
				'author' => '',
				'authorId' => $event['owner'],
				'level' => '',
				'completed' => $event['completed'],
			),
			'links' => array(
				'category' => array(
					'id' => '',
					'name' => '',
				)
			),
			'styles' => array(
				'visible' => '',
			),
			'attendance' => array(
				'icon' => false,
				'status' => false,
				'requestButton' => false,
				'listButton' => false,
			)
		)
	);

	echo json_encode($json);
}

exit(1);
