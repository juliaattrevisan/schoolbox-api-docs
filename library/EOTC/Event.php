<?php
/**
 * Event
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class Event extends Calendar\Event {

	/**
	 * Metadata for this event
	 *
	 * An instance of EventMetadata
	 *
	 * @var null
	 */
	protected $metadata = null;

	/**
	 * A Collection of User objects
	 *
	 * @var null
	 */
	protected $studentAttendees = null;

	/**
	 * A Collection of User objects
	 *
	 * @var null
	 */
	protected $staffAttendees = null;

	/**
	 * A User object
	 *
	 * @var null
	 */
	protected $teacherInCharge = null;

	/**
	 * A collection of brownhole files
	 *
	 * @var null
	 */
	protected $ramsFiles = null;

	/**
	 * Hook method
	 *
	 * Initialise collections all over the place
	 */
	protected function setup() {
		$this->setStudentAttendees(new \Schoolbox\Collection());
		$this->setStaffAttendees(new \Schoolbox\Collection());
		$this->setMetadata(new \Schoolbox\Collection());
		$this->setRamsFiles(new \Schoolbox\Collection());
	}

	/* Getters/Setters
	 */

	/**
	 * Set metadata
	 *
	 * @param null $metadata
	 */
	public function setMetadata($metadata) {
		$this->metadata = $metadata;
	}

	/**
	 * Get metadata
	 *
	 * @return null
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Set staff attendees
	 *
	 * @param null $staffAttendees
	 */
	public function setStaffAttendees($staffAttendees) {
		$this->staffAttendees = $staffAttendees;
	}

	/**
	 * Get staff attendees
	 *
	 * @return null
	 */
	public function getStaffAttendees() {
		return $this->staffAttendees;
	}

	/**
	 * Set teacher in charge
	 *
	 * @param null $teacherInCharge
	 */
	public function setTeacherInCharge($teacherInCharge) {
		$this->teacherInCharge = $teacherInCharge;
	}

	/**
	 * Get teacher in charge
	 *
	 * @return null
	 */
	public function getTeacherInCharge() {
		return $this->teacherInCharge;
	}

	/**
	 * Set student attendees
	 *
	 * @param null $studentAttendees
	 */
	public function setStudentAttendees($studentAttendees) {
		$this->studentAttendees = $studentAttendees;
	}

	/**
	 * Get student attendees
	 *
	 * @return null
	 */
	public function getStudentAttendees() {
		return $this->studentAttendees;
	}

	/**
	 * Set rams files
	 *
	 * @param null $ramsFiles
	 */
	public function setRamsFiles($ramsFiles) {
		$this->ramsFiles = $ramsFiles;
	}

	/**
	 * Get rams files
	 *
	 * @return null
	 */
	public function getRamsFiles() {
		return $this->ramsFiles;
	}
}