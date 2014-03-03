<?php
/**
 * EventConnector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventConnector extends EventMapper {

	/**
	 * Get details of a single event (used in Mobile)
	 * 
	 * @param $eventId
	 * @return \Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$authorMapper->getColumnString('a')},
				{$eventTypeMapper->getColumnString('t')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $eventId);
		$statement->execute();

		$row = $statement->fetch();

		$collection = new \Calendar_Event_Collection();

		if ($row) {
			$eventItem = $this->hydrate($row, 'e');

			/* Author
			 */
			$authorItem = $authorMapper->hydrate($row, 'a');
			$eventItem->setAuthor($authorItem);

			/* Event Type
			 */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setEventType($eventTypeItem);

			/* Convert dates ;(
			 */
			$startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d h:i:s');
			$eventItem->setStartDateTime($startDateTime);

			$endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d h:i:s');
			$eventItem->setEndDateTime($endDateTime);

			/* Accumulate campuses for this event
			 */
			$eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

			/* For filters
			 */
			$campusIds = array();

			foreach($eventItem->getCampusCollection() as $currentCampus) {
				$campusIds[] = $currentCampus->getId();
			}

			/* Translate an \EOTC\Event type into a
			 * Calendar_Event_Type_Abstract type
			 */
			$event = new \Calendar_Event_Type_EOTC();
			$event->setAuthorName($eventItem->getAuthor()->getFullname());
			$event->setAuthorId($eventItem->getAuthor()->getId());
			$event->setEventId($eventItem->getId());
			$event->setStartTime($eventItem->getStartDateTime());
			$event->setEndTime($eventItem->getEndDateTime());
			$event->setCampusList(implode(',', $campusIds));

			/* EOTC Events are always of the type "EOTC" for usage
			 * in calendar filters
			 */
			$event->setType($eventItem->getEventType()->getId());
			$event->setNote($eventItem->getNote());
			$event->setAccess($eventItem->getAccess());
			$event->setEventType($eventItem->getEventType()->getType());
			$event->setColour($eventItem->getEventType()->getColor());
			$event->setDetail($eventItem->getDetail());
			$event->setLocation($eventItem->getLocation());
			$event->setAllDay(0);

			/* If you have write access to the event. Only superusers
			 * and authors can modify an event
			 */
			if ($_SESSION['user']->isSuperuser() || $_SESSION['user']->getId() == $event->getAuthorId()) {
				$event->setIsEditable(true);
			}
			else {
				$event->setIsEditable(false);
			}

			$event->setAttendance(0);
			$event->setCompleted(0);

			$collection->add($event);
		}

		return $collection;
	}

	/**
	 * Get events within a particular time span
	 *
	 * @param $user
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @return \Calendar_Event_Collection
	 */
	public function getDayEvents($user, $startTimestamp, $endTimestamp) {
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');
		$teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

		$query = "
			SELECT
				{$this->getColumnString('e')},
				{$authorMapper->getColumnString('a')},
				{$teacherInChargeMapper->getColumnString('c')},
				{$eventTypeMapper->getColumnString('t')}
			FROM `{$this->table}` e
			LEFT JOIN {$this->getJoinSql('author', 'e', 'a')}
			LEFT JOIN {$this->getJoinSql('teacherInCharge', 'e', 'c')}
			LEFT JOIN {$this->getJoinSql('eventType', 'e', 't')}
			WHERE
				DATE(e.endDate) > DATE(FROM_UNIXTIME(:startTimestamp))
				AND
				DATE(e.startDate) <= DATE(FROM_UNIXTIME(:endTimestamp))
			ORDER BY e.startTime, e.endTime
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":startTimestamp", $startTimestamp);
		$statement->bindParam(":endTimestamp", $endTimestamp);
		$statement->execute();

		$collection = new \Calendar_Event_Collection();

		foreach ($statement as $row) {
			$eventItem = $this->hydrate($row, 'e');

			/* Author
			 */
			$authorItem = $authorMapper->hydrate($row, 'a');
			$eventItem->setAuthor($authorItem);

			/* Teacher In Charge
			 */
			$teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
			$eventItem->setTeacherInCharge($teacherInChargeItem);

			/* Event Type
			 */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setEventType($eventTypeItem);

			/* Convert dates ;(
			 */
			$startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d h:i:s');
			$eventItem->setStartDateTime($startDateTime);

			$endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d h:i:s');
			$eventItem->setEndDateTime($endDateTime);

			/* Accumulate campuses for this event
			 */
			$eventItem->setCampusCollection($this->findCampuses($eventItem->getId()));

			/* Accumulate the metadata for this event
			 * Only use the first record
			 */
			$eventItem->setMetadata($this->findMetadata($eventItem->getId())->first());

			/* Determine current status
			 */
			if ($eventItem->getMetadata()) {
				$metadataMapper = new EventMetadataMapper();
				$metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

				/* Accumulate student attendees for this event
				 */
				$allStudentAttendees = $metadataMapper->findStudentAttendees($eventItem->getId());
				$eventItem->getMetadata()->setStudentAttendees($allStudentAttendees);
			}

			/* For filters
			 */
			$campusIds = array();

			foreach($eventItem->getCampusCollection() as $currentCampus) {
				$campusIds[] = $currentCampus->getId();
			}

			/* Only present this event for a particular user if they are
			 * superuser or attendee (teacher in charge, staff, student)
			 */
			$isAttendee = false;

			/* Superusers can always see it
			 */
			if (!$isAttendee) {
				if ($user->isSuperuser()) {
					$isAttendee = true;
				}
			}

			/* Creators can always see it
			 */
			if (!$isAttendee) {
				if ($eventItem->getAuthor()->getId() == $user->getId()) {
					$isAttendee = true;
				}
			}

			/* Teachers in Charge should see it in translucent form
			 */
			if (!$isAttendee) {
				$isAttendee = $user->getId() == $eventItem->getTeacherInCharge()->getId();
			}

			/* Staff members should see it in translucent form
			 */
			if (!$isAttendee) {
				foreach($eventItem->getStaffAttendees() as $currentStaff) {
					if ($currentStaff->getId() == $user->getId()) {
						$isAttendee = true;
						break;
					}
				}
			}

			/* Students should only see this event if it's Approved and
			 * if they are assigned to it as an attendee
			 */
			if (!$isAttendee) {
				if ($eventItem->getMetadata()->getStatus()->isApproved()) {
					foreach($eventItem->getStudentAttendees() as $currentStudent) {
						if ($currentStudent->getId() == $user->getId()) {
							$isAttendee = true;
							break;
						}
					}
				}
			}

			if ($isAttendee) {

				/* Translate an \EOTC\Event type into a
				 * Calendar_Event_Type_Abstract type
				 */
				$event = new \Calendar_Event_Type_EOTC();
				$event->setAuthorName($eventItem->getAuthor()->getFullname());
				$event->setAuthorId($eventItem->getAuthor()->getId());
				$event->setEventId($eventItem->getId());
				$event->setStartTime($eventItem->getStartDateTime());
				$event->setEndTime($eventItem->getEndDateTime());
				$event->setCampusList(implode(',', $campusIds));

				/* EOTC Events are always of the type "EOTC" for usage
				 * in calendar filters
				 */
				$event->setType($eventItem->getEventType()->getId());
				$event->setNote($eventItem->getNote());
				$event->setAccess($eventItem->getAccess());
				$event->setEventType($eventItem->getEventType()->getType());
				$event->setColour($eventItem->getEventType()->getColor());
				$event->setDetail($eventItem->getDetail());
				$event->setLocation($eventItem->getLocation());
				$event->setAllDay(0);

				/* If you have write access to the event. Only superusers
				 * and authors can modify an event
				 */
				if ($_SESSION['user']->isSuperuser() || $_SESSION['user']->getId() == $event->getAuthorId()) {
					$event->setIsEditable(true);
				}
				else {
					$event->setIsEditable(false);
				}

				$event->setAttendance(0);
				$event->setCompleted(0);

				/* Custom data
				 */
				$event->setCustom(array(
					'approved' => $eventItem->getMetadata()->getStatus()->isApproved()
				));

				$collection->add($event);
			}
		}

		return $collection;
	}
} 