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

$dayDelta = $_POST['dayDelta'];
$minuteDelta = $_POST['minuteDelta'];

$event = $calendarHandler->getByID($_POST['eventId']);

$startTime = new Alaress_Date_Format(strtotime($event['date'] . ' ' . $event['startTime']), 'U');
$startTime->addDays($dayDelta);
$startTime->addMinutes($minuteDelta);

$endTime = new Alaress_Date_Format(strtotime($event['date'] . ' ' . $event['endTime']), 'U');
$endTime->addDays($dayDelta);
$endTime->addMinutes($minuteDelta);

/* Check maximum endtime so that it's always on the same day
 *
 * So if you move an 8 hour event from 10am to 11pm, make it
 * only last till 11:59:59 that day instead of spanning into
 * the next day since schoolbox treats event days individually
 */
print_r($startTime->getDatabase() . ' ' . $endTime->getDatabase());

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

$calendarHandler->update($updatedEvent);
