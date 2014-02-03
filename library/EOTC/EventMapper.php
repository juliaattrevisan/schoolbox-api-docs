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

		return $eventItem;
	}

	/**
	 * Many-many relation table
	 *
	 *        $relationTable = 'eotc_event_metadata'
	 *
	 * Value is optional; one of the two columns must have it defined
	 *
	 *        $left = array('column' => 'event_id', 'value' = 1)
	 *        $right = array('column => 'metadata_id', 'value' = 1)
	 *
	 * @param $relationTable
	 * @param array $left
	 * @param array $right
	 * @return \Schoolbox\Collection
	 */
	public function manyManyRelation($relationTable, $left = array(), $right = array()) {
		$query = "
			SELECT {$right['column']}
			FROM `{$relationTable}`
		";

		if (isset($left['value'])) {
			$query .= "WHERE {$left['column']} = :value";

			$selectedValue = $left['value'];
			$column = $right['column'];
		}
		else if (isset($right['value'])) {
			$query .= "WHERE {$right['column']} = :value";

			$selectedValue = $right['value'];
			$column = $left['column'];
		}

		$statement = $this->database->prepare($query);
		$statement->bindParam(":value", $selectedValue);
		$statement->execute();

		/* Find all the primary keys in the relation table and objectify
		 * them into a collection
		 */
		$ids = array();

		foreach ($statement as $row) {
			$ids[] = $row[$column];
		}

		$metadataMapper = new EventMetadataMapper();
		$allMetadata = $metadataMapper->findByColumn('id', $ids);

		return $allMetadata;
	}

	/**
	 * Find metadata for a particular event
	 *
	 * @param $identifier
	 * @return \Schoolbox\Collection
	 */
	private function findMetadata($identifier) {
		return $this->manyManyRelation(
			'eotc_event_metadata',
			array(
				'column' => 'event_id',
				'value' => $identifier
			),
			array(
				'column' => 'metadata_id',
			)
		);
	}
} 