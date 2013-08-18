<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

/* THE THREE MUSKETEERS
 */
$externalClass = new External_Synergetic_Class();
$class = $externalClass->getClass($_POST['classCode']);

if (!$class) {
	error(148);
}

if (!strlen($_POST['attendanceDate'])) {
	error(149);
}

if (!is_numeric(preg_replace('/[^0-9]+/', '', $_POST['attendancePeriod']))) {
	error(150);
}

/* Selected date for which to deal with attendances
 * Check that you are allowed to edit something in a particular date range
 * (You cannot edit anything older than the previous week)
 */
$attendanceDate = new Alaress_Date_Format($_POST['attendanceDate'], 'y-m-d');
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

/* Validation check ends here
 */

/* Attendance Period
 */
$attendancePeriod = $_POST['attendancePeriod'];

/* Return url once non-ajax updates are done
 */
$redirect = '/modules/attendance/modify/' . $class['ClassCode'] . '/' . $attendanceDate->getDatabaseDate() . '/' . $attendancePeriod;

/* Deal with it all
 */
$attendanceHandler = new External_Synergetic_Attendance($class, $attendanceDate, $attendancePeriod, $_SESSION['FileYear'], $_SESSION['FileSemester'], MSSQL::getInstance());

/* Create everyone's attendance initially
 */
if (isset($_POST['updateAll']) && ($_POST['updateAll'] == 'true')) {

	/* Save a record for everyone the first time you mark the roll
	 * After that, just hide this button
	 */
	if (is_array($_POST['data']) && count($_POST['data'])) {

		/* First generate an AttendanceMasterSeq value by ramming in a new record
		 */
		$attendanceHandler->getAttendanceMaster()->insertStub();

		/* This is a pretty hollow shell of a class, it just contains the mssql
		 * instance and a query to insert/update
		 */
		$attendanceRecord = new Attendance_Synergetic_AttendanceRecord(MSSQL::getInstance());

		/* Then if you could do that, save each person
		 */
		foreach($_POST['data'] as $currentPerson) {

			/* Create a new record
			 */
			$data = array(
				'AttendanceMasterSeq' => $attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq(),
				'ID' => Scrub::text($currentPerson['externalId']),
				'PossibleAbsenceCode' => Scrub::text(trim($currentPerson['PossibleAbsenceCode'])),
				'PossibleReasonCode' => Scrub::text(trim($currentPerson['PossibleReasonCode'])),
				'PossibleDescription' => Scrub::text(trim($currentPerson['PossibleReasonDescription'])),
				'AttendedFlag' => $currentPerson['AttendedFlag'],
				'ModifiedByID' => $_SESSION['userID'],

				/* I don't know what these really do but these seem to be
				 * the usual default values
				 */
				'UserFlag1' => 0,
				'UserFlag2' => 0,
				'UserFlag3' => 0,
				'UserFlag4' => 0,
				'UserFlag5' => 0,
				'LateArrivalFlag' => 0,
				'LatearrivalTime' => null,
				'EarlyDepartureFlag' => 0,
				'EarlyDepartureTime' => null,
				'AbsenceEventsSeq' => 0,
				'NonAttendCreatedAbsenceEventsFlag' => 0
			);

			$attendanceRecord->create($data);
		}
	}
}
/* Update an individual person's attendance details individually
 *
 * This is supposed to happen only when altering the contents for
 * someone who's already marked absent
 *
 * Find out if they already have one in the tAttendances table;
 * if they do, just update it - otherwise create a new one
 */
else if (isset($_POST['updateOne']) && is_numeric($_POST['updateOne'])) {
	$existingRecord = $attendanceHandler->existingRecord($_POST['updateOne']);

	/* Update what's already in there, leaving other settings untarnished
	 * You'll need to scrub to allow double-quotes (mssql seems to hate those)
	 */
	if (is_array($existingRecord) && isset($existingRecord['AttendanceSeq']) && is_numeric($existingRecord['AttendanceSeq'])) {
		$data = $existingRecord;
		$data['PossibleAbsenceCode'] = Scrub::text(trim($_POST['PossibleAbsenceCode']));
		$data['PossibleReasonCode'] = Scrub::text(trim($_POST['PossibleReasonCode']));
		$data['PossibleDescription'] = Scrub::text(trim($_POST['PossibleReasonDescription']));
		$data['AttendedFlag'] = 0;
		$data['ModifiedByID'] = $_SESSION['id'];

		/* This is a pretty hollow shell of a class, it just contains the mssql
		 * instance and a query to insert/update
		 */
		$attendanceRecord = new Attendance_Synergetic_AttendanceRecord(MSSQL::getInstance());
		$attendanceRecord->update($data);

		SitePage::localRedir($redirect);
	}
}
/* Mark this person as present (non-absent)
 * Should occur when a checkbox went from ticked to unticked states.
 */
else if (isset($_POST['present']) && is_numeric($_POST['externalId'])) {
	$existingRecord = $attendanceHandler->existingRecord($_POST['externalId']);

	/* Update what's already in there, leaving other settings untarnished
	 */
	if (is_array($existingRecord) && isset($existingRecord['AttendanceSeq']) && is_numeric($existingRecord['AttendanceSeq'])) {
		$data = $existingRecord;
		$data['PossibleDescription'] = Scrub::text($data['PossibleDescription']);
		$data['AttendedFlag'] = 1;

		/* TODO: Clear Existing Data
		 * I don't know if you want to clear out whatever was in the PossibleAbsenceCode,
		 * PossibleDescription and PossibleReasonCode if someone who was formerly marked
		 * Absent gets put back into Present. At the moment it's non-destructive so you
		 * can "undo" an accidental change in state.
		 *
		 * If you want to ditch it all, just set these three fields to empty string.
		 */

		/* This is a pretty hollow shell of a class, it just contains the mssql
		 * instance and a query to insert/update
		 */
		$attendanceRecord = new Attendance_Synergetic_AttendanceRecord(MSSQL::getInstance());
		$attendanceRecord->update($data);
	}
}