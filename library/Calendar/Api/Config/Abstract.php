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
	 * @var string
	 */
	protected $eventType = '';

	/**
	 * Class name of the API which will use this config
	 * e.g. 'Schoolbox', 'Sharepoint'
	 *
	 * @var string
	 */
	protected $connector = '';

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
	 * @param string $connector
	 */
	public function setConnector($connector) {
		$this->connector = $connector;
	}

	/**
	 * @return string
	 */
	public function getConnector() {
		return $this->connector;
	}
}
