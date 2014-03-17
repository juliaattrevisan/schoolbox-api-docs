<?php
/**
 * Mapping
 *
 * The ints used in getUrgentIndex() etc. don't necessarily correspond
 * to the consts defined in EventStatusMapper. These ints are used purely
 * within this class
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
		'Pending Awaiting Final Approval',
		'Pending Initial Application Review',
		'Pending RAMS Review',
		'Approved',
		'Rejected',
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
	 * Index of Awaiting Final Approval events
	 *
	 * @return int
	 */
	public function getPendingAwaitingFinalApprovalReviewIndex() {
		return 1;
	}

	/**
	 * Index of Pending Final Review events
	 *
	 * @return int
	 */
	public function getPendingInPrincipleReviewIndex() {
		return 2;
	}

	/**
	 * Index of Pending Rams Review events
	 *
	 * @return int
	 */
	public function getPendingRamsReviewIndex() {
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

	/**
	 * Index of Pending Initial Review events
	 *
	 * @return int
	 */
	public function getRejectedIndex() {
		return 5;
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