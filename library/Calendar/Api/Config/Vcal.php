<?php
/**
 * Calendar_Api_Config_Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Vcal extends Calendar_Api_Config_Abstract {

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

	/* Getters/Setters
	 */

	/**
	 * @param string $url
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @param array $perms
	 */
	public function setPerms($perms) {
		$this->perms = $perms;
	}

	/**
	 * @return array
	 */
	public function getPerms() {
		return $this->perms;
	}
}
