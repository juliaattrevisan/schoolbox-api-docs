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

$event = $calendarHandler->getByID($_POST['eventId']);
//
// All day events: assume they start at 12am, and go for 2 hours for movement
// purposes (because that's what they look like in FullCalendar when dragged)
if ($event['allday']) {
	$event['startTime'] = '00:00:00';
	$event['endTime']   = '02:00:00';
}

/* If you are resizing an event in the week view the new start
 * and end times are expressed as a date string
 */
if (isset($_POST['resize']) && $_POST['resize']) {
	$startTime = new Alaress_Date_Format(strtotime($_POST['start']), 'U');
	$endTime   = new Alaress_Date_Format(strtotime($_POST['end']),   'U');
}
/* Otherwise you are moving an event on the Month view, which makes
 * the new datetime transfer as a difference value -_-
 */
else {
	$dayDelta = $_POST['dayDelta'];
	$minuteDelta = $_POST['minuteDelta'];

	$startTime = new Alaress_Date_Format(strtotime($event['startDate'] . ' ' . $event['startTime']), 'U');
	$startTime->addDays($dayDelta);
	$startTime->addMinutes($minuteDelta);

	$endTime = new Alaress_Date_Format(strtotime($event['endDate'] . ' ' . $event['endTime']), 'U');
	$endTime->addDays($dayDelta);
	$endTime->addMinutes($minuteDelta);
}

/* Update that event
 */
$updatedEvent = array();
$updatedEvent['id']        = $event['id'];
$updatedEvent['owner']     = $event['owner'];
$updatedEvent['access']    = $event['access'];
$updatedEvent['startDate'] = $startTime->getDatabaseDate();
$updatedEvent['startTime'] = $startTime->getDatabaseTime();
$updatedEvent['endDate']   = $endTime->getDatabaseDate();
$updatedEvent['endTime']   = $endTime->getDatabaseTime();
if (isset($_POST['allDay'])) {
	//
	// String 'false' is not and never will be a boolean! FFS
	$updatedEvent['allday'] = ($_POST['allDay'] !== 'false');
}
else {
	$updatedEvent['allday'] = $event['allday'];
}
if ($updatedEvent['allday']) {
	$updatedEvent['startTime'] = '00:00:00';
	$updatedEvent['endTime']   = '00:00:00';
}

/* For tasks
 */
if (isset($_POST['note']) && isset($_POST['detail'])) {
	$updatedEvent['note'] = $_POST['note'];
	$updatedEvent['detail'] = $_POST['detail'];
	$updatedEvent['completed'] = $_POST['completed'];
}
/* If checkbox on the calendar is interacted, note and detail won't be passed
 */
elseif(isset($_POST['completed'])) {
    $updatedEvent['completed'] = $_POST['completed'];
}
/* Otherwise maintain groupList and campusList for non-tasks
 */
else {
	$updatedEvent['groupList'] = $calendarHandler->getEventGroup($event['id']);
	$updatedEvent['campusList'] = $calendarHandler->getEventCampus($event['id']);
}

if($eventId = $calendarHandler->update($updatedEvent)) {
    $event = $calendarHandler->getByID($eventId);

    $json = array(
        'title' => $event['note'],
        'start' => strtotime($event['startDate'] . ' ' . $event['startTime']),

        /* Whether you can edit this event or not
           */
        'editable' => true,

        'allDay' => $updatedEvent['allday'],

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