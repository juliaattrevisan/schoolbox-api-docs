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


class EventMapper extends Calendar\EventMapper {

	/**
	 * This is a direct clone with less functionality
	 */
	protected function mapping() {
		$this->hasTable('eotc_event');
		$this->hasModel('\\EOTC\\Event');

		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'series_id',
			'property' => 'seriesId',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'author_id',
			'property' => 'author',
			'type' => self::TYPE_MODEL,
			'join' => 'author',
		));
		$this->hasMap(array(
			'column' => 'teacher_in_charge_id',
			'property' => 'teacherInCharge',
			'type' => self::TYPE_MODEL,
			'join' => 'teacherInCharge',
		));
		$this->hasMap(array(
			'column' => 'type',
			'property' => 'type',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'note',
			'property' => 'note',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'detail',
			'property' => 'detail',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'location',
			'property' => 'location',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startDate',
			'property' => 'startDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'startTime',
			'property' => 'startTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endDate',
			'property' => 'endDate',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'endTime',
			'property' => 'endTime',
			'type'     => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'allday',
			'property' => 'allday',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasJoin('author', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'author_id',
			'other_key' => 'id',
		));
		$this->hasJoin('eventType', array(
			'mapper'    => '\\Schoolbox\\Calendar\\EventTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'eventTypeID',
		));
		$this->hasJoin('teacherInCharge', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'teacher_in_charge_id',
			'other_key' => 'id',
		));

		$this->registerCallback('postSave', array($this, 'postSave'));
	}

	/**
	 * Custom findById
	 *
	 * @param mixed $identifier
	 * @return object|Model
	 * @throws \Exception
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

		$row = $statement->fetch();

		/* Hydrate or die
		 */
		if ($row != false) {
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
		}

		if ($eventItem) {
			return $eventItem;
		}

		throw new \Exception('Instance of object not found');
	}

	/**
	 * Custom findAllByListGroups
	 *
	 * @return object|Model
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
			ORDER BY e.startDate, e.startTime
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
			$eventItem = $this->hydrate($row, 'e');

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
	 * @param \Schoolbox\User $user
	 * @return ListGroup\Collection
	 */
	public function findAllByListGroupsForUser(\Schoolbox\User $user) {
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
			ORDER BY e.startDate, e.startTime
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":userId", $user->getId());
		$statement->execute();

		$allEvents = new \Schoolbox\Collection();
		$processedSeriesIds = array();

		/* Hydrate or die
		 */
		foreach($statement as $row) {
			$eventItem = $this->hydrate($row, 'e');

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
	 * @param $event
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
	}

	/**
	 * Find metadata for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
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
	 * @param $event
	 * @return bool|void
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
	 * @param $event
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
	 * @param $identifier
	 * @return \Schoolbox\Collection
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
					'mapper' => new \Schoolbox\CampusMapper()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes campus relations if there are no remaining
	 * events in the series
	 *
	 * @param $event
	 * @return bool|void
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