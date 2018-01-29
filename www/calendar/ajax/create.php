<?php
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\UserMapper;
use Calendar\Event\Type\Day\Renderer\Javascript;
use Schoolbox\Permission\Access\Access as FolderAccess;

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

$calendarHandler = new Calendar();
$page = new SitePage();

$groupHandler = new Group();

$user = $_SESSION['user'];
$ownerId = $_POST['userId'];
$owner = UserMapper::getInstance()->findById($ownerId);

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

/* Check user has permission to create this event
 */
$eventAccess = new Access(new FolderAccess());
if ($eventAccess->checkDestination($user, Event::ACCESS_PERSONAL, $user, $owner) == false) {
    error(35);
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
    $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Default::class);
    $connector      = new Calendar_Api_EventType_Event_Schoolbox_Default($calendarConfig->getId(), $owner);

    foreach ($connector->getEvent($event->getId()) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        /** @var \Schoolbox\User $user */
        $user     = $_SESSION['user'];
        $renderer = new Javascript(new SitePage(), $calendarEvent, $user, new \Group(), new \Calendar(), $user);
        $output   = $renderer->render();
        echo substr($output,0,-1);
        break;
    }
}
exit(1);
