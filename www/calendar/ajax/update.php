<?php

use Calendar\Event\Type\Day\Renderer\Javascript;


/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

use Schoolbox\UserMapper;

$calendarHandler = new Calendar();
$page            = new SitePage();

$event = $calendarHandler->getByID($_POST['eventId']);
//
// All day events: assume they start at 12am, and go for 2 hours for movement
// purposes (because that's what they look like in FullCalendar when dragged)
if ($event['allday']) {
    $event['startTime'] = '00:00:00';
    $event['endTime']   = '02:00:00';
}

if (isset($_POST['resize']) && $_POST['resize']) {
    /* If you are resizing an event in the week view the new start
     * and end times are expressed as a date string
     */
    $startTime = new Alaress_Date_Format(strtotime($_POST['start']), 'U');
    $endTime   = new Alaress_Date_Format(strtotime($_POST['end']), 'U');
} else {
    /* Otherwise you are moving an event on the Month view, which makes
     * the new datetime transfer as a difference value -_-
     */
//    $dayDelta    = $_POST['dayDelta'];
    $minuteDelta = $_POST['minuteDelta'];

    $startTime = new Alaress_Date_Format(strtotime($event['start_datetime']), 'U');
//    $startTime->addDays($dayDelta);
    $startTime->addMinutes($minuteDelta);

    $endTime = new Alaress_Date_Format(strtotime($event['end_datetime']), 'U');
//    $endTime->addDays($dayDelta);
    $endTime->addMinutes($minuteDelta);
}

/* Update that event
 */
$updatedEvent              = array();
$updatedEvent['id']        = $event['id'];
$updatedEvent['owner']     = $event['owner'];
$updatedEvent['author_id'] = $event['author_id'];
$updatedEvent['access']    = $event['access'];
$updatedEvent['start_datetime'] = $startTime->getDatabase();
$updatedEvent['end_datetime']   = $endTime->getDatabase();

if (isset($_POST['allDay'])) {
    //
    // String 'false' is not and never will be a boolean! FFS
    $updatedEvent['allday'] = ($_POST['allDay'] !== 'false');
} else {
    $updatedEvent['allday'] = $event['allday'];
}
if ($updatedEvent['allday']) {
    $updatedEvent['start_datetime'] = $startTime->getDatabaseDate().' 00:00:00';
    $updatedEvent['end_datetime']   = $endTime->getDatabase().' 00:00:00';
}

if (isset($_POST['title']) && isset($_POST['detail'])) {
    /* For tasks
     */
    $updatedEvent['title']     = $_POST['title'];
    $updatedEvent['detail']    = $_POST['detail'];
    $updatedEvent['completed'] = $_POST['completed'];
} elseif (isset($_POST['completed'])) {
    /* If checkbox on the calendar is interacted, note and detail won't be passed
     */
    $updatedEvent['completed'] = $_POST['completed'];
} else {
    /* Otherwise maintain groupList and campusList for non-tasks
     */
    $updatedEvent['groupList']  = $calendarHandler->getEventGroup($event['id']);
    $updatedEvent['campusList'] = $calendarHandler->getEventCampus($event['id']);
}

// Get event back out of the connector, so I know the rendering is consistent
$owner = UserMapper::getInstance()->createModel()->setId($event['owner']);
if ($eventId = $calendarHandler->update($updatedEvent)) {
    $connector = new Calendar_Api_EventType_Event_Schoolbox_Default($owner, null);

    foreach ($connector->getEvent($eventId) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        $renderer = new Javascript(new SitePage(), $calendarEvent, $_SESSION['user'], new \Group, new \Calendar);
        $output   = $renderer->render();
        echo substr($output, 0, -1);
        break;
    }
}
exit(1);
