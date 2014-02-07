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
		$this->hasJoin('author', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'author_id',
			'other_key' => 'id',
		));
		$this->hasJoin('eventType', array(
			'mapper'    => '\\EOTC\\Calendar\\EventTypeMapper',
			'this_key'  => 'type',
			'other_key' => 'eventTypeID',
		));
		$this->hasJoin('teacherInCharge', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'teacher_in_charge_id',
			'other_key' => 'id',
		));
		$this->hasJoin('activityLog', array(
			'mapper'    => '\\EOTC\\ActivityLog\\Mapper',
			'this_key'  => 'id',
			'other_key' => 'event_id',
		));
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
//		$activityLogMapper = $this->getJoinMapper('activityLog');

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
			/* Hydrate or die
			 */
			$allActivityLogs = new \Schoolbox\Collection();

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

//			$activityLog = $activityLogMapper->hydrate($row, 'l');
//			$allActivityLogs->add($activityLog);

			/* Convert dates ;(
			 */
			$startDateTime = new \Alaress_Date_Format($eventItem->getStartDate() . ' ' . $eventItem->getStartTime(), 'y-m-d h:i:s');
			$eventItem->setStartDateTime($startDateTime);

			$endDateTime = new \Alaress_Date_Format($eventItem->getEndDate() . ' ' . $eventItem->getEndTime(), 'y-m-d h:i:s');
			$eventItem->setEndDateTime($endDateTime);

			/* Accumulate the metadata for this event
			 */
			$eventItem->setMetadata($this->findMetadata($identifier));

			/* Accumulate campuses for this event
			 */
			$eventItem->setCampusCollection($this->findCampuses($identifier));

			/* Accumulate staff attendees for this event
			 */
			$eventItem->setStaffAttendees($this->findStaffAttendees($identifier));

			/* Accumulate student attendees for this event
			 */
			$eventItem->setStudentAttendees($this->findStudentAttendees($identifier));

			/* Accumulate RAMS files for this event
			 */
			$eventItem->setRamsFiles($this->findFiles($identifier));

			/* Determine current status
			 */
			if ($eventItem->getMetadata()->count()) {
				$metadataMapper = new EventMetadataMapper();
				$metadataMapper->loadJoinedModel('status', $eventItem->getMetadata());
			}
		}

		if ($eventItem) {
			$eventItem->setActivityLog($allActivityLogs);
			return $eventItem;
		}

		throw new \Exception('Instance of object not found');
	}

	/**
	 * Postsave hook
	 *
	 * @param $event
	 */
	protected function postSave($event) {
		$eventMetadataMapper = new EventMetadataMapper();
		$eventMetadataMapper->save($event->getMetadata());
		$eventMetadataMapper->saveRelation($event);

		$this->deleteCampusRelations($event);
		$this->createCampus($event);

		$this->deleteStaffAttendeesRelations($event);
		$this->createStaffAttendees($event);

		$this->deleteStudentAttendeesRelations($event);
		$this->createStudentAttendees($event);

		$this->deleteFilesRelations($event);
		$this->createFiles($event);
	}

	/**
	 * Find metadata for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findMetadata($identifier) {
		return $this->manyManyRelation(array(
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
		));
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
	private function findCampuses($identifier) {
		return $this->manyManyRelation(array(
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
		));
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

	/**
	 * Assign staff to an event
	 *
	 * @param $event
	 */
	private function createStaffAttendees($event) {
		$query = "
			INSERT IGNORE INTO `eotc_event_staff` (event_id, user_id)
			VALUES (:eventId, :userId)
		";

		foreach ($event->getStaffAttendees() as $currentStaff) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":userId", $currentStaff->getId());
			$statement->execute();
		}
	}

	/**
	 * Find staff for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findStaffAttendees($identifier) {
		return $this->manyManyRelation(array(
				'name' => 'eotc_event_staff',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'event_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'user_id',
					'key' => 'id',
					'mapper' => new \Schoolbox\UserMapper()
				),
			));
	}

	/**
	 * Deletes staff attendee relations if there are no remaining
	 * events in the series
	 *
	 * @param $event
	 * @return bool|void
	 */
	public function deleteStaffAttendeesRelations($event) {
		$query = "
			DELETE FROM `eotc_event_staff`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}

	/**
	 * Assign students to an event
	 *
	 * @param $event
	 */
	private function createStudentAttendees($event) {
		$query = "
			INSERT IGNORE INTO `eotc_event_student` (event_id, user_id)
			VALUES (:eventId, :userId)
		";

		foreach ($event->getStudentAttendees() as $currentStaff) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":userId", $currentStaff->getId());
			$statement->execute();
		}
	}

	/**
	 * Find students for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findStudentAttendees($identifier) {
		return $this->manyManyRelation(array(
			'name' => 'eotc_event_student',
		),
		array(
			/* Find things to join to this...
			 */
			'column' => 'event_id',
			'value' => $identifier,

			/* ...using this relation, such that column = key
			 */
			'relation' => array(
				'column' => 'user_id',
				'key' => 'id',
				'mapper' => new \Schoolbox\UserMapper(),
				'options' => array(
					'order' => array(
						'lastname' => 'ASC'
					)
				)
			),
		));
	}

	/**
	 * Deletes student attendee relations if there are no remaining
	 * events in the series
	 *
	 * @param $event
	 * @return bool|void
	 */
	public function deleteStudentAttendeesRelations($event) {
		$query = "
			DELETE FROM `eotc_event_student`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}

	/**
	 * Add specific relations for a particular event
	 *
	 * @param $event
	 * @param $ids
	 */
	public function addStudentAttendeesRelationsSpecific($event, $ids) {
		if (is_array($ids) && count($ids)) {
			$query = "
				INSERT IGNORE INTO `eotc_event_student` (event_id, user_id)
				VALUES (:eventId, :userId)
			";

			foreach($ids as $currentId) {
				if (preg_match('/[0-9]+/', $currentId)) {
					$statement = $this->database->prepare($query);
					$statement->bindParam(":eventId", $event->getId());
					$statement->bindParam(":userId", $currentId);
					$statement->execute();
				}
			}
		}
	}

	/**
	 * Delete specific relations for a particular event
	 *
	 * @param $event
	 * @param $ids
	 */
	public function deleteStudentAttendeesRelationsSpecific($event, $ids) {
		if (is_array($ids) && count($ids)) {
			$allIds = array();

			foreach($ids as $currentId) {
				if (preg_match('/[0-9]+/', $currentId)) {
					$allIds[] = $currentId;
				}
			}

			$allIds = implode(',', $allIds);

			$query = "
				DELETE FROM `eotc_event_student`
				WHERE event_id = :eventId
				AND user_id IN ({$allIds})
			";

			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->execute();
		}
	}

	/**
	 * Assign files to an event
	 *
	 * @param $event
	 */
	private function createFiles($event) {
		$query = "
			INSERT IGNORE INTO `eotc_event_file` (event_id, storage_id)
			VALUES (:eventId, :storageId)
		";

		foreach ($event->getRamsFiles() as $currentFile) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":eventId", $event->getId());
			$statement->bindParam(":storageId", $currentFile->getId());
			$statement->execute();
		}
	}

	/**
	 * Find files for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findFiles($identifier) {
		return $this->manyManyRelation(array(
				'name' => 'eotc_event_file',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'event_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'storage_id',
					'key' => 'storage_id',
					'mapper' => new \Schoolbox\StorageMapper()
				),
			));
	}

	/**
	 * Deletes file relations if there are no remaining
	 * events in the series
	 *
	 * @param $event
	 * @return bool|void
	 */
	public function deleteFilesRelations($event) {
		$query = "
			DELETE FROM `eotc_event_file`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();
	}

	/**
	 * Many-many relation table, where $relationTable is a pseudo-mapping
	 * of column names
	 *
	 * @param array $relationTableDefinition
	 * @param array $join
	 * @internal param array $selected
	 * @return \Schoolbox\Collection
	 */
	public function manyManyRelation($relationTableDefinition = array(), $join = array()) {
		$query = "
			SELECT {$join['relation']['column']}
			FROM `{$relationTableDefinition['name']}`
			WHERE {$join['column']} = :value
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":value", $join['value']);
		$statement->execute();

		/* Find all the primary keys in the relation table and objectify
		 * them into a collection
		 */
		$ids = array();

		foreach ($statement as $row) {
			$ids[] = $row[$join['relation']['column']];
		}

		$allInstances = $join['relation']['mapper']->findByColumn($join['relation']['key'], $ids, $join['relation']['options']);

		return $allInstances;
	}
}