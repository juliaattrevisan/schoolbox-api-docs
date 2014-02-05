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
	 * Hook method
	 */
	protected function setup() {

		/* This is populated by hand
		 */
		$this->setMetadata(new \Schoolbox\Collection());
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
}