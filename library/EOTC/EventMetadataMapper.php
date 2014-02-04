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
			'type' => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column' => 'rams_required',
			'property' => 'ramsRequired',
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
	}

	/**
	 * Find out if a relation exists before inserting a new one
	 *
	 * @param Event $event
	 * @throws \Exception
	 * @return \Schoolbox\Collection
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

		if (count($allMetadataIds)) {
			return $this->findByColumn('id', $allMetadataIds);
		}

		throw new \Exception('No metadata for this event exists.');
	}

	/**
	 * Custom save method
	 *
	 * @param Event $event
	 * @return array|void
	 */
	public function saveRelation(Event $event) {
		$query = "
			INSERT INTO `eotc_event_metadata` (event_id, metadata_id)
			VALUES (:eventId, :metadataId)
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $event->getId());
		$statement->bindParam(":metadataId", $event->getMetadata()->getId());
		$statement->execute();
	}
} 