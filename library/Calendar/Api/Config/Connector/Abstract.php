<?php
/**
 * Calendar_Api_Config_Connector_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Api_Config_Connector_Abstract {

	/**
	 * Primary key
	 *
	 * @var int
	 */
	protected $id = -1;

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

	/**
	 * Method for uniquely rendering on admin screen
	 *
	 * @param $page
	 * @return mixed
	 */
	abstract public function render($page);

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
