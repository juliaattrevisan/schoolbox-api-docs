<?php
/**
 * EventTypeMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventTypeMapper extends Calendar\EventTypeMapper {

	/**
	 * A particular record in luEventType corresponding to EOTC
	 * events, specifically - we get this record by name
	 *
	 * @var string
	 */
	private $eotcName = 'EOTC';

	/**
	 * Find all the event types you can choose from
	 *
	 * @return object|\Schoolbox\Collection
	 * @throws Exception
	 */
	public function findAll() {
		$query = "
			SELECT {$this->getColumnString('t')}
			FROM `{$this->table}` t
			WHERE eventType = :name
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":name", $this->getEotcName());
		$statement->execute();

		$row = $statement->fetch();

		$collection = new \Schoolbox\Collection();

		/* Hydrate or die
		 */
		if ($row != false) {
			$collection->add($this->hydrate($row, 't'));

			return $collection;
		}

		throw new Exception('Type not found.');
	}

	/**
	 * Set eotc name
	 *
	 * @param string $eotcName
	 */
	private function setEotcName($eotcName) {
		$this->eotcName = $eotcName;
	}

	/**
	 * Get eotc name
	 *
	 * @return string
	 */
	private function getEotcName() {
		return $this->eotcName;
	}
}