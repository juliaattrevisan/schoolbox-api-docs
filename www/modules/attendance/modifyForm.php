<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$externalClass = new External_Synergetic_Class();

/* Hardcoded test data
 */
$class = $externalClass->getClass($_POST['classCode']);
$attendanceDate = new Alaress_Date_Format($_POST['attendanceDate'], 'y-m-d');

/* TODO: Attendance Period
 * Figger out how to figger this out
 */
$attendancePeriod = 6;

/* Return url once non-ajax updates are done
 */
$redirect = '/modules/attendance/modify/' . $class['ClassCode'] . '/' . $attendanceDate->getDatabaseDate() . '/' . $attendancePeriod;

/* Deal with it all
 */
$attendanceHandler = new External_Synergetic_Attendance($class, $attendanceDate, $attendancePeriod, new MSSQL());
$now = new Alaress_Date_Format(time(), 'U');

/* Create/update everyone's attendance initially
 */
if (isset($_POST['updateAll']) && ($_POST['updateAll'] == 'true')) {

	/* TODO: Batch Saving
	 * Save a record for everyone the first time you mark the roll
	 * After that, just hide this button
	 */
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

		$attendanceHandler->update($data);

		SitePage::localRedir($redirect);
	}
	/* Create a new record
	 */
	else {
		$data = array(
			'ID' => $_POST['synergy_id'],
			'PossibleAbsenceCode' => Scrub::text(trim($_POST['PossibleAbsenceCode'])),
			'PossibleReasonCode' => Scrub::text(trim($_POST['PossibleReasonCode'])),
			'PossibleDescription' => Scrub::text(trim($_POST['PossibleReasonDescription'])),
			'AttendedFlag' => 0,
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

		$attendanceHandler->create($data);

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

		$attendanceHandler->update($data);
	}
}