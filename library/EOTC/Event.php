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


class Event extends CalendarEvent {

	/**
	 * Metadata for this event
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
	 * Constructor
	 */
	public function __construct() {
	}
}