<?php
/**
 * Allows the updating of attendance records in synergetic.  This page is used to display the input form with a
 * list of students, options for attendance storing
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\Config\Loader as Config;
use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

/**
 * Parse date/time strings to get the time only
 *
 * @param string $input
 * @return string
 */
function parseDateTimeString($input)
{
    if (empty(trim($input))) {
        return '';
    }

    $timestamp = strtotime($input);

    if (!empty($timestamp)) {
        return date("g:ia", $timestamp);
    }

    error(ErrorMapper::ERROR_DATE_FORMAT);
}

// Redir if $this doesn't exist (a.k.a this was accessed with a .php extension)
if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/modules/attendance/modify');
}

/** @var SitePage $this */

// Ensure online attendance is enabled.
/** @var IConfigLoader $configLoader */
$configLoader = $this->getDi()->get('config');
if (!$configLoader->get('external_attendance')) {
    $this->pageNotFound();
}

// Ensure correct parameter count
if (count($this->arg) < 3) {
    $this->error(ErrorMapper::ERROR_CLASS_CODE_DATE_PERIOD);
}

// Finagle path parameters
[$classCampus, $classCode, $date, $attendancePeriods, $timetableGroup, $fileType] = $this->arg;
$classCode         = urldecode(urldecode(urldecode($classCode)));
$attendancePeriods = explode(',', $attendancePeriods);

// Finagle PHPLib
$this->setTitle('Student Attendance');
$this->setFile(TEMPLATE_content, 'modules/attendance/modify.html');
$this->setBlock(TEMPLATE_content, 'student', 'student_block');
$this->setBlock(TEMPLATE_content, 'attendance_period', 'attendance_period_block');

// Dropdowns and such for each student

$this->setBlock('student', 'student_absent', 'student_absent_block');
$this->setBlock('student', 'absence_options', 'absence_options_block');
$this->setBlock('student', 'reason_options', 'reason_options_block');
$this->setBlock('student', 'flags', 'flags_block');

// Setup handlers
$groupHandler = new Group();
$externalClass = new External_Synergetic_Class();
$externalTimetable = new External_Timetable();
$externalUserHandler = new External_User();

/* Manufacture a specific class construct
 */
$class= [
    'FileType' => $fileType,
    'ClassCampus' => $classCampus,
    'ClassCode' => $classCode,
];

if (!$class) {
    error(ErrorMapper::ERROR_CLASS_CODE_NOT_FOUND);
}

if (!strlen($date)) {
    $this->error(ErrorMapper::ERROR_DATE_FORMAT);
}

foreach ($attendancePeriods as $attendancePeriod) {
    if ($attendancePeriod < 0 || $attendancePeriod > 100 || !preg_match('/^\d+$/', $attendancePeriod)) {
        $this->error(ErrorMapper::ERROR_PERIOD_NUMBER_INVALID);
    }
}

// Selected date for which to deal with attendances check that you are allowed to edit something in a particular date range (You cannot edit anything
// older than the previous week)

$attendanceDate = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
if (!$attendanceDate->isValid()) {
    $this->error(ErrorMapper::ERROR_DATE_FORMAT);
}
$attendanceDate->startOfDay();

$now = Alaress_Date_Format::now();
$now->endOfDay();

$days = $configLoader->get('external_attendance_days');

// If not today and not within days range
if ($attendanceDate->diffInDays($now) != 0 && $attendanceDate->diffInDays($now, false) > $days) {
    $this->error(ErrorMapper::ERROR_ATTENDANCE_PAST);
}

if ($attendanceDate->gt($now)) {
    $this->error(ErrorMapper::ERROR_ATTENDANCE_FUTURE);
}

// Only teachers can mark attendance (we allow all staff, but synergetic may restrict this later aswell)

if (!$_SESSION['user']->getRole()->isStaff()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

// Attendance Periods
$firstPeriod = (int)reset($attendancePeriods);
$lastPeriod  = (int)end($attendancePeriods);

// Check selected campus is one of users campus

try {
    /** @var Campus $selectedCampus */
    $selectedCampus = \Schoolbox\CampusMapper::getInstance()->findOneByColumn('ext_timetable_code', $timetableGroup);
} catch (MapperException $e) {
    error(ErrorMapper::ERROR_CAMPUS_CODE);
}

// Get timetable definition

$timetableDay = $externalTimetable->getDayNumber($selectedCampus, $attendanceDate->getUnixTimeStamp());

$definition = $externalTimetable->getTimetableDefinition(
    null,
    $selectedCampus,
    $timetableDay
);

$firstPeriodNumber = array_search($firstPeriod, $definition['number'], true);
$lastPeriodNumber  = array_search($lastPeriod, $definition['number'], true);
$startTime   = $definition['start'][$firstPeriodNumber];
$endTime     = $definition['end'][$lastPeriodNumber];
$periodNames = array_unique([$definition['desc'][$firstPeriodNumber], $definition['desc'][$lastPeriodNumber]]);

if ($class) {

    // Cosmetic makeup for the masses

    $this->setVar([
        'FILETYPE' => $fileType,
        'CLASS_CODE' => $class['ClassCampus'] . '/' . $class['ClassCode'],
        'ATTENDANCE_START' => $startTime,
        'ATTENDANCE_END' => $endTime,
        'ATTENDANCE_DATE' => $attendanceDate->getDatabaseDate(),
        'ATTENDANCE_DAY' => $timetableDay,
        'ATTENDANCE_DATE_FORMATTED' => $attendanceDate->getLongDateOnly(),
        'ATTENDANCE_PERIOD_LONG' => Scrub::text(implode(' to ', $periodNames)),
        'TIMETABLE_GROUP' => $timetableGroup,
        'attendance_period_block' => '',
    ]);

    foreach ($attendancePeriods as $attendancePeriod) {
        $this->setVar('ATTENDANCE_PERIOD', $attendancePeriod);
        $this->parse('attendance_period_block', 'attendance_period', true);
    }

    // Get the attendance record for this class

    $allStudents = [];

    try {

        // Glue the records for all periods together.
        // TODO make External_Synergetic_Attendance accept multiple periods
        $allStudents = [];
        foreach ($attendancePeriods as $attendancePeriod) {
            $attendanceHandler = new External_Synergetic_Attendance(
                $class,
                $attendanceDate,
                $timetableDay,
                $attendancePeriod,
                $externalUserHandler->getCurrentYear($_SESSION['user']->getExternalId()),
                $externalUserHandler->getCurrentSemester($_SESSION['user']->getExternalId()),
                $_SESSION['user']->getExternalId(),
                $timetableGroup
            );
            $allStudentsTemp = $attendanceHandler->findAttendanceRecords();
            $allStudentsTemp = array_combine(
                array_column($allStudentsTemp, 'ID'),
                $allStudentsTemp
            );
            foreach ($allStudentsTemp as $id => $student) {
                // Set base student record
                if (!isset($allStudents[$id])) {
                    $allStudents[$id] = $student;
                }

                // Override late arrival / early departure if set
                if ($student['LateArrivalFlag']) {
                    $allStudents[$id]['AttendedFlag']    = 1;
                    $allStudents[$id]['LateArrivalFlag'] = 1;
                    $allStudents[$id]['LateArrivalTime'] = $student['LateArrivalTime'];
                }
                if ($student['EarlyDepartureFlag']) {
                    $allStudents[$id]['AttendedFlag']       = 1;
                    $allStudents[$id]['EarlyDepartureFlag'] = 1;
                    $allStudents[$id]['EarlyDepartureTime'] = $student['EarlyDepartureTime'];
                }
            }
        }

    } catch (ExternalException $e) {
        error(ErrorMapper::ERROR_ATTENDANCE_LIST);
    } catch (SchoolboxException $e) {
        error(ErrorMapper::ERROR_ATTENDANCE_SEQUENCE);
    }

    // Render each row

    $attendanceCount = 0;
    $attendanceTotal = 0;

    if (is_array($allStudents) && count($allStudents)) {

        $attendanceCount = $attendanceTotal = count($allStudents);
        $userHandler = new User();

        // Dropdown options

        $absenceDropdownOptions = $attendanceHandler->findAbsenceDropdownOptions();
        $reasonDropdownOptions = $attendanceHandler->findReasonDropdownOptions();
        $filteredReasons = [];

        /* Collate a list of (type code, reason[]) tuples to use as a hashed lookup
         * Each Absence Reason dropdown should consist of the AbsenceTypeCode selected
         * in the other dropdown concatenated with the _all stack
         */
        define('INDEX_ALL', '_all');

        $reasonStack = [
            INDEX_ALL => []
        ];

        foreach ($reasonDropdownOptions as $reason) {

            /* Figure out where it should be stacked
             */
            $type = trim($reason['AbsenceTypeCode']) !== '' ? trim($reason['AbsenceTypeCode']): INDEX_ALL;

            /* If we haven't seen this type code before, init an array
             */
            if (!isset($reasonStack[$type])) {
                $reasonStack[$type] = [];
            }

            /* A list of correlated (type code, reason[]) tuples
             * All the ones without type codes get stashed into _all
             */
            $reasonStack[$type][] = $reason;
        }

        /* Provide it to phpliberty land
         */
        $this->setVar([
            'INDEX_ALL' => INDEX_ALL,
            'DROPDOWN_HASH' => json_encode($reasonStack)
        ]);

        // Iterate each student

        foreach ($allStudents as $currentStudent) {

            $user = $userHandler->getBySynergy($currentStudent['ID']);

            $pattern = '/((?:[0-2])?[0-9]:[0-5][0-9]):[0-5][0-9](?:\.|:)000(AM|PM)?/i';

            $isAbsent = $currentStudent['AttendedFlag'] == 0;

            if ($isAbsent) {
                $attendanceCount--;
            }

            $this->setVar([
                'STUDENT_UID' => $user['uid'],
                'STUDENT_NAME' => Scrub::text($currentStudent['StudentName']),
                'STUDENT_SYNERGY_ID' => $currentStudent['ID'],
                'STUDENT_ABSENCE_CODE' => $currentStudent['PossibleAbsenceCode'],
                'STUDENT_REASON_CODE' => $currentStudent['PossibleReasonCode'],
                'STUDENT_REASON_DESCRIPTION' => Scrub::text($currentStudent['ActualEventComment'] ? $currentStudent['ActualEventComment'] : $currentStudent['PossibleDescription']),
                'STUDENT_ATTENDANCESTRING' => Scrub::text($currentStudent['TodaysAttendanceString']),
                'STUDENT_NONATTENDREASON' => Scrub::text($currentStudent['PossibleNonAttendReason']),
                'STUDENT_ARRIVAL_TIME' => parseDateTimeString($currentStudent['LateArrivalTime']),
                'STUDENT_DEPT_TIME' => parseDateTimeString($currentStudent['EarlyDepartureTime']),
                'IS_ABSENT' => $isAbsent ? 'checked="checked"' : '',
            ]);

            if ($currentStudent['PossibleNonAttendFlag']) {
                $this->parse('student_absent_block', 'student_absent');
            } else {
                $this->setVar('student_absent_block', '');
            }

            $abscenceCode = $currentStudent['ActualAbsenceTypeCode'] && $currentStudent['ActualAbsenceTypeCode'] != '?' ? $currentStudent['ActualAbsenceTypeCode'] : $currentStudent['PossibleAbsenceCode'];

            $this->dropDown(
                'absence_options', $absenceDropdownOptions, $abscenceCode, 'student',
                array('Code' => 'ABSENCE_OPTIONS_VALUE', 'Description' => 'ABSENCE_OPTIONS_DESCRIPTION')
            );


            $reasonCode = $currentStudent['ActualAbsenceReasonCode'] ? $currentStudent['ActualAbsenceReasonCode'] : $currentStudent['PossibleReasonCode'];

            /* Set default dropdown appearance on load
             */
            $this->dropDown(
                'reason_options', array_merge($reasonStack[$abscenceCode] ?: [], $reasonStack[INDEX_ALL]), $reasonCode, 'student',
                array('Code' => 'REASON_OPTIONS_VALUE', 'AbsenceTypeCode' => 'REASON_OPTIONS_TYPE', 'Description' => 'REASON_OPTIONS_DESCRIPTION')
            );

            if ($configLoader->get('show_attendance_flags')) {
                //Let's render out some flags
                //Only the flags defined in the keys will be rendered
                $flags = [
                    'MedicalAlertFlag'=> [
                        'icon' => 'alert',
                        'descriptionField' => 'MedicalAlert',
                        'defaultDescription' => 'Medical Alert'
                    ],
                    'SpecialNeedsFlag'=> [
                        'icon' => 'info',
                        'descriptionField' => 'SpecialNeedsDescription',
                        'defaultDescription' => 'Special Needs'
                    ],
                    'CommentWarningFlag'=> [
                        'icon' => 'comment',
                        'descriptionField' => 'CommentDescription',
                        'defaultDescription' => 'Comment'
                    ],
                    'ParentsSeparatedFlag'=> [
                        'icon' => 'staff',
                        'descriptionField' => 'ParentsSeparatedDescription',
                        'defaultDescription' => 'Parents Separated'
                    ],
                    'CourtOrderFlag'=> [
                        'icon' => 'forms',
                        'descriptionField' => 'CourtOrderDescription',
                        'defaultDescription' => 'Court Order'
                    ],
                    'IndividualPlanFlag'=> [
                        'icon' => 'staff-students',
                        'descriptionField' => 'IndividualPlanDescription',
                        'defaultDescription' => 'Individual Plan'
                    ],
                    'AnaphylaxisFlag'=> [
                        'icon' => 'danger',
                        'descriptionField' => 'AnaphylaxisDescription',
                        'defaultDescription' => 'Anaphylaxis'
                    ]
                ];

                foreach ($flags as $key => $value) {
                    if(isset($currentStudent[$key]) && $currentStudent[$key]){
                        $this->setVar('ICON', $flags[$key]['icon']);

                        if(!empty($currentStudent[$flags[$key]['descriptionField']])) {
                            $this->setVar('DESCRIPTION', $currentStudent[$flags[$key]['descriptionField']]);
                        } else {
                            $this->setVar('DESCRIPTION', $flags[$key]['defaultDescription']);
                        }

                        $this->parse('flags_block', 'flags', true);
                    }
                }
            }

            $this->parse('student_block', 'student', true);

            // Clear children after parsing out the parent
            $this->setVar('flags_block', '');
            $this->setVar('absence_options_block', '');
            $this->setVar('reason_options_block', '');
        }
    } else {

        $this->setVar('student_block', '');
    }

    $this->setVar('ATTENDANCE_COUNT', $attendanceCount);
    $this->setVar('ATTENDANCE_TOTAL', $attendanceTotal);

} else {
    error(ErrorMapper::ERROR_CLASS_CODE_NOT_FOUND);
}
