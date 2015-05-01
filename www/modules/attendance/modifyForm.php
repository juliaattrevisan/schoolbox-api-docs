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
$attendanceDate = new Alaress_Date_Format($_POST['attendanceDate'], 'Y-m-d');
$attendanceDate->startOfDay();

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

if (!in_array($_SESSION['sid'], $externalClass->getTeacherName($_POST['classCode'])) && !$groupHandler->isSuperuser()) {
	error(19);
}

/* Validation check ends here
 */

/* Attendance Period
 */
$attendancePeriod = $_POST['attendancePeriod'];
$attendanceDay = $_POST['attendanceDay'];

/* Deal with it all
 */
try {
    $externalUserHandler = new External_User();
    $attendanceHandler   = new External_Synergetic_Attendance(
        $class,
        $attendanceDate,
        $attendanceDay,
        $attendancePeriod,
        $externalUserHandler->getCurrentYear(),
        $externalUserHandler->getCurrentSemester(),
        MSSQL::getInstance()
    );
} catch(Exception $e) {
    error(148); //Class does not exist
}

/* Create everyone's attendance initially
 */
if (is_array($_POST['studentID'])) {

    /* This is a pretty hollow shell of a class, it just contains the mssql
     * instance and a query to insert/update
     */
    $attendanceRecord = new Attendance_Synergetic_AttendanceRecord(MSSQL::getInstance());

    /* Then if you could do that, save each person
     */

    if(is_array($_POST['attending'])) {
        $attending = $_POST['attending'];
    }
    else {
        $attending = array();
    }

    for($i = 0; $_POST['studentID'][$i]; $i++) {

        /* Create a new record
         */
        $data = array(
            'AttendanceMasterSeq' => $attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq(),
            'ID' => $_POST['studentID'][$i],
            'PossibleAbsenceCode' => trim($_POST['PossibleAbsenceCode'][$i]),
            'PossibleReasonCode' => trim($_POST['PossibleReasonCode'][$i]),
            'PossibleDescription' => trim($_POST['PossibleReasonDescription'][$i]),
            'AttendedFlag' => in_array($_POST['studentID'][$i], $attending) ? 0 : 1,
        );

        if($_POST['LateArrivalTime'][$i]) {
            $data['LateArrivalFlag'] = 1;
            $data['LateArrivalTime'] = date("Y-m-d H:i:s", strtotime(trim($_POST['LateArrivalTime'][$i])));
        }

        if($_POST['EarlyDepartureTime'][$i]) {
            $data['EarlyDepartureFlag'] = 1;
            $data['EarlyDepartureTime'] = date("Y-m-d H:i:s", strtotime(trim($_POST['EarlyDepartureTime'][$i])));
        }

        $attendanceRecord->update($data);

    }


    if(! $attendanceHandler->getAttendanceMaster()->save()) {
        error(148);
    }
}

$this->backToTheFuture(-1);