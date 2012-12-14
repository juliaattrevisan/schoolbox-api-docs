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

/* If you are resizing an event in the week view the new start
 * and end times are expressed as a date string
 */
if (isset($_POST['resize']) && $_POST['resize']) {
	$startTime = new Alaress_Date_Format(strtotime($_POST['start']), 'U');
	$endTime = new Alaress_Date_Format(strtotime($_POST['end']), 'U');
}
/* Otherwise you are moving an event on the Month view, which makes
 * the new datetime transfer as a difference value -_-
 */
else {
	$dayDelta = $_POST['dayDelta'];
	$minuteDelta = $_POST['minuteDelta'];

	$startTime = new Alaress_Date_Format(strtotime($event['date'] . ' ' . $event['startTime']), 'U');
	$startTime->addDays($dayDelta);
	$startTime->addMinutes($minuteDelta);

	$endTime = new Alaress_Date_Format(strtotime($event['date'] . ' ' . $event['endTime']), 'U');
	$endTime->addDays($dayDelta);
	$endTime->addMinutes($minuteDelta);
}

/* Check maximum endtime so that it's always on the same day
 *
 * So if you move an 8 hour event from 10am to 11pm, make it
 * only last till 11:59:59 that day instead of spanning into
 * the next day since schoolbox treats event days individually
 */
if ($endTime->getDatabaseDate() != $startTime->getDatabaseDate()) {
	$endTime = new Alaress_Date_Format($event['date'] . ' 23:59:59', 'y-m-d h:i:s');
}

/* Update that event
 */
$updatedEvent = array();
$updatedEvent['id'] = $event['id'];
$updatedEvent['date'] = $startTime->getDatabaseDate();
$updatedEvent['startTime'] = $startTime->getDatabaseTime();
$updatedEvent['endTime'] = $endTime->getDatabaseTime();

/* For tasks
 */
if (isset($_POST['note']) && isset($_POST['detail'])) {
	$updatedEvent['note'] = $_POST['note'];
	$updatedEvent['detail'] = $_POST['detail'];
	$updatedEvent['completed'] = $_POST['completed'];
}
/* Otherwise maintain groupList and campusList for non-tasks
 */
else {
	$updatedEvent['groupList'] = $calendarHandler->getEventGroup($event['id']);
	$updatedEvent['campusList'] = $calendarHandler->getEventCampus($event['id']);
}

$calendarHandler->update($updatedEvent);
