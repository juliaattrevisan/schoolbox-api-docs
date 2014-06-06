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

/* Javascript returns date as milliseconds from 1/1/1970
 * When creating via the js calendar ui, assume single-day events so
 * startDate and endDate are the same.
 */
$dateObj      = new Alaress_Date_Format($_POST['date']/1000, 'U');
$publish_date = new Alaress_Date_Format(new DateTime(), 'U');

$groupHandler = new Group();

$user = $_SESSION['user'];

/* Superuser/Staff/Parent adding event for someone else or
 * someone creating their own event
 */
if ($groupHandler->isSuperuser() || $groupHandler->isStaff() || $groupHandler->isParentOfChild($_POST['userId']) || ($user->getId() == $_POST['userId'])) {
	$ownerId = $_POST['userId'];
}
/* You're not supposed to be able to create events under other circumstances
 */
else {
	error(19);
	exit;
}

$event = new Event();

$event->setOwner(UserMapper::getInstance()->createModel()->setId($ownerId))
	  ->setAuthor($user)
	  ->setAccess(Event::ACCESS_PERSONAL)
	  ->setServiceId($ownerId)
	  ->setCampusCollection(new Collection())
	  ->setRoleCollection(new Collection())
	  ->setType(EventTypeMapper::getInstance()->createModel()->setId($_POST['type']))
	  ->setNote($_POST['note'])
	  ->setStartDateTime($dateObj)
	  ->setEndDateTime($dateObj)
	  ->setAllDay(true)
	  ->setLocation($_POST['location'])
	  ->setAttendance($_POST['attendance'])
	  ->setDetail($_POST['detail'])
	  ->setPublishDateTime($publish_date)
	  ->setCompleted(isset($_POST['completed']) ? $_POST['completed'] : false);

$affectedIds = EventMapper::getInstance()->save($event);

if (count($affectedIds) == 1) {
	$json = array(
		'title' => $event->getNote(),
		'start' => $event->getStartDateTime()->getUnixTimestamp(),

		/* Whether you can edit this event or not
		 */
		'editable' => true,

		/* Whether it's an all-day event or not
		 * If allDay is true, then you can't resize it
		 */
		'allDay' => $event->getAllDay(),

		/* Colour of the event
		 */
		'color' => '#aaaaaa',

		/* Extra data that the Event Object doesn't directly support
		 */
		'data' => array(
			'meta' => array(
				'eventId'       => $affectedIds[0],
				'detail'        => $event->getDetail(),
				'location'      => $event->getLocation(),
				'eventType'     => '',
				'eventTypeId'   => $event->getType()->getId(),
				'type'          => $event->getType()->getId(),
				'author'        => '',
				'authorId'      => $event->getOwner()->getId(),
				'level'         => '',
				'completed'     => $event->getCompleted(),
			),
			'links' => array(
				'category'  => array(
					'id'    => '',
					'name'  => '',
				)
			),
			'styles' => array(
				'visible' => '',
			),
			'attendance'        => array(
				'icon'          => false,
				'status'        => false,
				'requestButton' => false,
				'listButton'    => false,
			)
		)
	);

	echo json_encode($json);
}

exit(1);
