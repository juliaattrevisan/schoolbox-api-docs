<?php
/**
 * EventMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;

use Schoolbox\Calendar;
use Schoolbox\Calendar\EventType;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;


class EventMapper extends Calendar\EventMapper {

	/**
	 * Calls for the core mapping of events, then extends it
	 */
	protected function mapping() {
		$this->coreMapping();

		$this->hasTable('eotc_event');
		$this->hasModel('\\EOTC\\Event');

		$this->hasMap(array(
			'column' => 'teacher_in_charge_id',
			'property' => 'teacherInCharge',
			'type' => self::TYPE_MODEL,
			'join' => 'teacherInCharge',
		));
		$this->hasJoin('teacherInCharge', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'teacher_in_charge_id',
			'other_key' => 'id',
		));
	}

	/**
	 * Custom findById
	 *
	 * @param int $identifier
	 * @return Event
	 * @throws Exception
	 */
	public function findById($identifier) {
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
			WHERE e.id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $identifier);
		$statement->execute();

		;

		/* Hydrate or die
		 */
		if ($row = $statement->fetch()) {
			/** @var Event $eventItem */
			$eventItem = $this->hydrate($row, 'e');

			/** @var User $authorItem */
			$authorItem = $authorMapper->hydrate($row, 'a');
			$eventItem->setAuthor($authorItem);

			/** @var User $teacherInChargeItem */
			$teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
			$eventItem->setTeacherInCharge($teacherInChargeItem);

			/** @var EventType $eventTypeItem */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setType($eventTypeItem);

			/* Accumulate the metadata for this event
			 * Only use the first record
			 */
			$eventItem->setMetadata($this->findMetadata($identifier)->first());

			/* Accumulate campuses for this event
			 */
			$eventItem->setCampusCollection($this->findCampuses($identifier));

			/* Determine current status
			 */
			if ($eventItem->getMetadata()) {
				$metadataMapper = new EventMetadataMapper();
				$metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());

				/* Accumulate staff attendees for this event
				 */
				$allStaff = $metadataMapper->findStaffAttendees($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setStaffAttendees($allStaff);

				/* Accumulate student attendees for this event
				 */
				$allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setStudentAttendees($allStudents);

				/* Accumulate RAMS files for this event
				 */
				$allFiles = $metadataMapper->findFiles($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setRamsFiles($allFiles);

				/* Accumulate resource bookings for this event
				 */
				$allResourceBookings = $metadataMapper->findResourceBookings($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setResourceBookings($allResourceBookings);

				/* Activity logs
				 */
				$activityLogMapper = new ActivityLog\Mapper();

				$allActivityLogs = $activityLogMapper->findByColumn('metadata_id', $eventItem->getMetadata()->getId(), array(
					'order' => array(
						'created_at' => 'DESC'
					)
				));

				$eventItem->getMetadata()->setActivityLog($allActivityLogs);
			}

			return $eventItem;
		}

		throw new Exception('Instance of object not found');
	}

	/**
	 * Custom findAllByListGroups
	 *
	 * @return object
	 */
	public function findAllByListGroups() {
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');
		$teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

		/* Looked for a way to convince GROUP BY to group records with
		 * integer series_id's, but leave NULL ones individual
		 *
		 * Thought about using GROUP BY IFNULL(e.series_id, e.id) but it
		 * didn't really work very well
		 *
		 * I'll just postprocess them
		 */
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
			WHERE e.start_datetime >= DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
			ORDER BY e.start_datetime
		";

		$statement = $this->database->prepare($query);
		$statement->execute();

		/* Postprocess this list of events based on status and
		 * organise them into a collection of collections
		 */
		$listGroup = new ListGroup\Collection();
		$listGroup->setup();

		/* Hydrate or die
		 */
		foreach($statement as $row) {
			/** @var Event $eventItem */
			$eventItem = $this->hydrate($row, 'e');

			/** @var User $teacherInChargeItem */
			$teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
			$eventItem->setTeacherInCharge($teacherInChargeItem);

			/** @var EventType $eventTypeItem */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setType($eventTypeItem);

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
				$allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setStudentAttendees($allStudents);
			}

			/* Assign it to an Event List Group
			 */
			$listGroup->assign($eventItem);
		}

		/* Sort each grouping in a special way
		 */
		$listGroup->sortEventsByStartDate();

		return $listGroup;
	}

	/**
	 * Custom findAllByListGroups for a particular user
	 *
	 * This is used on the My EOTC screen
	 *
	 * @param User $user
	 * @return ListGroup\Collection
	 */
	public function findAllByListGroupsForUser($user) {
		$authorMapper = $this->getJoinMapper('author');
		$eventTypeMapper = $this->getJoinMapper('eventType');
		$teacherInChargeMapper = $this->getJoinMapper('teacherInCharge');

		/* Looked for a way to convince GROUP BY to group records with
		 * integer series_id's, but leave NULL ones individual
		 *
		 * Thought about using GROUP BY IFNULL(e.series_id, e.id) but it
		 * didn't really work very well
		 *
		 * I'll just postprocess them
		 */
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
			WHERE e.author_id = :userId
			OR e.teacher_in_charge_id = :userId
			ORDER BY e.start_datetime
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":userId", $user->getId());
		$statement->execute();

		$allEvents = new Collection();
		$processedSeriesIds = array();

		/* Hydrate or die
		 */
		foreach($statement as $row) {
			/** @var Event $eventItem */
			$eventItem = $this->hydrate($row, 'e');

			/** @var User $teacherInChargeItem */
			$teacherInChargeItem = $teacherInChargeMapper->hydrate($row, 'c');
			$eventItem->setTeacherInCharge($teacherInChargeItem);

			/** @var EventType $eventTypeItem */
			$eventTypeItem = $eventTypeMapper->hydrate($row, 't');
			$eventItem->setType($eventTypeItem);

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
				$allStudents = $metadataMapper->findStudentAttendees($eventItem->getMetadata()->getId());
				$eventItem->getMetadata()->setStudentAttendees($allStudents);
			}

			/* Only add the first in a series; ignore the others
			 */
			if (is_numeric($eventItem->getSeriesId())) {
				if (!in_array($eventItem->getSeriesId(), $processedSeriesIds)) {
					$processedSeriesIds[] = $eventItem->getSeriesId();

					$allEvents->add($eventItem);
				}
			}
			/* Otherwise just add the one-off event
			 */
			else {
				$allEvents->add($eventItem);
			}
		}

		return $allEvents;
	}

	/**
	 * Postsave hook
	 *
	 * @param Event $event
	 */
	protected function postSave($event) {
		$metadataMapper = new EventMetadataMapper();
		$metadataMapper->save($event->getMetadata());
		$metadataMapper->saveRelation($event);

		$this->deleteCampusRelations($event);
		$this->createCampus($event);

		$metadataMapper->deleteStaffAttendeesRelations($event->getMetadata());
		$metadataMapper->createStaffAttendees($event->getMetadata());

		$metadataMapper->deleteStudentAttendeesRelations($event->getMetadata());
		$metadataMapper->createStudentAttendees($event->getMetadata());

		$metadataMapper->deleteFilesRelations($event->getMetadata());
		$metadataMapper->createFiles($event->getMetadata());

		$metadataMapper->deleteResourceBookingsRelations($event->getMetadata());
		$metadataMapper->createResourceBookings($event->getMetadata());
	}

	/**
	 * Find metadata for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|EventMetadata[]
	 */
	protected function findMetadata($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_event_metadata',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'event_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'metadata_id',
					'key' => 'id',
					'mapper' => new EventMetadataMapper()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes metadata relations if there are no remaining
	 * events in the series
	 *
	 * @param Event $event
	 */
	public function deleteMetadataRelations($event) {
		$query = "
			DELETE FROM `eotc_event_metadata`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}

	/**
	 * Assign campuses to an event
	 *
	 * @param Event $event
	 */
	private function createCampus($event) {
		$query = "
			INSERT INTO `eotc_event_campus` (event_id, campus_id)
			VALUES (:eventId, :campusId)
		";

		foreach ($event->getCampusCollection() as $currentCampus) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":campusId", $currentCampus);
			$statement->execute();
		}
	}

	/**
	 * Find campuses for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|Campus[]
	 */
	protected function findCampuses($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_event_campus',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'event_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'campus_id',
					'key' => 'id',
					'mapper' => new CampusMapper()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes campus relations if there are no remaining
	 * events in the series
	 *
	 * @param Event $event
	 */
	public function deleteCampusRelations($event) {
		$query = "
			DELETE FROM `eotc_event_campus`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}
}