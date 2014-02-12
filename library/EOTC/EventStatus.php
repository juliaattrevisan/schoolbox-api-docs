<?php
/**
 * EventStatus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventStatus {

	/**
	 * Primary key
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * Name of the status
	 *
	 * @var int
	 */
	private $name = -1;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Determine whether this event is in Draft
	 *
	 * @return bool
	 */
	public function isDraft() {
		if ($this->getId() == 1) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether this event is In Principle
	 *
	 * @return bool
	 */
	public function isInPrinciple() {
		if ($this->getId() == 2) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether this event is RAMS Approved
	 *
	 * @return bool
	 */
	public function isRamsApproved() {
		if ($this->getId() == 3) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether this event is Final Approved
	 *
	 * @return bool
	 */
	public function isApproved() {
		if ($this->getId() == 4) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set id
	 *
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param int $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return int
	 */
	public function getName() {
		return $this->name;
	}
}