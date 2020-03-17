<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Event\Type\Day\Renderer\Javascript;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$calendarHandler = new Calendar();

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
    $startTime = Alaress_Date_Format::newFromTimestamp(strtotime($_POST['start']));
    $endTime   = Alaress_Date_Format::newFromTimestamp(strtotime($_POST['end']));
    // Is the resized event an all-day event?
    // Set its end time back one day to account for FullCalendar treating end
    // dates as exclusive
    // (and thus reporting the end day one day after its appears to end)
    if ($event['allday']) {
        $startTime = $startTime->startOfDay();
        $endTime   = $endTime->addDays(-1)->startOfDay();
    }

} else {
    /* Otherwise you are moving an event on the Month view, which makes
     * the new datetime transfer as a difference value -_-
     */
    $minuteDelta = $_POST['minuteDelta'];

    $startTime = Alaress_Date_Format::newFromTimestamp(strtotime($event['start_datetime']));
    $startTime->addMinutes($minuteDelta);

    $endTime = Alaress_Date_Format::newFromTimestamp(strtotime($event['end_datetime']));
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
    $updatedEvent['end_datetime']   = $endTime->getDatabaseDate().' 00:00:00';
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
$owner = UserMapper::getInstance()->findByIdWithFallback($event['owner']);
if ($eventId = $calendarHandler->update($updatedEvent)) {
    $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Default::class);
    $connector      = new Calendar_Api_EventType_Event_Schoolbox_Default($calendarConfig->getId(), $owner, $_SESSION['user']);

    foreach ($connector->getEvent($eventId) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        $renderer = new Javascript($kernel->getDependencyInjector(), $calendarEvent, $_SESSION['user'], $_SESSION['user']);
        $output   = $renderer->render();
        echo $output;
        break;
    }
}
exit(1);
