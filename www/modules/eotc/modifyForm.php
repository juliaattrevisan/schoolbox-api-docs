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

/** @var Schoolbox\User $user */
$user = $_SESSION['user'];
$ownerId = $user->getId();

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
}

switch($_POST['action']) {
	case 'Add':
	case 'Update':

		/* Start and end dates
		 */
		$start = $_POST['startDate'] . ' ' . $_POST['startHour'] . ':' . $_POST['startMinute'] . ' ' . $_POST['startPeriod'];
		$end = $_POST['endDate'] . ' ' . $_POST['endHour'] . ':' . $_POST['endMinute'] . ' ' . $_POST['endPeriod'];

		$startDate = new Alaress_Date_Format($start, 'd/m/y h:i z');
		$endDate = new Alaress_Date_Format($end, 'd/m/y h:i z');

		/* Events aren't visible until this date
		 */
		if ($_POST['publishDate']) {
			$pubDateObject = new Alaress_Date_Format($_POST['publishDate'], 'd/m/y');
			$publishDate = $pubDateObject->getDatabaseDate();
		}
		else {
			$publishDate = 'NULL';
		}

		/* Events recur until this time
		 */
		$until = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/y');

		/* Crazy handler
		 */
		$event = array(
			'owner' => $ownerId,
			'access' => $_POST['level'],
			'author_id' => $_POST['authorId'],
			'sid' => null,
			'campusList' => null,
			'groupList' => null,
			'type' => $_POST['type'],
			'note' => $_POST['note'],
			'allday' => !empty($_POST['allday']),
			'startDate' => $startDate->getDatabaseDate(),
			'startTime' => $startDate->getDatabaseTime(),
			'endDate' => $endDate->getDatabaseDate(),
			'endTime' => $endDate->getDatabaseTime(),
			'location' => $_POST['location'],
			'attendance' => $_POST['attendance'],
			'detail' => $_POST['detail'],
			'publish_date' => $publishDate,
		);

		// Force events to have at least one campus value selected (if a group event)
		if ($_POST['level'] == Calendar::ACCESS_CAMPUS) {
			$event['groupList'] = $_POST['groupCheck'];

			if (is_array($_POST['campus']) && count($_POST['campus'])) {
				$event['campusList'] = $_POST['campus'];
			}
			else {
				error(158);
			}
		}
		elseif ($_POST['level'] == Calendar::ACCESS_GROUP) {
			$event['sid'] = $_POST['group'];
		}

		/* Set up recurrences, if appropriate
		 * This is mandatory as events are saved via EventRecurrence
		 */
		$eventRecurrence = new \EOTC\Calendar\EventRecurrence($_POST['id'], $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate);
		$eventRecurrence->setup()->save($event);

		/* If you were editing an event on someone else's calendar, don't
		 * get stuffed around by time travel's one-index-offset rip in
		 * the space-time continuum
		 */

		/* Superuser modifying some arbitrary event
		 */
		if (!in_array($_SESSION['user']->getId(), array($event['author_id'], $event['owner']))) {
			$page->backToTheFuture(3);
		}
		/* Teacher modifying self-created event for some arbitrary person
		 */
		else if (($event['author_id'] != $event['owner']) && ($event['author_id'] == $_SESSION['user']->getId())) {
			$page->backToTheFuture(3);
		}
		else if (!empty($_POST['return'])) {
			$page->localRedir($_POST['return']);
		}
		else {
			$page->backToTheFuture(2);
		}

		break;

	case 'Delete':
		if(!is_numeric($_POST['id'])) {
			error(0);
		}

		if (($sID = $calendarHandler->getSeriesID($_POST['id'])) != null) {
			// delete all in series
			if ($_POST['efficacy'] == 'series') {
				$series = $calendarHandler->getAllInSeries($sID);
				// rather than wrapping into a single query batch delete, this deletion checks individual permissions on each event
				// (which may not necessarily be the same for each)
				foreach($series as $event) {
					$calendarHandler->delete($event['id']);
				}
			}
			else if($_POST['efficacy'] == 'self') {
				$calendarHandler->delete($_POST['id']);
			}
		}
		// delete self
		else {
			$calendarHandler->delete($_POST['id']);
		}

		$page->backToTheFuture(2);
		break;

	default:
		error(0);
		break;
}


if (isset($_POST['date'])) {

}
else if (isset($_GET['delete'])) {
	$calendar = $calendarHandler->getByID($_GET['delete']);
	$calendarHandler->delete($_GET['delete']);

	$date = explode("-", $calendar['date']);
	$date = array_reverse($date);

	$page->backToTheFuture(2);

}