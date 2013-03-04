<?php
/**
 * Calendar_Api_Config_Connector_Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Connector_Vcal extends Calendar_Api_Config_Connector_Abstract {

	/**
	 * A url to a vcal stream
	 *
	 * @var string
	 */
	private $url = '';

	/**
	 * Campus visibility for this stream
	 *
	 * @var array
	 */
	private $campus = array();

	/**
	 * Group visibility for this stream
	 *
	 * @var array
	 */
	private $groups = array();

	/**
	 * Render configs into admin screen
	 *
	 * @param $page
	 * @return mixed|void
	 */
	public function render($page) {
		$page->setVar(array(
			'CONNECTOR_ID' => $this->getId(),
			'CONNECTOR_CLASS' => $this->getConnectorClass(),
			'URL' => $this->getUrl()
		));

		$page->parse('has_multiple_options_block', 'has_multiple_options');

		/* Render event type options
		 */
		$this->renderEventType($page, $this->getLuEventType());

		/* Permissions for this connector
		 */
		$calendarHandler = new Calendar();

		/* Campus permissions
		 */
		$campusList = $calendarHandler->getCampusList();

		if (count($campusList) > 1) {
			$page->checkList('campus_list', $campusList, $this->getCampus(), 'configFragment');
		}
		else {
			$page->setVar('campus_list_block', $campusList[0]['CAMPUS_NAME']);
		}

		/* Group permissions
		 */
		$page->checkList('group_list', $calendarHandler->getGroupList(), $this->getGroups());
	}

	/* Getters/Setters
	 */

	/**
	 * Set url
	 *
	 * @param string $url
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Set campus
	 *
	 * @param array $campus
	 */
	public function setCampus($campus) {
		$this->campus = $campus;
	}

	/**
	 * Get campus
	 *
	 * @return array
	 */
	public function getCampus() {
		return $this->campus;
	}

	/**
	 * Set groups
	 *
	 * @param array $groups
	 */
	public function setGroups($groups) {
		$this->groups = $groups;
	}

	/**
	 * Get groups
	 *
	 * @return array
	 */
	public function getGroups() {
		return $this->groups;
	}
}
