<?php
/**
 * Attendance_Synergetic_AttendanceMaster_New
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Attendance_Synergetic_AttendanceMaster_New extends Attendance_Synergetic_AttendanceMaster_Abstract {

	/**
	 * Hook method
	 */
	protected function setup() {
		$class = $this->getClass();

		/* Default settings for a new AttendanceMaster record
		 */
		$data = array(
			'CreatedDate' => 'GETDATE()',
			'CreatedByID' => $_SESSION['id'],
			'ModifiedDate' => 'GETDATE()',
			'ModifiedByID' => $_SESSION['id'],
			'FileType' => 'A',
			'FileYear' => $_SESSION['FileYear'],
			'FileSemester' => $_SESSION['FileSemester'],
			'ClassCampus' => $class['classCampus'],
			'ClassCode' => $this->getClassCode(),
			'StaffID' => $_SESSION['id'],
			'AttendanceDate' => $this->getAttendanceDate() . ' 00:00:00.000',
			'AttendancePeriod' => $this->getAttendancePeriod(),

			/* Where do these come from?
			 */
			'AttendanceDateTimeFrom' => null,
			'AttendanceDateTimeTo' => null,

			/* These two methods can be refactored, they are virtually identical
			 */
			'AttendanceDayNumber' => $this->getTimetableDayNumber(),
			'TimetableGroup' => $this->getTimetableGroup(),

			'ClassCancelledFlag' => 0,
			'AttendanceOfficerModeFlag' => 0,
			'SystemProcessNumber' => 0,
		);

		$this->setData($data);
	}
}
