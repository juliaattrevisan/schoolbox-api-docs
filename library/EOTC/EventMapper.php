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


	protected function mapping() {
		parent::mapping();

		$this->hasModel('\\EOTC\\Event');
	}

	/**
	 * Custom findById
	 *
	 * @param mixed $identifier
	 * @return object|Model
	 * @throws \Exception
	 */
	public function findById($identifier) {
		$eventItem = parent::findById($identifier);

		/* Accumulate the metadata for this event
		 */
		$eventItem->setMetadata($this->findMetadata($identifier));

		new \Alaress_Probe($eventItem);
	}

	/**
	 * Find metadata for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findMetadata($identifier) {
		$query = "
			SELECT metadata_id
			FROM `eotc_event_metadata`
			WHERE event_id = :eventId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":eventId", $identifier);
		$statement->execute();

		/* Find all the primary keys in the relation table and objectify
		 * them into a collection
		 */
		$ids = array();

		foreach ($statement as $row) {
			$ids[] = $row['metadata_id'];
		}

		$metadataMapper = new EventMetadataMapper();
		$allMetadata = $metadataMapper->findByColumn('id', $ids);

		return $allMetadata;
	}
} 