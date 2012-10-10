<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$this->setTitle('Student Attendance');
$this->setFile(TEMPLATE_content, 'modules/attendance/modify.html');
$this->setBlock(TEMPLATE_content, 'student', 'student_block');
$this->setBlock('student', 'absence_options', 'absence_options_block');
$this->setBlock('student', 'reason_options', 'reason_options_block');
$this->setBlock('student', 'has_attendance_options', 'has_attendance_options_block');

/* TODO: Figger out a way to get this value from somewhere, like a timetable
 */
$attendancePeriod = 6;

/* Selected date for which to deal with attendances
 */
if (strlen($this->arg[1])) {
	$attendanceDate = new Alaress_Date_Format($this->arg[1], 'y-m-d');
}
else {
	$attendanceDate = new Alaress_Date_Format(time(), 'U');
}

/* Get a class list which we'll match up manually with their attendance records
 * but only if this class exists
 */
$externalClass = new External_Synergetic_Class();
$class = $externalClass->getClass($this->arg[0]);

if ($class) {
	$allStudents = $externalClass->getStudents($class['ClassCode']);

	/* Dish out each row
	 */
	if (is_array($allStudents) && count($allStudents)) {
		$userHandler = new User();

		/* Get the attendance record for this class
		 */
		$attendanceHandler = new External_Synergetic_Attendance($class, $attendanceDate, $attendancePeriod, new MSSQL());
		$attendanceHandler->findAttendanceRecords();

		/* Dropdown options
		 */
		$absenceDropdownOptions = $attendanceHandler->findAbsenceDropdownOptions();
		$reasonDropdownOptions = $attendanceHandler->findReasonDropdownOptions();

		/* Iterate each guy
		 */
		foreach($allStudents as $currentStudent) {
			$user = $userHandler->getBySynergy($currentStudent['synergy_id']);

			/* Ram in their attendance record as well
			 */
			$attendanceRecord = $attendanceHandler->getAttendanceRecord($currentStudent['synergy_id']);

			/* Default setting for the checkbox and Edit link
			 */
			if (is_array($attendanceRecord) && ($attendanceRecord['AttendedFlag'] == 0)) {
				$isAbsent = "checked='checked'";

				$this->setVar(array(
					'STUDENT_SYNERGY_ID' => $currentStudent['synergy_id']
				));

				$this->parse('has_attendance_options_block', 'has_attendance_options');
			}
			else {
				$isAbsent = '';

				/* Clear this as we don't want it to display anything
				 */
				$attendanceRecord = array();

				$this->setVar('has_attendance_options_block', '');
			}

			/* Replace everything with everything
			 */
			$this->setVar(array(
				'STUDENT_UID' => $user['uid'],
				'STUDENT_FIRSTNAME' => $currentStudent['firstname'],
				'STUDENT_LASTNAME' => $currentStudent['lastname'],
				'STUDENT_SYNERGY_ID' => $currentStudent['synergy_id'],
				'STUDENT_ABSENCE_CODE' => $attendanceRecord['PossibleAbsenceCode'],
				'STUDENT_REASON_CODE' => $attendanceRecord['PossibleReasonCode'],
				'STUDENT_REASON_DESCRIPTION' => Scrub::text($attendanceRecord['PossibleReasonDescription']),
				'IS_ABSENT' => $isAbsent,
				'ON_OFF' => ++$i%2 ? 'dark' : '',
			));

			/* Render dropdown options individually
			 */
			foreach($absenceDropdownOptions as $currentOption) {

				/* Preselect
				 */
				if ($currentOption['Code'] == $attendanceRecord['PossibleAbsenceCode']) {
					$isSelected = "selected='selected'";
				}
				else {
					$isSelected = '';
				}

				$this->setVar(array(
					'ABSENCE_OPTIONS_VALUE' => $currentOption['Code'],
					'ABSENCE_OPTIONS_DESCRIPTION' => $currentOption['Description'],
					'ABSENCE_OPTIONS_SELECTED' => $isSelected,
				));

				$this->parse('absence_options_block', 'absence_options', true);
			}

			/* Render dropdown options individually
			 */
			foreach($reasonDropdownOptions as $currentOption) {

				/* Preselect
				 */
				if ($currentOption['Code'] == $attendanceRecord['PossibleReasonCode']) {
					$isSelected = "selected='selected'";
				}
				else {
					$isSelected = '';
				}

				$this->setVar(array(
					'REASON_OPTIONS_VALUE' => $currentOption['Code'],
					'REASON_OPTIONS_DESCRIPTION' => $currentOption['Description'],
					'REASON_OPTIONS_SELECTED' => $isSelected,
				));

				$this->parse('reason_options_block', 'reason_options', true);
			}

			$this->parse('student_block', 'student', true);

			/* Clear children after parsing out the parent
			 */
			$this->setVar('absence_options_block', '');
			$this->setVar('reason_options_block', '');
			$this->setVar('has_attendance_options_block', '');
		}
	}
	else {
		$this->setVar('student_block', '');
	}
}
else {
	$this->setVar('student_block', '');
}

/* Synthesise a javascript cure!
 */
$this->setVar(array(
	'JS_CLASS_CODE' => $class['ClassCode'],
	'JS_ATTENDANCE_DATE' => $attendanceDate->getDatabaseDate(),
	'JS_ATTENDANCE_PERIOD' => $attendancePeriod,
));

/* Cosmetic makeup for the masses
 */
$this->setVar(array(
	'CLASS_CODE' => $class['ClassCode'],
	'ATTENDANCE_DATE' => $attendanceDate->getDatabaseDate(),
	'ATTENDANCE_DATE_FORMATTED' => $attendanceDate->getDateOnly(),
	'ATTENDANCE_PERIOD' => $attendancePeriod,
));

