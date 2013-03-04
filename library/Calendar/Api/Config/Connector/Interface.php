<?php
/**
 * Calendar_Api_Config_Connector_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Calendar_Api_Config_Connector_Interface {

	/**
	 * Method for uniquely rendering on admin screen
	 *
	 * $userCampus is an array of campus ids
	 * $userGroup is an int
	 *
	 * @param $userCampus
	 * @param $userGroup
	 * @return mixed
	 */
	public function hasPermissions($userCampus, $userGroup);

	/**
	 * Render this connector config
	 *
	 * @param $page
	 * @return mixed
	 */
	public function render($page);
}
