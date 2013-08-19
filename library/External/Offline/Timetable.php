<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Offline_Timetable implements External_TimetableInterface {
	public function getTimetableDefinition($campusId, $day = 0) {
		// TODO: Implement getTimetableDefinition() method.
	}

	public function getDayNumber($campusId, $date = 0) {
		// TODO: Implement getDayNumber() method.
	}

	public function getPeriodNumber($campusId) {
		// TODO: Implement getPeriodNumber() method.
	}

	public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber) {
		// TODO: Implement getDayTimetable() method.
	}

	public function getFullTimetable($externalUserId, $userId, $campusId) {
		// TODO: Implement getFullTimetable() method.
	}

	public function getDaySubjects($externalUserId, $dayNumber) {
		// TODO: Implement getDaySubjects() method.
	}

	public function getSubjectPeriod($classCode, $dayNumber) {
		// TODO: Implement getSubjectPeriod() method.
	}

	public function getSubjectName($classCode) {
		// TODO: Implement getSubjectName() method.
	}

}
