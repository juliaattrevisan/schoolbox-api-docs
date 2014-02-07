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

$page = new SitePage();

$userMapper = new \Schoolbox\UserMapper();

/** @var Schoolbox\User $user */
$owner = $_SESSION['user'];

switch($_POST['action']) {
	case 'Add':
	case 'Update':

		/* Default
		 */
		$until = null;

		$eventMapper = new \EOTC\EventMapper();

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
			$event = new \EOTC\Event();
		}

		/* Spawn and assign metadata record to event
		 */
		try {
			$metadataMapper = new \EOTC\EventMetadataMapper();
			$metadata = $metadataMapper->findByEvent($event)->first();
		}
		catch (\Exception $e) {
			$metadata = new \EOTC\EventMetadata();
		}

		/* NO RETREAT
		 * NO SURRENDER
		 */

		/* Author
		 */
		$userMapper = new \Schoolbox\UserMapper();

		try {
			$author = $userMapper->findById($_POST['authorId']);
		}
		catch (\Exception $e) {
		}

		/* Teacher in charge
		 */
		try {
			/* -_- magicsuggest sends this back at you in json_encoded format
			 * or as an int - i.e. there are two formats:
			 *
			 * [151]
			 * 151
			 */
			if (is_numeric($_POST['teacherInCharge'])) {
				$teacherInCharge = $userMapper->findById($_POST['teacherInCharge']);
			}
			else {
				$wtf = json_decode($_POST['teacherInCharge']);
				$teacherInCharge = $userMapper->findById($wtf[0]);
			}
		}
		catch (\Exception $e) {
		}

		/* Events recur until this time
		 */
		if (strlen($_POST['repeat_until'])) {
			$until = new Alaress_Date_Format($_POST['repeat_until'], 'd/m/y');
		}

		/* If a status changed, log it
		 */
		$statusChanged = false;
		$commentChanged = false;

		if ($event->getId()) {
			if ($metadata->getStatus()->getId() != $_POST['status_id']) {
				$statusChanged = true;
			}

			/* If a comment change, log it
			 */
			if ($metadata->getComment() != $_POST['comment']) {
				$commentChanged = true;
			}
		}

		/* Start and end dates
		 */
		$start = $_POST['startDate'] . ' ' . $_POST['startHour'] . ':' . $_POST['startMinute'] . ' ' . $_POST['startPeriod'];
		$end = $_POST['endDate'] . ' ' . $_POST['endHour'] . ':' . $_POST['endMinute'] . ' ' . $_POST['endPeriod'];

		$startDate = new Alaress_Date_Format($start, 'd/m/y h:i z');
		$endDate = new Alaress_Date_Format($end, 'd/m/y h:i z');

		// TODO: These are linked up in View EOTC Event
		// $_POST['status_id']
		$eventStatusMapper = new \EOTC\EventStatusMapper();
		$eventStatus = $eventStatusMapper->findById(1);

		// $_POST['comment']
		$comment = 'mort';

		/* Spawned further up
		 */
		$metadata->setStatus($eventStatus);
		$metadata->setComment($comment);
		$metadata->setEventType($_POST['type']);
		$metadata->setRamsRequired($_POST['ramsRequired']);
		$metadata->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
		$metadata->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

		/* All the staff that were selected
		 */
		$staffCollection = new \Schoolbox\Collection();

		if (is_array($_POST['staff']) && count($_POST['staff'])) {
			$staffCollection = $userMapper->findByColumn('id', $_POST['staff']);
		}

		/* Spawned further up
		 */
		$event->setAuthor($author);
		$event->setTeacherInCharge($teacherInCharge);
		$event->setType($_POST['type']);
		$event->setNote($_POST['note']);
		$event->setStartDate($startDate->getDatabaseDate());
		$event->setStartTime($startDate->getDatabaseTime());
		$event->setEndDate($endDate->getDatabaseDate());
		$event->setEndTime($endDate->getDatabaseTime());
		$event->setLocation($_POST['location']);
		$event->setDetail($_POST['detail']);

		/* Collections of ids
		 */
		$event->setCampusCollection(new \Schoolbox\Collection($_POST['campus']));
		$event->setStaffAttendees($staffCollection);

		/* Store metadata
		 */
		$event->setMetadata($metadata);

		/* If RAMS file is required and one hasn't been uploaded yet,
		 * save it; otherwise ignore it
		 */
		if ($event->getMetadata()->getRamsRequired()) {

			/* A RAMS file doesn't exist, nor uploaded, but was required
			 */
			if (!$event->getRamsFiles()->count() && !strlen($_FILES['ramsFile']['name'])) {
				error(161);
			}
			/* A RAMS file was uploaded; replace all existing ones
			 */
			else if (strlen($_FILES['ramsFile']['name'])) {

				/* Empty old RAMS objects and put the new one in
				 */
				$ramsFile = \Schoolbox\StorageMapper::getInstance()->upload(\Cfg::get('storage_dir') . '/' . $_FILES['ramsFile']['name'], $_FILES['ramsFile']['tmp_name'], $author->getId());

				$event->setRamsFiles(new \Schoolbox\Collection());
				$event->getRamsFiles()->add($ramsFile);
			}
			/* Otherwise just keep the current RAMS file
			 */
		}
		/* RAMS file not needed - remove all existing ones
		 */
		else {
			$event->setRamsFiles(new \Schoolbox\Collection());
		}

		/* Save this event and any recurring incidents too
		 */
		$eventRecurrenceMapper = new \EOTC\EventRecurrenceMapper($eventMapper);
		$eventRecurrenceMapper->save($event, $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate);

		/* Set up activity log
		 */
		$registry = new \EOTC\ActivityLog\Registry();
		$factory = new \EOTC\ActivityLog\Factory($event, $_SESSION['user']);

		if ($statusChanged) {
			$registry->add($factory->produce('StatusChange'));
		}
//	new \EOTC\ActivityLog\StatusChange($event, $_SESSION['user'])
//	new \EOTC\ActivityLog\CommentChange($event, $_SESSION['user'])
		if ($commentChanged) {
			$registry->add($factory->produce('CommentChange'));
		}

		/* Notify everyone
		 */
		$registry->notify();

		/* Superuser modifying some arbitrary event
		 */
		$page->localRedir('/modules/eotc/modify.php?eventID=' . $event->getId());

		break;

	case 'Delete':
		$eventMapper = new \EOTC\EventMapper();
		$metadataMapper = new \EOTC\EventMetadataMapper();

		try {
			$event = $eventMapper->findById($_POST['id']);
		}
		catch (\Exception $e) {
			error(160);
		}

		/* Delete an entire series, if the seriesId is not null
		 */
		if ($event->getSeriesId() != null) {
			if ($_POST['efficacy'] == 'series') {
				$allEvents = $eventMapper->getAllInSeries($event);

				/* Delete the metadata relations, then delete the
				 * metadata record itself
				 */
				$metadataMapper->delete($event->getMetadata());

				foreach($allEvents as $currentEvent) {
					$eventMapper->deleteCampusRelations($currentEvent);
					$eventMapper->deleteMetadataRelations($currentEvent);
					$eventMapper->deleteStaffAttendeesRelations($currentEvent);
					$eventMapper->delete($currentEvent);
				}
			}
			/* Delete a metadata relation for an event that is part
			 * of a series
			 */
			else if ($_POST['efficacy'] == 'self') {
				$eventMapper->deleteCampusRelations($event);
				$eventMapper->deleteMetadataRelations($event);
				$eventMapper->deleteStaffAttendeesRelations($currentEvent);
				$eventMapper->delete($event);
			}
		}
		/* Just a single event all on its lonesome
		 */
		else {
			$eventMapper->delete($event);
			$eventMapper->deleteCampusRelations($event);
			$eventMapper->deleteMetadataRelations($event);
			$eventMapper->deleteStaffAttendeesRelations($currentEvent);
			$metadataMapper->delete($event->getMetadata());
		}

		$page->backToTheFuture(2);
		break;

	default:
		error(160);
		break;
}