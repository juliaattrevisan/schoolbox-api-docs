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

// Ensure online attendance is enabled.
if (!Config::get('external_attendance')) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

/* This only works with the Synergetic connector.
 * An alternative to 404 would be errors:
 *  210 Cannot get attendance list. Please contact system administrator.
 *  169 Access to this module is denied
 *  19 You do not have permission to view this page.
 *
 * ...but none of those really fit the context as to why you'd be unable to access this place.
 */
if (\External\IConnector::EXTERNAL_TYPE_SYNERGETIC !== Config::get('external_type')) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

/* Synergetic has an External_Synergetic_Class which can spawn via the factory,
 * but it has a special method not defined in External_ClassInterface, breaking
 * its contract: getClass()
 *
 * This inevitably forces you to spawn it directly instead.
 * $externalClass = new External_Class();
 */
$externalClass = new External_Synergetic_Class();

/* This is a POST only page
 * the 3 params are required and we should go back if they are not.
 * $_POST['classCode'], $_POST['fileType'], $_POST['timetableGroup']
 *
 * we need to throw an error to inform the user of an issue, rather than redirect and them assume it succeeded
 *
 * There are other POST params but some of those have their own validation
 */
if (!isset($_POST['classCode'], $_POST['fileType'], $_POST['timetableGroup'])) {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}

$class = $externalClass->getClass($_POST['classCode'], $_SESSION['user'], null, null, $_POST['fileType']);

if (!$class) {
    error(ErrorMapper::ERROR_CLASS_CODE_NOT_FOUND);
}

// harden the check for $_POST['attendanceDate']
if (!isset($_POST['attendanceDate']) || !strlen($_POST['attendanceDate'])) {
    error(ErrorMapper::ERROR_PERIOD_NUMBER_INVALID);
}

// Selected date for which to deal with attendances
// Check that you are allowed to edit something in a particular date range
// (You cannot edit anything older than the previous week)

$attendanceDate = new Alaress_Date_Format($_POST['attendanceDate'], 'Y-m-d');
$attendanceDate->startOfDay();

$now = new Alaress_Date_Format(time(), 'U');

if ((int)$attendanceDate->getUsingFormat('W') < ((int)$now->getUsingFormat('W') - 1)) {
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
    // Attendance period must be numeric, otherwise... say it's in the past?
    // Just copying what was here before.
    if (!is_numeric(preg_replace('/[^0-9]+/', '', $attendancePeriod))) {
        error(ErrorMapper::ERROR_ATTENDANCE_PAST);
    }

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

    // Find period, so I can ensure I don't save something outside the class' time
    /** @var External_Synergetic_Attendance $attendanceHandler */
    $periodDefinition = $attendanceHandler->getAttendanceMaster()->findTimetablePeriod();
    $periodStart      = Alaress_Date_Format::newFromTimestamp(strtotime($periodDefinition['TimeFrom']))->getDatabaseTime();
    $timeTo           = strtotime((string)$periodDefinition['TimeTo']);
    $periodEnd        = Alaress_Date_Format::newFromTimestamp($timeTo);
    if (false === $timeTo) {
        /* If the period end is not defined (ie NULL), we are assuming that the period will end at the end of the day as
         * we can not determine the actual end of period
         */
        $periodEnd = $periodEnd->endOfDay();
    }
    $periodEnd = $periodEnd->getDatabaseTime();

    // Create everyone's attendance initially

    if (is_array($_POST['studentID'])) {
        // This is a pretty hollow shell of a class, it just contains the mssql instance and a query to insert/update

        $attendanceRecord = new Attendance_Synergetic_AttendanceRecord();

        // Then if you could do that, save each person

        $attending = $_POST['attending'] ?? [];

        foreach ($_POST['studentID'] as $i => $studentId) {
            // Create a new record

            $data = [
                'AttendanceMasterSeq' => $attendanceHandler->getAttendanceMaster()->getAttendanceMasterSeq(),
                'ID' => $studentId,
                // some schools purge these fields from the form, so we need to make sure they're not empty
                'PossibleAbsenceCode' => trim($_POST['PossibleAbsenceCode'][$i] ?? ''),
                'PossibleReasonCode' => trim($_POST['PossibleReasonCode'][$i] ?? ''),
                'PossibleDescription' => trim($_POST['PossibleReasonDescription'][$i] ?? ''),
                'AttendedFlag' => in_array($studentId, $attending) ? 0 : 1,

                /* dbo.wkAttendances lets the EarlyDepartureTime and LateArrivalTime be null
                 * so this basically means you're clearing these fields in our ui; but when you
                 * do that they're not being passed here which means they're never going to
                 * get to the db. We have to explicitly state them as default values
                 */
                'EarlyDepartureFlag' => 0,
                'EarlyDepartureTime' => null,
                'LateArrivalFlag' => 0,
                'LateArrivalTime' => null
            ];

            // Arrival/Departure for multi-period classes
            if ($_POST['LateArrivalTime'][$i] ?? false) {
                // if this period starts after the late arrival,
                // don't mark them as late
                $arrival     = Alaress_Date_Format::newFromTimestamp(strtotime(trim($_POST['LateArrivalTime'][$i])));
                $arrivalTime = $arrival->getDatabaseTime();

                /* periodEnd might actually be null from the synergetic stored proc.
                 * This probably means some kind of broken data or weird nonstandard configuration,
                 * but we interpret here as "this period never ends"
                 *
                 * This means you can't ever arrive after the period ends: it never does.
                 */
                if ($arrivalTime > $periodEnd) {
                    $data['AttendedFlag'] = 0;
                } elseif ($arrivalTime >= $periodStart) {
                    $data['LateArrivalFlag'] = 1;
                    $data['LateArrivalTime'] = $arrival->getDatabase();
                }
            }

            if ($_POST['EarlyDepartureTime'][$i] ?? false) {
                // if this period starts before the early departure,
                // don't mark them as leaving early
                $departure     = Alaress_Date_Format::newFromTimestamp(strtotime(trim($_POST['EarlyDepartureTime'][$i])));
                $departureTime = $departure->getDatabaseTime();
                if ($departureTime < $periodStart) {
                    $data['AttendedFlag'] = 0;
                } elseif ($departureTime <= $periodEnd) {
                    /* periodEnd might actually be null from the synergetic stored proc.
                     * This probably means some kind of broken data or weird nonstandard configuration,
                     * but we interpret here as "this period never ends"
                     *
                     * This means you can't ever arrive after the period ends: it never does.
                     */
                    $data['EarlyDepartureFlag'] = 1;
                    $data['EarlyDepartureTime'] = $departure->getDatabase();
                }
            }

            $attendanceRecord->update($data);
        }

        if (!$attendanceHandler->getAttendanceMaster()->save()) {
            error(ErrorMapper::ERROR_ATTENDANCE_SAVE);
        }
    }
}

$this->backToTheFuture(2);
