<?php
/**
 * Calendar_Api_EventType_Abstract
 *
 * This is used to remember the calendar_connector id ($connectorId) because each event needs to know from whence it
 * came. This id is used to retrieve a particular event record from whatever source, to display event detail screen in
 * the Mobile version of the calendar (in Desktop, event details are rendered inline)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Api_EventType_Abstract implements Calendar_Api_EventType_Interface {

	/**
	 * The calendar_connector id from which this event was retrieved.
	 * This is used to create a functioning event detail screen in Mobile.
	 *
	 * @var string
	 */
	protected $connectorId = '';

	/**
	 * An instance of User
	 *
	 * @var int
	 */
	protected $user = null;

	/* Getters/Setters
	 */

	/**
	 * Set calendar_connector id
	 *
	 * @param string $connectorId
	 */
	public function setConnectorId($connectorId) {
		$this->connectorId = $connectorId;
	}

	/**
	 * Get calendar_connector id
	 *
	 * @return string
	 */
	public function getConnectorId() {
		return $this->connectorId;
	}

	/**
	 * Set user
	 *
	 * @param $user
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;

		return $this;
	}

	/**
	 * @return int
	 */
	protected function getUser() {
		return $this->user;
	}
}