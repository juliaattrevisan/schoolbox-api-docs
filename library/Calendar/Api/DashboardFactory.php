<?php
/**
 * Calendar_Api_DashboardFactory
 *
 * This does two specific groups of calendar events
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_DashboardFactory extends Calendar_Api_Factory {

	/**
	 * Constructor
	 *
	 * Argument $type corresponds to "event" or "submission" for usage
	 * on the Dashboard
	 *
	 * @param $type
	 */
	public function __construct($type) {
		$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumn('event_type', $type);

		/* These configs are base64-encode()'d to get around a weird issue with unserialize
		 * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
		 */
		for ($allConfigs->rewind(); $allConfigs->valid(); $allConfigs->next()) {
			$this->addConfig($allConfigs->current()->decodeConfig());
		}
	}

}
