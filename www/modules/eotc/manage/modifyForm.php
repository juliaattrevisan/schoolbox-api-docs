<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../../include/Bootstrap.php');

$page = new SitePage();

$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
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
					exit;
				}
			}
			else {
				$event = new \EOTC\Event();
			}

			/* Spawn and assign metadata record to event
			 */
			try {
				$metadataMapper = new \EOTC\EventMetadataMapper();
				$metadata = $metadataMapper->findByEvent($event);
			}
			catch (\Exception $e) {
				$metadata = new \EOTC\EventMetadata();
				$metadataStatus = new \EOTC\EventStatus();
				$metadataStatus->setId(\EOTC\EventStatusMapper::STATUS_DEFAULT);
				$metadata->setStatus($metadataStatus);
			}

			/* NO RETREAT
			 * NO SURRENDER
			 */

			/* Author
			 */
			$userMapper = new \Schoolbox\UserMapper();

			try {
				/** @var \Schoolbox\User $author */
				$author = $userMapper->findById($_POST['authorId']);
			}
			catch (Schoolbox\Mapper\Exception $e) {
				error(2);
				exit;
			}

			/* Teacher in charge
			 */
			try {
				/** @var \Schoolbox\User $teacherInCharge */
				$teacherInCharge = $userMapper->findById($_POST['teacherInCharge']);
			}
			catch (Schoolbox\Mapper\Exception $e) {
				error(2);
				exit;
			}

			// EventType
			try {
				/** @var \Schoolbox\Calendar\EventType $eventType */
				$eventType = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findById($_POST['type']);
			}
			catch (Schoolbox\Mapper\Exception $e) {
				error(2);
				exit;
			}


			/* Events recur until this time
			 */
			if (strlen($_POST['repeat_until'])) {
				$until = Alaress_Date_Format::newLocalShort($_POST['repeat_until']);
			}

			/* Start and end dates
			 */
            $startDate = Alaress_Date_Format::newLocalShort($_POST['startDate'])
                ->set12Hour($_POST['startHour'], $_POST['startPeriod'])
                ->setMinute($_POST['startMinute'])
            ;
            $endDate = Alaress_Date_Format::newLocalShort($_POST['endDate'])
                ->set12Hour($_POST['endHour'], $_POST['endPeriod'])
                ->setMinute($_POST['endMinute'])
            ;

			/* Spawned further up
			 */
			$metadata->setEventType($_POST['type']);
			$metadata->setRamsRequired($_POST['ramsRequired']);

			/* Only assign createdAt for new metadata records
			 */
			if (!$metadata->getId()) {
				$metadata->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
			}

			$metadata->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

			/* All the staff that were selected
			 */
			$staffCollection = new \Schoolbox\Collection();

			if (is_array($_POST['staff']) && count($_POST['staff'])) {
				$staffCollection = $userMapper->findByColumn('id', $_POST['staff']);
			}

			$metadata->setStaffAttendees($staffCollection);

			/* All the resources that were selected
			 */
			$resourceBookingCollection = new \Schoolbox\Collection();

			if (is_numeric($event->getId())) {
				$resourceBookingCollection = $metadataMapper->findResourceBookings($metadata->getId());
			}

			$metadata->setResourceBookings($resourceBookingCollection);

			/* Spawned further up
			 */
			$event->setAuthor($author);
			$event->setTeacherInCharge($teacherInCharge);
			$event->setType($eventType);
			$event->setTitle($_POST['note']);
			$event->setStartDateTime($startDate);
			$event->setEndDateTime($endDate);
			$event->setLocation($_POST['location']);
			$event->setDetail($_POST['detail']);
			$event->setAllDay($_POST['allday']);

            /* EOTC events don't use publishDate values, so hardcode this as per
             * SB-5978 to always be published
             */
            $event->setPublishDate('1970-01-01');

			/* Collections of ids
			 */
			$event->setCampusCollection(new \Schoolbox\Collection($_POST['campus']));

			/* Store metadata
			 */
			$event->setMetadata($metadata);

			/* If RAMS file is required and one hasn't been uploaded yet,
			 * save it; otherwise ignore it
			 *
			 * As of SB-4367 RAMs files are no longer mandatory, even if
			 * the checkbox setting declares that one must exist
			 */
			if ($event->getMetadata()->getRamsRequired()) {
				// Delete files that are checked, preserve ones that aren't
				$ramsFiles = new \Schoolbox\Collection();
				foreach ($event->getMetadata()->getRamsFiles() as $existingFile) {
					if (empty($_POST['deleteRamsFile'][$existingFile->getHash()])) {
						$ramsFiles->add($existingFile);
					}
				}
				if (!empty($_FILES['ramsFile'])) {
					// Upload new files
					// php wy u do fials liek dis
					foreach ($_FILES['ramsFile']['name'] as $i => $fileName) {
						if ($_FILES['ramsFile']['error'][$i] === UPLOAD_ERR_OK) {
							$newFile = \Schoolbox\Resource\Storage\Mapper::getInstance()->accept(
								$fileName,
								$_FILES['ramsFile']['tmp_name'][$i],
								$author->getId()
							);
							$ramsFiles->add($newFile);
						}
					}
				}
				$event->getMetadata()->setRamsFiles($ramsFiles);
			}
			/* RAMS file not needed - remove all existing ones
			 */
			else {
				$event->getMetadata()->setRamsFiles(new \Schoolbox\Collection());
			}

			/* If promoting an event, set to the next status
			 * This is a special case for event authors and In Principle status
			 */
			$canSelfPromote = $authenticator->canSelfPromote($_SESSION['user'], $event);

			if (isset($_POST['promoteInPrinciple']) && ($_POST['promoteInPrinciple'] == 1) && $canSelfPromote) {
				$eventStatusMapper = new \EOTC\EventStatusMapper();
				$nextStatusId = $eventStatusMapper->findNextStatus($event);
				$eventStatus = $eventStatusMapper->findById($nextStatusId);

				/* Remember original states so we know which obs to spawn
				 */
				$originalStatus = $event->getMetadata()->getStatus()->getId();
				$originalComment = $event->getMetadata()->getComment();

				/* Set status id
				 */
				$event->getMetadata()->setStatus($eventStatus);
				$event->getMetadata()->setComment($_POST['comment']);
				
				/* Activity Log
				 */
				$registry = new \EOTC\ActivityLog\Registry();
				$factory = new \EOTC\ActivityLog\Factory($event, $_SESSION['user']);

				/* If a status changed, log it
				 */
				if ($originalStatus != $event->getMetadata()->getStatus()->getId()) {
					$registry->add($factory->produce('StatusChange'));
				}

				/* If a comment changed, log it
				 */
				if ($originalComment != $event->getMetadata()->getComment()) {
					$registry->add($factory->produce('CommentChange'));
				}

				/* Notify everyone
				 */
				$registry->notify();
			}

            /* Set up individual relations for each event in the series
             * Event object is provided within eventRecurrenceMapper
             */
            $eventRelations = new \Schoolbox\Calendar\EventRelations(null, null, null);

            /* Save this event and any recurring incidents too
             */
			$eventRecurrenceMapper = new \EOTC\EventRecurrenceMapper($eventMapper);
			$eventRecurrenceMapper->save($event, $_POST['efficacy'], $_POST['repeat'], $until, $startDate, $endDate, null, $eventRelations);

			/* Superuser modifying some arbitrary event
			 */
			$page->localRedir('/modules/eotc/manage/modify.php?eventID=' . $eventRecurrenceMapper->getFirstEventIdInSeries());

			break;

        // There's no delete of EOTC events as of EOTC1.1 (SB-5297)
//		case 'Delete':
//			$eventMapper = new \EOTC\EventMapper();
//			$metadataMapper = new \EOTC\EventMetadataMapper();
//
//			try {
//				$event = $eventMapper->findById($_POST['id']);
//			}
//			catch (\Exception $e) {
//				error(160);
//			}
//
//			/* Delete an entire series, if the seriesId is not null
//			 */
//			if ($event->getSeriesId() != null) {
//				if ($_POST['efficacy'] == 'series') {
//					$allEvents = $eventMapper->getAllInSeries($event);
//
//					/* Delete the metadata relations, then delete the
//					 * metadata record itself
//					 */
//					$metadataMapper->deleteStaffAttendeesRelations($event->getMetadata());
//					$metadataMapper->deleteStudentAttendeesRelations($event->getMetadata());
//					$metadataMapper->deleteFilesRelations($event->getMetadata());
//					$metadataMapper->deleteResourceBookingsRelations($event->getMetadata());
//					$metadataMapper->delete($event->getMetadata());
//
//					foreach($allEvents as $currentEvent) {
//						$eventMapper->deleteMetadataRelations($currentEvent);
//						$eventMapper->deleteCampusRelations($currentEvent);
//						$eventMapper->delete($currentEvent);
//					}
//
//					/* Since you deleted all the instances of a this event,
//					 * there's nowhere else to go but here
//					 */
//					$page->localRedir('/modules/eotc/myReview');
//				}
//				/* Delete a metadata relation for an event that is part
//				 * of a series
//				 */
//				else if ($_POST['efficacy'] == 'self') {
//					$metadataMapper->deleteStaffAttendeesRelations($event->getMetadata());
//					$metadataMapper->deleteStudentAttendeesRelations($event->getMetadata());
//					$metadataMapper->deleteFilesRelations($event->getMetadata());
//					$metadataMapper->deleteResourceBookingsRelations($event->getMetadata());
//					$eventMapper->deleteMetadataRelations($event);
//					$eventMapper->deleteCampusRelations($event);
//					$eventMapper->delete($event);
//
//					/* Continue editing
//					 */
//					$page->backToTheFuture(2);
//				}
//			}
//			/* Just a single event all on its lonesome
//			 */
//			else {
//				$metadataMapper->deleteStaffAttendeesRelations($event->getMetadata());
//				$metadataMapper->deleteStudentAttendeesRelations($event->getMetadata());
//				$metadataMapper->deleteFilesRelations($event->getMetadata());
//				$metadataMapper->deleteResourceBookingsRelations($event->getMetadata());
//				$metadataMapper->delete($event->getMetadata());
//				$eventMapper->deleteMetadataRelations($event);
//				$eventMapper->deleteCampusRelations($event);
//				$eventMapper->delete($event);
//
//				/* Since you deleted all the instances of a this event,
//				 * there's nowhere else to go but here
//				 */
//				$page->localRedir('/modules/eotc/myReview');
//			}
//
//			break;

		default:
			error(160);
			break;
	}
}
else {
	error(19);
}
