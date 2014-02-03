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

		/* Determine current status
		 */
		if ($eventItem->getMetadata()->count()) {
			$statusMapper = new EventStatusMapper();

			foreach($eventItem->getMetadata() as $currentMetadata) {
				try {
					$statusItem = $statusMapper->findById($currentMetadata->getStatus());
					$currentMetadata->setStatus($statusItem);
				}
				catch (\Exception $e) {
				}
			}
		}

		return $eventItem;
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
				'left' => 'event_id',
				'right' => 'metadata_id',
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
					'key' => 'id'
				),
			)
		);
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

		$metadataMapper = new EventMetadataMapper();
		$allMetadata = $metadataMapper->findByColumn($join['relation']['key'], $ids);

		return $allMetadata;
	}
}