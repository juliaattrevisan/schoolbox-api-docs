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
use Schoolbox\Calendar;


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
	 * A User object
	 *
	 * @var null
	 */
	protected $teacherInCharge = null;

	/**
	 * Hook method
	 *
	 * Initialise collections all over the place
	 */
	protected function setup() {
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
}