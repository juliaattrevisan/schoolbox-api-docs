<?php
/**
 * Calendar_Api_Config_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Api_Config_Abstract {

	/**
	 * The kind of event this configuration is for
	 * e.g. 'Event', 'Submission'
	 *
	 * Public because of Mapper
	 *
	 * @var string
	 */
	protected $eventType = '';

	/**
	 * Class name of the API which will use this config
	 * e.g. 'Schoolbox', 'Sharepoint'
	 *
	 * Public because of Mapper
	 *
	 * @var string
	 */
	protected $connectorClass = '';

	/* Getters/Setters
	 */

	/**
	 * Set event type
	 *
	 * @param string $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * Get event type
	 *
	 * @return string
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * Set connector class
	 *
	 * @param string $connectorClass
	 */
	public function setConnectorClass($connectorClass) {
		$this->connectorClass = $connectorClass;
	}

	/**
	 * Get connector class
	 *
	 * @return string
	 */
	public function getConnectorClass() {
		return $this->connectorClass;
	}
}
