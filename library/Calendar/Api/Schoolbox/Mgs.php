<?php
/**
 * Calendar_Api_Schoolbox_Mgs
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Schoolbox_Mgs extends Calendar_Api_Schoolbox_Default {

	/**
	 * Get student birthdays from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {

		/* Note that this uses an MSSQL connection
		 */
		$collection = $this->getCalendarHandler()->getStudentBirthdays($currentDay);

		return $collection;
	}
}
