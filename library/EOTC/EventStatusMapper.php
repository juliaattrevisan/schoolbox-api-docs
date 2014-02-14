<?php
/**
 * EventStatusMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventStatusMapper extends \Schoolbox\Mapper {

	/**
	 * Default status id
	 *
 	 * @var int
	 */
	private $defaultStatusId = 1;

	/**
	 * Rejected status id
	 *
	 * @var int
	 */
	private $rejectedStatusId = 5;

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_status');
		$this->hasModel('\\EOTC\\EventStatus');
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => self::TYPE_STRING,
		));
	}

	/**
	 * Find default status object
	 *
	 * @return object
	 */
	public function findDefaultStatus() {
		return $this->findById($this->getDefaultStatusId());
	}

	/**
	 * The list of statuses that can be assigned via permissions
	 */
	public function findPermissionStatuses() {
		$allStatuses = $this->findAll();

		/* Ignore Approved and Rejected statuses
		 */
		$exclusions = array(5, 6);

		$collection = new \Schoolbox\Collection();

		foreach($allStatuses as $currentStatus) {
			if (!in_array($currentStatus->getId(), $exclusions)) {
				$collection->add($currentStatus);
			}
		}

		return $collection;
	}

	/**
	 * Find the available statuses for a given event and its
	 * configuration - there should on be the current status,
	 * the next status, and Rejected
	 *
	 * @param $event
	 * @return \Schoolbox\Collection
	 */
	public function findAvailableStatuses($event) {
		try {
			$chain = new \EOTC\ListGroup\Chain($event);
			$nextStatusId = $chain->getResult()->getNextStatus();
		}
		catch (\Exception $e) {
		}

		/* Only three options at a time
		 */
		return $this->findByColumn('id', array(
			$event->getMetadata()->getStatus()->getId(),
			$this->getRejectedStatusId(),
			$nextStatusId,
		));
	}

	/* Getters/Setters
	 */

	/**
	 * Set default status id
	 * 
	 * @param int $defaultStatusId
	 */
	private function setDefaultStatusId($defaultStatusId) {
		$this->defaultStatusId = $defaultStatusId;
	}

	/**
	 * Get default status id
	 *
	 * @return int
	 */
	private function getDefaultStatusId() {
		return $this->defaultStatusId;
	}

	/**
	 * Set rejected status id
	 *
	 * @param int $rejectedStatusId
	 */
	private function setRejectedStatusId($rejectedStatusId) {
		$this->rejectedStatusId = $rejectedStatusId;
	}

	/**
	 * Get rejected status id
	 *
	 * @return int
	 */
	private function getRejectedStatusId() {
		return $this->rejectedStatusId;
	}
}