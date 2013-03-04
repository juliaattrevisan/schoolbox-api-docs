<?php
/**
 * Calendar_Api_Config_Connector_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Api_Config_Connector_Abstract implements Calendar_Api_Config_Connector_Interface {

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
	 * Corresponds to the luEventType table
	 *
	 * @var string
	 */
	protected $luEventType = '1';

	/**
	 * Whether or not the current user can view these events
	 * Setting this to False prevents event accumulation
	 *
	 * @var bool
	 */
	protected $hasPermissions = false;

	/**
	 * Render event type lookup selector with the currently chosen
	 * value preselected
	 *
	 * @param $page
	 * @param $preselected
	 */
	protected function renderEventType($page, $preselected) {
		$eventHandler = new Calendar();

		$allLookupEventTypes = $eventHandler->getTypeList();

		if (is_array($allLookupEventTypes) && count($allLookupEventTypes)) {
			foreach($allLookupEventTypes as $currentLookupEventType) {
				if ($currentLookupEventType['eventTypeID'] == $preselected) {
					$selected = 'selected="selected"';
				}
				else {
					$selected = '';
				}

				$page->setVar(array(
					'LUEVENTTYPE_VALUE' => $currentLookupEventType['eventTypeID'],
					'LUEVENTTYPE_NAME' => $currentLookupEventType['eventType'],
					'LUEVENTTYPE_SELECTED' => $selected,
				));

				$page->parse('luEventType_block', 'luEventType', true);
			}
		}
		else {
			$page->setVar('luEventType_block', '');
		}
	}


	/**
	 * Find out if the current user's settings of $userCampus and $userGroup
	 * are in the selections for this config
	 *
	 * @param $userCampus
	 * @param $userGroup
	 * @return bool
	 */


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

	/**
	 * Set luEventType
	 *
	 * @param string $luEventType
	 */
	public function setLuEventType($luEventType) {
		$this->luEventType = $luEventType;
	}

	/**
	 * Get luEventType
	 *
	 * @return string
	 */
	public function getLuEventType() {
		return $this->luEventType;
	}
}
