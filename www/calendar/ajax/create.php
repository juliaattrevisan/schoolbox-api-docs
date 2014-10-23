<?php
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\UserMapper;

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$calendarHandler = new Calendar();
$page = new SitePage();

$groupHandler = new Group();

$user = $_SESSION['user'];

/* Superuser/Staff/Parent adding event for someone else or
 * someone creating their own event
 */
if ($groupHandler->isSuperuser() || $groupHandler->isStaff() || $groupHandler->isParentOfChild($_POST['userId']) || ($user->getId() == $_POST['userId'])) {
	$ownerId = $_POST['userId'];
	$owner   = UserMapper::getInstance()->createModel()->setId($ownerId);
}
/* You're not supposed to be able to create events under other circumstances
 */
else {
	error(19);
	exit;
}

$event = new Event();

$event->setOwner($owner)
	->setAuthor($user)
	->setAccess(Event::ACCESS_PERSONAL)
	->setCampusCollection(new Collection())
	->setRoleCollection(new Collection())
	->setType(EventTypeMapper::getInstance()->createModel()->setId($_POST['type']))
	->setTitle($_POST['note'])
	->setLocation($_POST['location'])
	->setAttendance($_POST['attendance'])
	->setDetail($_POST['detail'])
	->setPublishDateTime(null)
	->setCompleted(isset($_POST['completed']) ? $_POST['completed'] : false);
if ($_POST['allDay']) {
    $dateObj = new Alaress_Date_Format($_POST['date'], 'd-m-Y');
    $event
        ->setStartDateTime($dateObj)
        ->setEndDateTime($dateObj)
        ->setAllDay(true);
} else {
    $startDate = new Alaress_Date_Format($_POST['date'], 'd-m-Y H:i');
    $endDate   = new Alaress_Date_Format($_POST['date'], 'd-m-Y H:i');
    $endDate->addHours(1);
    $event
        ->setStartDateTime($startDate)
        ->setEndDateTime($endDate)
        ->setAllDay(false);
}

$affectedIds = EventMapper::getInstance()->save($event);

/* Save a Personal Relation
 */
$personalRelation = new \Schoolbox\Calendar\Relation\Personal();
$personalRelation->setEventId($event->getId());
$personalRelation->setUserId($ownerId);

$personalRelationMapper = new \Schoolbox\Calendar\Relation\PersonalMapper();
$personalRelationMapper->save($personalRelation);

// Get event back out of the connector, so I know the rendering is consistent
if (count($affectedIds) == 1) {
    ob_start();
    // No matter what I do to get the just posted event, it will be a crime
    // against humanity.
    // The only difference is where it happens.
    $connector = new Calendar_Api_EventType_Event_Schoolbox_Default($owner, null);
    $renderer  = new Calendar_Event_Type_Day_Renderer_Javascript(new SitePage());
    $events    = $connector->getEvents(
        intval($event->getStartDateTime()->getUnixTimestamp()),
        intval($event->getEndDateTime()->addDays(1)->getUnixTimestamp())
    );
    foreach ($events as $calendarEvent) {
        $renderer->visit($calendarEvent);
    }
    // Panel-beat Steve's calendar JSON into shape
    foreach (json_decode('['.ob_get_clean().'{}]') as $item) {
        if (property_exists($item, 'data')) {
            if (property_exists($item->data, 'meta')) {
                if (property_exists($item->data->meta, 'eventId')) {
                    if ($item->data->meta->eventId == $event->getId()) {
                        echo json_encode($item);
                        exit;
                    }
                }
            }
        }
    }
    echo '{}';
}
exit(1);
