<?php
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\UserMapper;
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
	->setTitle($_POST['title'])
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
    $connector = new Calendar_Api_EventType_Event_Schoolbox_Default($owner, null);

    foreach ($connector->getEvent($event->getId()) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        $renderer = new Javascript(new SitePage(), $calendarEvent, $_SESSION['user'], new \Group, new \Calendar);
        $output   = $renderer->render();
        echo substr($output,0,-1);
        break;
    }
}
exit(1);
