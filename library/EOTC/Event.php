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
use Schoolbox\Collection;
use Schoolbox\User;


class Event extends Calendar\Event {

	/**
	 * Metadata for this event
	 *
	 * An instance of EventMetadata
	 *
	 * \EOTC\EventMetadata EventMetadata
	 */
	protected $metadata = null;

	/**
	 * A User object
	 *
	 * \Schoolbox\User User
	 */
	protected $teacherInCharge = null;

    /**
     * Publish Date
     *
     * \Alaress_Date_Format null
     */
    protected $publishDate = null;

	/**
	 * Hook method
	 *
	 * Initialise collections all over the place
	 */
	protected function setup() {
		$this->setMetadata(new EventMetadata());
	}

	/* Getters/Setters
	 */

	/**
	 * Set metadata
	 *
	 * @param EventMetadata $metadata
	 * @return self
	 */
	public function setMetadata($metadata) {
		$this->metadata = $metadata;
		return $this;
	}

	/**
	 * Get metadata
	 *
	 * @return EventMetadata
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * Set teacher in charge
	 *
	 * @param User $teacherInCharge
	 * @return self
	 */
	public function setTeacherInCharge($teacherInCharge) {
		$this->teacherInCharge = $teacherInCharge;
		return $this;
	}

	/**
	 * Get teacher in charge
	 *
	 * @return User
	 */
	public function getTeacherInCharge() {
		return $this->teacherInCharge;
	}

    /**
     * Set publish date
     *
     * @param mixed $publishDate
     */
    public function setPublishDate($publishDate)
    {
        $this->publishDate = $publishDate;
    }

    /**
     * Get publish date
     *
     * @return mixed
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }
}