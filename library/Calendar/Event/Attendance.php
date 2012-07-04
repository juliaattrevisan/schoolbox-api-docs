<?php
/**
 * Calendar_Event_Attendance
 *
 * A class which manages event attendance. All of Alex's defined stuff in Calendar
 * should eventually be moved here, where possible.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Attendance {
	const ATTENDANCE_STATUS_PENDING = 1;
	const ATTENDANCE_STATUS_APPROVED = 2;
	const ATTENDANCE_STATUS_REJECTED = 3;

	/* An int telling you whether people can attend this event or not
	 */
	private $attendanceFlag = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
	}


	public function isPending() {
	}

	public function isApproved() {
	}

	public function isRejected() {
	}

	/* Getters/Setters
	 */

	/**
	 * Set attendance
	 *
	 * @param $attendanceFlag
	 */
	private function setAttendanceFlag($attendanceFlag) {
		$this->attendanceFlag = $attendanceFlag;
	}

	/**
	 * Get attendance
	 *
	 * @return string
	 */
	private function getAttendanceFlag() {
		return $this->attendanceFlag;
	}
}
