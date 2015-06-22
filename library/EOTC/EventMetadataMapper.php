<?php
/**
 * EventMetadataMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


use Schoolbox\Collection;
use Schoolbox\Resource\Storage;
use Schoolbox\ResourceBooking\Booking;
use Schoolbox\User;
use Schoolbox\UserMapper;

class EventMetadataMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_metadata');
		$this->hasModel('\\EOTC\\EventMetadata');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'event_type_id',
			'property' => 'eventType',
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'status_id',
			'property' => 'status',
			'type' => self::TYPE_MODEL,
			'join' => 'status',
		));
		$this->hasMap(array(
			'column' => 'rams_required',
			'property' => 'ramsRequired',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'comment',
			'property' => 'comment',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'   => 'created_at',
			'property' => 'createdAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'updated_at',
			'property' => 'updatedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'   => 'deleted_at',
			'property' => 'deletedAt',
			'type'     => self::TYPE_DATETIME,
		));
		$this->hasJoin('status', array(
			'mapper'    => '\\EOTC\\EventStatusMapper',
			'this_key'  => 'status_id',
			'other_key' => 'id',
		));
	}

	/**
	 * Find out if a relation exists before inserting a new one
	 *
	 * @param Event $event
	 * @throws \Exception
	 * @return EventMetadata
     */
	public function findByEvent(Event $event) {
		$query = "
			SELECT metadata_id
			FROM `eotc_event_metadata`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->execute();

		$allMetadataIds = array();

		foreach($statement as $row) {
			$allMetadataIds[] = $row['metadata_id'];
		}

		/* Include the associated files as well
		 * Include the associated students as well
		 * Include the associated resource bookings as well
		 */
		if (count($allMetadataIds)) {
			$metadata = $this->findByColumn('id', $allMetadataIds)->first();
			$metadata->setRamsFiles($this->findFiles($metadata->getId()));
			$metadata->setStudentAttendees($this->findStudentAttendees($metadata->getId()));
			$metadata->setResourceBookings($this->findResourceBookings($metadata->getId()));

			return $metadata;
		}

		throw new \Exception('No metadata for this event exists.');
	}

	/**
	 * Custom save method
	 *
	 * Only save relations if one doesn't already exist
	 *
	 * @param Event $event
	 */
	public function saveRelation(Event $event) {
		$query = "
			INSERT IGNORE INTO `eotc_event_metadata` (event_id, metadata_id)
			VALUES (:eventId, :metadataId)
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->bindParam(":metadataId", $event->getMetadata()->getId());
		$statement->execute();
	}

	/**
	 * Assign files to an event
	 *
	 * @param EventMetadata $metadata
	 */
	public function createFiles($metadata) {
        $statement = $this->database->prepare("
			INSERT IGNORE INTO `eotc_metadata_file` (metadata_id, storage_id)
            SELECT :metadataId, `storage_id`
            FROM `storage`
            WHERE `hash` = :storageHash
		");

		foreach ($metadata->getRamsFiles() as $currentFile) {
            $statement->execute([
                'metadataId'  => $metadata->getId(),
                'storageHash' => $currentFile->getHash()
            ]);
		}
	}


	/**
	 * Find files for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|Storage\Model[]
	 */
	public function findFiles($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_metadata_file',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'metadata_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'storage_id',
					'key' => 'storage_id',
					'mapper' => \Schoolbox\Resource\Storage\Mapper::getInstance()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes file relations if there are no remaining
	 * events in the series
	 *
	 * @param EventMetadata $metadata
	 */
	public function deleteFilesRelations($metadata) {
		$query = "
			DELETE FROM `eotc_metadata_file`
			WHERE metadata_id = :metadataId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":metadataId", $metadata->getId());
		$statement->execute();
	}

	/**
	 * Assign staff to an event
	 *
	 * @param EventMetadata $metadata
	 */
	public function createStaffAttendees($metadata) {
		$query = "
			INSERT IGNORE INTO `eotc_metadata_staff` (metadata_id, user_id)
			VALUES (:metadataId, :userId)
		";

		foreach ($metadata->getStaffAttendees() as $currentStaff) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":metadataId", $metadata->getId());
			$statement->bindParam(":userId", $currentStaff->getId());
			$statement->execute();
		}
	}

	/**
	 * Find staff for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|User[]
	 */
	public function findStaffAttendees($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_metadata_staff',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'metadata_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'user_id',
					'key' => 'id',
					'mapper' => new UserMapper()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes staff attendee relations if there are no remaining
	 * events in the series
	 *
	 * @param EventMetadata $metadata
	 */
	public function deleteStaffAttendeesRelations($metadata) {
		$query = "
			DELETE FROM `eotc_metadata_staff`
			WHERE metadata_id = :metadataId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":metadataId", $metadata->getId());
		$statement->execute();
	}

	/**
	 * Assign students to an event
	 *
	 * @param EventMetadata $metadata
	 */
	public function createStudentAttendees($metadata) {
		$query = "
			INSERT IGNORE INTO `eotc_metadata_student` (metadata_id, user_id)
			VALUES (:metadataId, :userId)
		";

		foreach ($metadata->getStudentAttendees() as $currentStudent) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":metadataId", $metadata->getId());
			$statement->bindParam(":userId", $currentStudent->getId());
			$statement->execute();
		}
	}

	/**
	 * Add specific relations for a particular event
	 *
	 * @param EventMetadata $metadata
	 * @param array $ids
	 */
	public function addStudentAttendeesRelationsSpecific($metadata, $ids) {
		if (is_array($ids) && count($ids)) {
			$query = "
				INSERT IGNORE INTO `eotc_metadata_student` (metadata_id, user_id)
				VALUES (:metadataId, :userId)
			";

			foreach($ids as $currentId) {
				if (preg_match('/[0-9]+/', $currentId)) {
					$statement = $this->database->prepare($query);
					$statement->bindParam(":metadataId", $metadata->getId());
					$statement->bindParam(":userId", $currentId);
					$statement->execute();
				}
			}
		}
	}

	/**
	 * Find students for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|User[]
	 */
	public function findStudentAttendees($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_metadata_student',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'metadata_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'user_id',
					'key' => 'id',
					'mapper' => new UserMapper(),
					'options' => array(
						'order' => array(
							'lastname' => 'ASC'
						)
					)
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes student attendee relations if there are no remaining
	 * events in the series
	 *
	 * @param EventMetadata $metadata
	 */
	public function deleteStudentAttendeesRelations($metadata) {
		$query = "
			DELETE FROM `eotc_metadata_student`
			WHERE metadata_id = :metadataId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":metadataId", $metadata->getId());
		$statement->execute();
	}

	/**
	 * Delete specific relations for a particular event
	 *
	 * @param EventMetadata $metadata
	 * @param array $ids
	 */
	public function deleteStudentAttendeesRelationsSpecific($metadata, $ids) {
		if (is_array($ids) && count($ids)) {
			$allIds = array();

			foreach($ids as $currentId) {
				if (preg_match('/[0-9]+/', $currentId)) {
					$allIds[] = $currentId;
				}
			}

			$allIds = implode(',', $allIds);

			$query = "
				DELETE FROM `eotc_metadata_student`
				WHERE metadata_id = :metadataId
				AND user_id IN ({$allIds})
			";

			$statement = $this->database->prepare($query);
			$statement->bindParam(":metadataId", $metadata->getId());
			$statement->execute();
		}
	}

	/**
	 * Assign resource bookings to an event
	 *
	 * @param EventMetadata $metadata
	 */
	public function createResourceBookings($metadata) {
		$query = "
			INSERT IGNORE INTO `eotc_metadata_resourcebooking` (metadata_id, booking_id)
			VALUES (:metadataId, :bookingId)
		";

		foreach ($metadata->getResourceBookings() as $currentResourceBooking) {
			$statement = $this->database->prepare($query);
			$statement->bindParam(":metadataId", $metadata->getId());
			$statement->bindParam(":bookingId", $currentResourceBooking->getId());
			$statement->execute();
		}
	}

	/**
	 * Find resource bookings for a particular event
	 *
	 * @param int $identifier
	 * @return Collection|\Schoolbox\ResourceBooking\Booking\Model[]
	 */
	public function findResourceBookings($identifier) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_metadata_resourcebooking',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'metadata_id',
				'value' => $identifier,

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'booking_id',
					'key' => 'id',
					'mapper' => new \Schoolbox\ResourceBooking\Booking\Mapper()
				),
			)
		);

		return $manyManyRelation->find();
	}

	/**
	 * Deletes resource booking relations if there are no remaining
	 * events in the series
	 *
	 * @param EventMetadata $metadata
	 */
	public function deleteResourceBookingsRelations($metadata) {
		$query = "
			DELETE FROM `eotc_metadata_resourcebooking`
			WHERE metadata_id = :metadataId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":metadataId", $metadata->getId());
		$statement->execute();
	}

	/**
	 * Find all the events belonging to this metadata
	 * There should be only one
	 *
	 * @param EventMetadata $metadata
	 * @return Collection|Event[]
	 */
	public function findEventsForMetadata($metadata) {
		$manyManyRelation = new ManyManyRelation();
		$manyManyRelation->setup(
			array(
				'name' => 'eotc_event_metadata',
			),
			array(
				/* Find things to join to this...
				 */
				'column' => 'metadata_id',
				'value' => $metadata->getId(),

				/* ...using this relation, such that column = key
				 */
				'relation' => array(
					'column' => 'event_id',
					'key' => 'id',
					'mapper' => new \EOTC\EventMapper(),
				),
			)
		);

		return $manyManyRelation->find();
	}
}