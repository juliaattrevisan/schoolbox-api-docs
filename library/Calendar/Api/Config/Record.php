<?php
/**
 * Calendar_Api_Config_Record
 *
 * This corresponds to a row in the calendar_connector table and is used
 * by the Mapper class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Record {

	/**
	 * Primary key
	 *
	 * @var int
	 */
	public $id = -1;

	/**
	 * The kind of event this configuration is for
	 * e.g. 'Event', 'Submission'
	 *
	 * Public because of Mapper
	 *
	 * @var string
	 */
	public $eventType = '';

	/**
	 * Class name of the API which will use this config
	 * e.g. 'Schoolbox', 'Sharepoint'
	 *
	 * Public because of Mapper
	 *
	 * @var string
	 */
	public $connectorClass = '';

	/**
	 * Serialized configuration object
	 *
	 * @var string
	 */
	public $config = '';

	/* Getters/Setters
	 */

	/**
	 * @param string $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * @return string
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * Set connectorClass
	 *
	 * @param string $connectorClass
	 */
	public function setConnectorClass($connectorClass) {
		$this->connectorClass = $connectorClass;
	}

	/**
	 * Get connectorClass
	 *
	 * @return string
	 */
	public function getConnectorClass() {
		return $this->connectorClass;
	}

	/**
	 * Set config
	 *
	 * @param string $config
	 */
	public function setConfig($config) {
		$this->config = $config;
	}

	/**
	 * Get config
	 *
	 * @return string
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Set id
	 *
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}
}
