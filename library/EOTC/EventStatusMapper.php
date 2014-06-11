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

	/* These consts are referenced in numerous places but
	 * for the same context.
	 */
	const STATUS_DEFAULT = 1;

	const STATUS_DRAFT = 1;
	const STATUS_INPRINCIPLE = 2;
	const STATUS_RAMSAPPROVED = 3;
	const STATUS_RAMSCONFIRMED = 4;
	const STATUS_AWAITINGFINALAPPROVAL = 4;
	const STATUS_APPROVED = 5;
	const STATUS_REJECTED = 6;

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
		$exclusions = array(self::STATUS_APPROVED, self::STATUS_REJECTED);

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
	 * Get default status id
	 *
	 * @return int
	 */
	private function getDefaultStatusId() {
		return self::STATUS_DEFAULT;
	}

	/**
	 * Get rejected status id
	 *
	 * @return int
	 */
	private function getRejectedStatusId() {
		return self::STATUS_REJECTED;
	}
}