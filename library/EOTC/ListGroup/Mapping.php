<?php
/**
 * Mapping
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup;


class Mapping {

	/**
	 * Event List Group mapping
	 *
	 * @var array
	 */
	private $mapping = array(
		'Urgent',
		'Pending Final Review',
		'Pending RAMS Review',
		'Pending Initial Review',
		'Approved',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * The human-readable version of the name
	 *
	 * @param $index
	 * @return mixed
	 */
	public function getName($index) {
		return $this->mapping[$index];
	}

	/**
	 * Index of "Urgent" events
	 *
	 * @return int
	 */
	public function getUrgentIndex() {
		return 0;
	}

	/**
	 * Index of Pending Final Review events
	 *
	 * @return int
	 */
	public function getPendingFinalReviewIndex() {
		return 1;
	}

	/**
	 * Index of Pending Rams Review events
	 *
	 * @return int
	 */
	public function getPendingRamsReviewIndex() {
		return 2;
	}

	/**
	 * Index of Pending Initial Review events
	 *
	 * @return int
	 */
	public function getPendingInitialReviewIndex() {
		return 3;
	}

	/**
	 * Index of Pending Initial Review events
	 *
	 * @return int
	 */
	public function getApprovedIndex() {
		return 4;
	}

	/* Getters/Setters
	 */

	/**
	 * Get mapping
	 *
	 * @return array
	 */
	public function getMapping() {
		return $this->mapping;
	}
}