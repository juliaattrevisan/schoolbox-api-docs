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
if (is_array($_POST['studentID'])) {

    /* This is a pretty hollow shell of a class, it just contains the mssql
     * instance and a query to insert/update
     */
    $attendanceRecord = new Attendance_Synergetic_AttendanceRecord(MSSQL::getInstance());

    /* Then if you could do that, save each person
     */
    for($i = 0; $_POST['studentID'][$i]; $i++) {

        /* Create a new record
         */
        $data = array(
            'AttendanceMasterSeq' => $attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq(),
            'ID' => $_POST['studentID'][$i],
            'PossibleAbsenceCode' => trim($_POST['PossibleAbsenceCode'][$i]),
            'PossibleReasonCode' => trim($_POST['PossibleReasonCode'][$i]),
            'PossibleDescription' => trim($_POST['PossibleReasonDescription'][$i]),
            'LateArrivalFlag' => trim($_POST['LateArrivalTime'][$i]) ? 1 : '',
            'LateArrivalTime' => trim($_POST['LateArrivalTime'][$i]),
            'EarlyDepartureFlag' => trim($_POST['EarlyDepartureTime'][$i]) ? 1 : '',
            'EarlyDepartureTime' => trim($_POST['EarlyDepartureTime'][$i]),
            'AttendedFlag' => $_POST['AttendedFlag'][$i],
        );

        $attendanceRecord->update($data);

    }

    $attendanceHandler->getAttendanceMaster()->save();
}
