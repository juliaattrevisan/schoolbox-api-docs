<?php
/**
 * Helper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Helper {

	/**
	 * Get the current timetable period eg. 2013S2
	 */
	public function getCurrentTTPeriod() {
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$currentYear = $now->getUsingFormat('Y');
		$currentSemester = $this->getCurrentSemester();

		/* TODO: Hard code this for now
		 *
		 * CURRENT_SEMESTER is empty in the Maze db
		 */
		$currentSemester = 1;

		/* Only get subjects that are relevant now
		 */
		$ttPeriod = "{$currentYear}S{$currentSemester}";

		return $ttPeriod;
	}
} 