<?php
/**
 * Calendar_Api_Interface
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
interface Calendar_Api_Interface {

	/* Each method must return an instance of Calendar_Event_Collection
	 * containing Calender_Event_Type subclasses in their stacks.
	 */

	public function getDayEvents($currentDay);
	public function getStudentBirthdays($currentDay);
	public function getSubmissionEvents($currentDay);
}
