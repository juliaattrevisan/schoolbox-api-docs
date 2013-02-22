<?php
/**
 * Calendar_Api_Config_Type_Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Type_Vcal extends Calendar_Api_Config_Abstract {

	/**
	 * A url to a vcal stream
	 *
	 * @var string
	 */
	private $url = '';

	/**
	 * All the perms associated with the vcal stream
	 *
	 * @var array
	 */
	private $perms = array();

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
	 * Set perms
	 *
	 * @param array $perms
	 */
	public function setPerms($perms) {
		$this->perms = $perms;
	}

	/**
	 * Get perms
	 *
	 * @return array
	 */
	public function getPerms() {
		return $this->perms;
	}
}
