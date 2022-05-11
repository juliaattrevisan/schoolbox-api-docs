<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\Calendar\Event\Attendance;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Config\Loader;
use Schoolbox\UserMapper;
use Calendar\Event\Type\Day\Renderer\Javascript;
use Schoolbox\Permission\Access\Access as FolderAccess;

/** @var SitePage|null $this */
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}

$user    = $_SESSION['user'];
$ownerId = $_POST['userId'];
$owner   = UserMapper::getInstance()->findById($ownerId);
$config  = new Loader();

$event = new Event();

if (is_numeric($_POST['type'])) {
    $type = EventTypeMapper::getInstance()->createModel()->setId($_POST['type']);
} else {
    $type = EventTypeMapper::getInstance()->findAll()->first();
}

/* Persist event attendances
 * If event has zero guests, or if guests weren't specified at all, just provide empty array
 * If you can't add attendees because you're a student and the config var is off, just provide empty array
 */
$guests = [];

if ((new Attendance\ValidCanInvite())->isSatisfiedBy($user, $config->get('calendar_student_can_invite'))) {
    $guests = (!isset($_POST['guests']) || '' === (string)$_POST['guests']) ? [] : explode(',', $_POST['guests']);
}

$event
    ->setOwner($owner)
    ->setAuthor($user)
    ->setAccess(Event::ACCESS_PERSONAL)
    ->setCampusCollection(new Collection())
    ->setRoleCollection(new Collection())
    ->setType($type)
    ->setVariant($_POST['variant'] ?? Event::VARIANT_EVENT)
    ->setTitle($_POST['title'] ?? '')
    ->setLocation($_POST['location'] ?? '')
    ->setAttendance(\count($guests) ? Event\Attendance\Model::LEVEL_INVITE_ONLY : Event\Attendance\Model::LEVEL_NO_ATTENDANCE)
    ->setDetail($_POST['detail'] ?? '')
    ->setPublishDateTime(null)
    ->setCompleted(isset($_POST['completed']) ? $_POST['completed'] : false);

/* Give all-day events a time, so that update.php can calculate the minuteDelta appropriately
 * without adding a 12-hour offset
 */
if ($_POST['allDay']) {
    $dateObj = new Alaress_Date_Format($_POST['date'] . ' 00:00', 'd-m-Y H:i');
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
$eventAccess = new Access(FolderAccess::getInstance());
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

(new Attendance\Transactor(Attendance\Mapper::getInstance(), UserMapper::getInstance()))->update($event, $guests);

// Get event back out of the connector, so I know the rendering is consistent
if (count($affectedIds) == 1) {
    $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Default::class);
    $connector      = new Calendar_Api_EventType_Event_Schoolbox_Default($calendarConfig->getId(), $owner, $user);

    foreach ($connector->getEvent($event->getId()) as $calendarEvent) {
        // Only one event should ever be gotten here, so render that one only
        /** @var \Schoolbox\User $user */
        $user     = $_SESSION['user'];
        $renderer = new Javascript(
            $this->getDi()->get('authorizer'),
            $this->getDi()->get('router'),
            $calendarEvent,
            $user,
            $user
        );
        $output   = $renderer->render();
        echo $output;
        break;
    }
}
exit(1);
