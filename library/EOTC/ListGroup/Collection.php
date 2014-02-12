<?php
/**
 * EventListGroup
 *
 * All the events whose statuses can be modified appear in a mega
 * Collection of Collections called Event List Group. These have
 * events corresponding to Pending Final Approval etc. on the
 * Review screen.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup;


class Collection extends \Schoolbox\Collection {

	/**
	 * A list of ids that have already been processed
	 *
	 * @var array
	 */
	private $processedSeriesIds = array();

	/**
	 * An instance of Mapping
	 *
	 * @var null
	 */
	private $mapping = null;

	/**
	 * No setup hook in \Schoolbox\Collection?
	 */
	public function setup() {
		$this->setMapping(new Mapping());

		foreach ($this->getMapping()->getMapping() as $current) {
			$this->add(new \Schoolbox\Collection());
		}
	}

	/**
	 * Assign an event to an Event List Group
	 *
	 * Ignore recurring instances and assign events to a collection
	 * based on the event's status id and RAMS requirements (or any
	 * other determination as defined in Chain)
	 *
	 * @param $event
	 */
	public function assign($event) {
		if (is_numeric($event->getSeriesId())) {
			if (!in_array($event->getSeriesId(), $this->getProcessedSeriesIds())) {
				$this->finish($event->getSeriesId());

				try {
					$chain = new PriorityChain($event);
					$location = $chain->getResult();

					$this->getOffset($location->getListGroupIndex())->add($event);
				}
				catch (\Exception $e) {
				}
			}
		}
		else {
			try {
				$chain = new PriorityChain($event);
				$location = $chain->getResult();

				$this->getOffset($location->getListGroupIndex())->add($event);
			}
			catch (\Exception $e) {
			}
		}
	}

	/**
	 * The name of the Event List Group currently being processed
	 *
	 * @return mixed
	 */
	public function getCurrentName() {
		return $this->getMapping()->getName($this->key());
	}

	/**
	 * Mark this series id as having been processed
	 *
	 * @param $seriesId
	 */
	private function finish($seriesId) {
		$this->processedSeriesIds[] = $seriesId;
	}

	/* Getters/Setters
	 */

	/**
	 * Set processed series ids
	 *
	 * @param array $processedSeriesIds
	 */
	private function setProcessedSeriesIds($processedSeriesIds) {
		$this->processedSeriesIds = $processedSeriesIds;
	}

	/**
	 * Get processed series ids
	 *
	 * @return array
	 */
	private function getProcessedSeriesIds() {
		return $this->processedSeriesIds;
	}

	/**
	 * Set mapping
	 *
	 * @param null $mapping
	 */
	private function setMapping($mapping) {
		$this->mapping = $mapping;
	}

	/**
	 * Get mapping
	 *
	 * @return null
	 */
	private function getMapping() {
		return $this->mapping;
	}
}