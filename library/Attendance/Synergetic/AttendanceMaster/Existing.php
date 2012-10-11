<?php
/**
 * Attendance_Synergetic_AttendanceMaster_Existing
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Attendance_Synergetic_AttendanceMaster_Existing extends Attendance_Synergetic_AttendanceMaster_Abstract {

	/**
	 * Hook method
	 */
	protected function setup() {
		$this->setAttendanceMasterSeq($this->findAttendanceMasterSeq($this->getClassCode(), $this->getAttendanceDate(), $this->getAttendancePeriod()));
	}

	/**
	 * Get the primary Attendance key associated with the selected class, date and period
	 *
	 * @param $classCode
	 * @param $attendanceDate
	 * @param $attendancePeriod
	 *
	 * @return int
	 */
	private function findAttendanceMasterSeq($classCode, $attendanceDate, $attendancePeriod) {
		$query = "
			SELECT AttendanceMasterSeq
			FROM SynergyOne.dbo.AttendanceMaster
			WHERE ClassCode='{$classCode}'
			AND AttendanceDate='{$attendanceDate} 00:00:00.000'
			AND AttendancePeriod='{$attendancePeriod}'
			ORDER BY AttendanceMasterSeq
		";

		$result = $this->getDatabase()->getRow($query);

		if (is_array($result)) {
			return $result['AttendanceMasterSeq'];
		}

		return -1;
	}
}
