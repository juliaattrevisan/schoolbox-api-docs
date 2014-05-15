<?php
/**
 * CalendarEventType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;


class EventType {

	/**
	 * Event type id
	 *
	 * @var string
	 */
	private $id = '';

	/**
	 * Event type name
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * Colour of this event type
	 *
	 * @var string
	 */
	private $color = '';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

	/**
	 * Set color
	 *
	 * @param string $color
	 */
	public function setColor($color) {
		$this->color = $color;
	}

	/**
	 * Get color
	 *
	 * @return string
	 */
	public function getColor() {
		return $this->color;
	}

	/**
	 * Set id
	 *
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set type
	 *
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
}