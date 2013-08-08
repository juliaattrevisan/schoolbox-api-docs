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

/* Dropdowns and such for each student
 */
$this->setBlock('student', 'absence_options', 'absence_options_block');
$this->setBlock('student', 'reason_options', 'reason_options_block');

/* This link only appears if the student is marked as absent (has the checkbox
 * ticked) as its purpose is to toggle the form into which you enter the details
 * regarding the absentee
 */
$this->setBlock('student', 'has_attendance_options', 'has_attendance_options_block');

/* This button only appears if the AttendanceMasterSeq existed, indicating
 * that the roll has been called and saved at least once
 */
$this->setBlock('student', 'has_update', 'has_update_block');

/* This button only appears if the AttendanceMasterSeq didn't exist, indicating
 * that the roll has not yet been called for this class, date and period
 */
$this->setBlock(TEMPLATE_content, 'has_save_all', 'has_save_all_block');

/* THE THREE MUSKETEERS
 */
if (count($this->arg) < 3) {
	error(147);
}

/* Get a class list which we'll match up manually with their attendance records
 * but only if this class exists
 */
$externalClass = new External_Synergetic_Class();
$class = $externalClass->getClass($this->arg[0]);

if (!$class) {
	error(148);
}

if (!strlen($this->arg[1])) {
	error(149);
}

if (!is_numeric(preg_replace('/[^0-9]+/', '', $this->arg[2]))) {
	error(150);
}

/* Selected date for which to deal with attendances
 * Check that you are allowed to edit something in a particular date range
 * (You cannot edit anything older than the previous week)
 */
$attendanceDate = new Alaress_Date_Format($this->arg[1], 'y-m-d');
$now = new Alaress_Date_Format(time(), 'U');

if ($attendanceDate->getUsingFormat('W')  < ($now->getUsingFormat('W')-1)) {
	error(151);
}

if ($attendanceDate->getUnixTimestamp() > $now->getUnixTimestamp()) {
	error(152);
}

/* Only teachers of this class (and superusers) can edit attendance records
 */
$groupHandler = new Group();

if (!in_array($_SESSION['sid'], $externalClass->getTeacherName($class['ClassCode'])) && !$groupHandler->isSuperuser()) {
	error(19);
}

/* Attendance Period
 */
$attendancePeriod = $this->arg[2];

/* Let's do it
 */
if ($class) {

	/* Get the attendance record for this class
	 */
	$attendanceHandler = new External_Synergetic_Attendance($class, $attendanceDate, $attendancePeriod, $_SESSION['FileYear'], $_SESSION['FileSemester'], MSSQL::getInstance());
	$attendanceHandler->findAttendanceRecords();

	/* If the roll has been marked before, just show the students that were originally
	 * in that roll. Otherwise load all the students in the class.
	 *
	 * Students could be added to a class and could unexpectedly make an
	 * appearance in history if you don't do this.
	 */
	if ($attendanceHandler->getAttendanceMaster()->hasAttendanceMasterSeq()) {
		$allStudents = $attendanceHandler->getStudents($attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq());
	}
	else {
		$allStudents = $externalClass->getStudents($class['ClassCode']);
	}

	/* Dish out each row
	 */
	if (is_array($allStudents) && count($allStudents)) {
		$userHandler = new User();

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

			/* Update button in absentee reasons form
			 */
			if ($attendanceHandler->hasAttendanceMasterSeq()) {
				$this->parse('has_update_block', 'has_update');
			}
			else {
				$this->setVar('has_update_block', '');
			}

			$this->parse('student_block', 'student', true);

			/* Clear children after parsing out the parent
			 */
			$this->setVar('absence_options_block', '');
			$this->setVar('reason_options_block', '');
			$this->setVar('has_attendance_options_block', '');
			$this->setVar('has_update_block', '');
		}

		/* Remove special save-all
		 */
		if ($attendanceHandler->hasAttendanceMasterSeq()) {
			$this->setVar('has_save_all_block', '');
		}
		else {
			$this->parse('has_save_all_block', 'has_save_all');
		}
	}
	else {
		$this->setVar('student_block', '');
		$this->setVar('has_save_all_block', '');
	}
}
else {
	error(148);

	$this->setVar('student_block', '');
	$this->setVar('has_save_all_block', '');
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
