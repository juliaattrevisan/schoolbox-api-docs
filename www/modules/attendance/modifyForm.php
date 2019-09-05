<?php
/**
 * Allows the updating of attendance records in synergetic.
 *
 * @author        Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Error\Mapper as ErrorMapper;

// Ensure parameters are correct

$externalClass = new External_Synergetic_Class();
$groupHandler = new Group();

$class = $externalClass->getClass($_POST['classCode'], $_SESSION['user']);

if (!$class) {
    error(ErrorMapper::ERROR_CLASS_CODE_NOT_FOUND);
}

if (!strlen($_POST['attendanceDate'])) {
    error(ErrorMapper::ERROR_PERIOD_NUMBER_INVALID);
}

if (!is_numeric(preg_replace('/[^0-9]+/', '', $_POST['attendancePeriod']))) {
    error(ErrorMapper::ERROR_ATTENDANCE_PAST);
}

// Selected date for which to deal with attendances
// Check that you are allowed to edit something in a particular date range
// (You cannot edit anything older than the previous week)

$attendanceDate = new Alaress_Date_Format($_POST['attendanceDate'], 'Y-m-d');
$attendanceDate->startOfDay();

$now = new Alaress_Date_Format(time(), 'U');

if ($attendanceDate->getUsingFormat('W') < ($now->getUsingFormat('W') - 1)) {
    error(ErrorMapper::ERROR_ATTENDANCE_FUTURE);
}

if ($attendanceDate->getUnixTimeStamp() > $now->getUnixTimeStamp()) {
    error(ErrorMapper::ERROR_NOT_ENROLLED);
}

// Only teachers can mark attendance (we allow all staff, but synergetic may restrict this later aswell)

if (!$_SESSION['user']->getRole()->isStaff()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

// Validation check ends here

// Attendance Period

$attendancePeriods = $_POST['attendancePeriod'];
$attendanceDay = $_POST['attendanceDay'];
$timetableGroup = $_POST['timetableGroup'];

// Deal with it all

foreach ($attendancePeriods as $attendancePeriod) {
    try {

        $externalUserHandler = new External_User();

        $attendanceHandler = new External_Synergetic_Attendance(
            $class,
            $attendanceDate,
            $attendanceDay,
            $attendancePeriod,
            $externalUserHandler->getCurrentYear($_SESSION['user']->getExternalId()),
            $externalUserHandler->getCurrentSemester($_SESSION['user']->getExternalId()),
            $_SESSION['user']->getExternalId(),
            $timetableGroup
        );

    } catch (ExternalException $e) {
        error(ErrorMapper::ERROR_ATTENDANCE_LIST);
    }

// Create everyone's attendance initially

    if (is_array($_POST['studentID'])) {

        // This is a pretty hollow shell of a class, it just contains the mssql instance and a query to insert/update

        $attendanceRecord = new Attendance_Synergetic_AttendanceRecord();

        // Then if you could do that, save each person

        if (is_array($_POST['attending'])) {
            $attending = $_POST['attending'];
        } else {
            $attending = [];
        }

        for ($i = 0; $_POST['studentID'][$i]; $i++) {

            // Create a new record

            $data = [
                'AttendanceMasterSeq' => $attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq(),
                'ID' => $_POST['studentID'][$i],
                'PossibleAbsenceCode' => trim($_POST['PossibleAbsenceCode'][$i]),
                'PossibleReasonCode' => trim($_POST['PossibleReasonCode'][$i]),
                'PossibleDescription' => trim($_POST['PossibleReasonDescription'][$i]),
                'AttendedFlag' => in_array($_POST['studentID'][$i], $attending) ? 0 : 1,
            ];

            if ($_POST['LateArrivalTime'][$i]) {
                $data['LateArrivalFlag'] = 1;
                $data['LateArrivalTime'] = date("Y-m-d H:i:s", strtotime(trim($_POST['LateArrivalTime'][$i])));
            }

            if ($_POST['EarlyDepartureTime'][$i]) {
                $data['EarlyDepartureFlag'] = 1;
                $data['EarlyDepartureTime'] = date("Y-m-d H:i:s", strtotime(trim($_POST['EarlyDepartureTime'][$i])));
            }

            $attendanceRecord->update($data);
        }

        if (!$attendanceHandler->getAttendanceMaster()->save()) {
            error(ErrorMapper::ERROR_ATTENDANCE_SAVE);
        }
    }
}

$this->backToTheFuture(-1);
