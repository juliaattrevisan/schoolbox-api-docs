<?php
/**
 * Allows the updating of attendance records in synergetic.  This page is used to display the input form with a
 * list of students, options for attendance storing
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

$this->setTitle('Student Attendance');
$this->setFile(TEMPLATE_content, 'modules/attendance/modify.html');
$this->setBlock(TEMPLATE_content, 'student', 'student_block');

/* Dropdowns and such for each student
 */
$this->setBlock('student', 'absence_options', 'absence_options_block');
$this->setBlock('student', 'reason_options', 'reason_options_block');

/* THE THREE MUSKETEERS
 */
if (count($this->arg) < 3) {
    error(147);
}

/* Get a class list which we'll match up manually with their attendance records
 * but only if this class exists
 */
$externalClass = new External_Synergetic_Class();
if ($this->arg[0]) {
    $class = $externalClass->getClass($this->arg[0] . '/' . $this->arg[1]);
} else {
    $class = $externalClass->getClass($this->arg[1]);
}

if (!$class) {
    error(148);
}

if (!strlen($this->arg[2])) {
    error(149);
}

if (!is_numeric(preg_replace('/[^0-9]+/', '', $this->arg[3]))) {
    error(150);
}

/* Selected date for which to deal with attendances
 * Check that you are allowed to edit something in a particular date range
 * (You cannot edit anything older than the previous week)
 */
$attendanceDate = new Alaress_Date_Format($this->arg[2], 'Y-m-d');
$attendanceDate->startOfDay();

$now = Alaress_Date_Format::now();

if ($attendanceDate->getUsingFormat('W') < ($now->getUsingFormat('W') - 1)) {
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
$attendancePeriod = $this->arg[3];

/* Get Period Definition */
$externalTimetable = new External_Timetable();
$timetableDay = $externalTimetable->getDayNumber($_SESSION['user']->getCampusCollection()->first(), $attendanceDate->getUnixTimestamp());

$definition = $externalTimetable->getTimetableDefinition(
    null,
    $_SESSION['user']->getCampusCollection()->first(),
    $timetableDay
);
$periods = array_keys($definition['number'], $attendancePeriod);
$startTime = $definition['start'][$periods[0]];
$endTime = $definition['end'][$periods[0]];
$periodName = $definition['desc'][$periods[0]];
/* Let's do it
 */
if ($class) {
    /* Cosmetic makeup for the masses
    */
    $this->setVar(
        array(
            'CLASS_CODE'                => $class['ClassCampus'] . '/' . $class['ClassCode'],
            'ATTENDANCE_START'          => $startTime,
            'ATTENDANCE_END'            => $endTime,
            'ATTENDANCE_DATE'           => $attendanceDate->getDatabaseDate(),
            'ATTENDANCE_DAY'            => $timetableDay,
            'ATTENDANCE_DATE_FORMATTED' => $attendanceDate->getLongDateOnly(),
            'ATTENDANCE_PERIOD'         => $attendancePeriod,
            'ATTENDANCE_PERIOD_LONG'    => Scrub::text($periodName),
        )
    );

    /* Get the attendance record for this class
     */
    $externalUserHandler = new External_User();
    try {
        $attendanceHandler   = new External_Synergetic_Attendance(
            $class,
            $attendanceDate,
            $timetableDay,
            $attendancePeriod,
            $externalUserHandler->getCurrentYear(),
            $externalUserHandler->getCurrentSemester(),
            MSSQL::getInstance()
        );
        $allStudents = $attendanceHandler->findAttendanceRecords();
    } catch (Exception $e) {
        error(148); //Class does not exist
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
        foreach ($allStudents as $currentStudent) {
            $user = $userHandler->getBySynergy($currentStudent['ID']);

            /* Replace everything with everything
             */
            preg_match('/([0-9]+):([0-5])([0-9]):00:000(AM|PM)/', $currentStudent['LateArrivalTime'], $arrivalTime);
            preg_match('/([0-9]+):([0-5])([0-9]):00:000(AM|PM)/', $currentStudent['EarlyDepartureTime'], $deptTime);

            $this->setVar(
                array(
                    'STUDENT_UID'                => $user['uid'],
                    'STUDENT_NAME'               => Scrub::text($currentStudent['StudentName']),
                    'STUDENT_SYNERGY_ID'         => $currentStudent['ID'],
                    'STUDENT_ABSENCE_CODE'       => $currentStudent['PossibleAbsenceCode'],
                    'STUDENT_REASON_CODE'        => $currentStudent['PossibleReasonCode'],
                    'STUDENT_REASON_DESCRIPTION' => Scrub::text($currentStudent['PossibleDescription']),
                    'STUDENT_ATTENDANCESTRING'   => Scrub::text($currentStudent['TodaysAttendanceString']),
                    'STUDENT_NONATTENDREASON'    => Scrub::text($currentStudent['PossibleNonAttendReason']),
                    'STUDENT_ARRIVAL_TIME'       => isset($arrivalTime[3]) ? date('g:ia', strtotime($arrivalTime[0])) : '',
                    'STUDENT_DEPT_TIME'          => isset($deptTime[3]) ? date('g:ia', strtotime($deptTime[0])) : '',
                    'IS_ABSENT'                  => $currentStudent['AttendedFlag'] == 0 ? 'checked="checked"' : '',
                    'ON_OFF'                     => ++$i % 2 ? 'dark' : '',
                )
            );


            $this->dropDown(
                'absence_options', $absenceDropdownOptions, $currentStudent['PossibleAbsenceCode'], 'student',
                array('Code' => 'ABSENCE_OPTIONS_VALUE', 'Description' => 'ABSENCE_OPTIONS_DESCRIPTION')
            );

            $this->dropDown(
                'reason_options', $reasonDropdownOptions, $currentStudent['PossibleReasonCode'], 'student',
                array('Code' => 'REASON_OPTIONS_VALUE', 'Description' => 'REASON_OPTIONS_DESCRIPTION')
            );



            $this->parse('student_block', 'student', true);

            /* Clear children after parsing out the parent
             */
            $this->setVar('absence_options_block', '');
            $this->setVar('reason_options_block', '');
        }


    } else {
        $this->setVar('student_block', '');
    }
} else {
    error(148);
}
