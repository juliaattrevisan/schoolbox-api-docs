<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$groupHandler = new Group();
$calendarHandler = new Calendar();
$page = new SitePage();

$userMapper = new \Schoolbox\UserMapper();

/** @var Schoolbox\User $user */
$owner = $_SESSION['user'];

/* Superuser/Staff/Parent adding event for someone else or
 * someone creating their own event
 */
if ($groupHandler->isSuperuser() || $groupHandler->isStaff() || $groupHandler->isParentOfChild($_POST['userId']) || ($_SESSION['user']->getId() == $_POST['userId'])) {
	$owner = $userMapper->findById($_POST['userId']);
}
/* You're not supposed to be able to create events under other circumstances
 */
else {
	error(19);
}

switch($_POST['action']) {
	case 'Add':
	case 'Update':

		/* Default
		 */
		$publishDate = null;
		$until = null;
		$eventMapper = new \Schoolbox\Calendar\EventMapper();

		/* Spawn an existing event if id provided
		 * Otherwise spawn a new one
		 */
		if (is_numeric($_POST['id'])) {
			try {
				$event = $eventMapper->findById($_POST['id']);
			}
			catch (\Exception $e) {
				error(160);
			}
		}
		else {
			$event = new \Schoolbox\Calendar\Event();
		}

		/* Events aren't visible until this date
		 * TODO: Fix validator in Alaress Date Format
		 */
		if (strlen($_POST['publishDate'])) {
			$pubDateObject = new Alaress_Date_Format($_POST['publishDate'], 'd/m/Y');
			$publishDate = $pubDateObject->getDatabaseDate();
		}

		/* Events recur until this time
		 */
		if (strlen($_POST['repeat_until'])) {
			$until = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/Y');
		}

		/* Start and end dates
		 */
		$start = $_POST['startDate'] . ' ' . $_POST['startHour'] . ':' . $_POST['startMinute'] . ' ' . $_POST['startPeriod'];
		$end = $_POST['endDate'] . ' ' . $_POST['endHour'] . ':' . $_POST['endMinute'] . ' ' . $_POST['endPeriod'];

		$startDate = new Alaress_Date_Format($start, 'd/m/Y H:i z');
		$endDate = new Alaress_Date_Format($end, 'd/m/Y H:i z');

		/* NO RETREAT
		 * NO SURRENDER
		 */
		$userMapper = new \Schoolbox\UserMapper();
		$author = $userMapper->findById($_POST['authorId']);

		/* Spawned further up
		 */
		$event->setOwner($owner);
		$event->setAuthor($author);
		$event->setAccess($_POST['level']);
		$event->setType(\Schoolbox\Calendar\EventTypeMapper::getInstance()->createModel()->setId($_POST['type']));
		$event->setTitle($_POST['note']);
		$event->setStartDateTime($startDate);
		$event->setEndDateTime($endDate);
		$event->setLocation($_POST['location']);
		$event->setDetail($_POST['detail']);
		$event->setPublishDateTime($publishDate);

		/* Collections of ids
		 */
		$event->setRoleCollection(new \Schoolbox\Collection($_POST['roles']));
		$event->setCampusCollection(new \Schoolbox\Collection($_POST['campus']));

		/* Attendance dirty bit should be set to true if the attendance
		 * setting now differs from what it previously was
		 */
		if ($event->getAttendance() != $_POST['attendance']) {
			$event->setAttendance($_POST['attendance']);
			$event->setAttendanceDirty(true);
		}

		// Force events to have at least one campus value selected (if a group event)
		if ($event->isCampusLevel()) {
			if (!is_array($_POST['campus']) || !count($_POST['campus'])) {
				error(158);
			}
		}

		/* Save this event and any recurring incidents too
		 */
		$eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($eventMapper);
		$eventRecurrenceMapper->save($event, $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate);

		/* If you were editing an event on someone else's calendar, don't
		 * get stuffed around by time travel's one-index-offset rip in
		 * the space-time continuum
		 */

		/* Superuser modifying some arbitrary event
		 */
		if (!in_array($_SESSION['user']->getId(), array($event->getAuthor()->getId(), $event->getOwner()->getId()))) {
			$page->backToTheFuture(3);
		}
		/* Teacher modifying self-created event for some arbitrary person
		 */
		else if (($event->getAuthor()->getId() != $event->getOwner()->getId()) && ($event->getAuthor()->getId() == $_SESSION['user']->getId())) {
			$page->backToTheFuture(3);
		}
		else if (!empty($_POST['return'])) {
			$page->localRedir($_POST['return']);
		}
		else {
			$page->backToTheFuture(2);
		}

		break;

    // There's no delete of EOTC events as of EOTC1.1 (SB-5297)
//	case 'Delete':
//		$eventMapper = new \Schoolbox\Calendar\EventMapper();
//
//		try {
//			$event = $eventMapper->findById($_POST['id']);
//		}
//		catch (\Exception $e) {
//			error(160);
//		}
//
//		/* Delete an entire series, if the seriesId is not null
//		 */
//		if ($event->getSeriesId() != null) {
//			if ($_POST['efficacy'] == 'series') {
//				$allEvents = $eventMapper->getAllInSeries($event);
//
//				foreach($allEvents as $currentEvent) {
//					$eventMapper->delete($currentEvent);
//				}
//			}
//			else if ($_POST['efficacy'] == 'self') {
//				$eventMapper->delete($event);
//			}
//		}
//		else {
//			$eventMapper->delete($event);
//		}
//
//		$page->backToTheFuture(2);
//		break;

	default:
		error(0);
		break;
}