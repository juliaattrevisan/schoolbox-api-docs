<?php
/**
 * PCSchools timetable connector
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Pcschools_Timetable implements External_TimetableInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * @var \Schoolbox\UserMapper
     */
    private $userMapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->userMapper = new \Schoolbox\UserMapper();
    }

    /**
     * The headings of the timetable, going horizontally
     * from left to right containing all the times and day numbers
     * Has the structure for each period
     * $timetableDef['desc'][]
     * $timetableDef['start'][]
     * $timetableDef['end'][]
     * $timetableDef['number'][]
     * $timetableDef['sequence'][]
     *
     * @param string            $externalUserId If set, the external user ID to get definition for.
     * @param \Schoolbox\Campus $campus
     * @param int               $day
     *
     * @return array
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        // TIMEPDHD looks like the timetable start/end time
        // It can be restricted on student's year, though.
        // MVP: don't bother with any of this, Cornerstone doesn't.
        $timetableDays = $this->getDatabase()->getAll("
            SELECT
                HEADING,
                TIME_START,
                TIME_END,
                SORT_ORDER
            FROM TIMEPDHD
            ORDER BY SORT_ORDER ASC
        ");
        $result = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'dayCount'    => $this->getDayCount(),
            'periodCount' => count($timetableDays),
        ];
        foreach ($timetableDays as $timetableDay) {
            $result['desc'][]     = $timetableDay['HEADING'];
            $result['start'][]    = $timetableDay['TIME_START'];
            $result['end'][]      = $timetableDay['TIME_END'];
            $result['number'][]   = $timetableDay['SORT_ORDER'];
            $result['sequence'][] = 1;
        }
        return $result;
    }

    /**
     * Gets the number of days in this timetable.
     *
     * @return int
     */
    protected function getDayCount()
    {
        return $this->getDatabase()->getOne(
            "SELECT MAX(DAY) FROM TIMEFILE WHERE TIME_PERIOD = (SELECT TIME_PERIOD from sysfile)"
        );
    }

    /**
     * Get the current day number for a given campus
     *
     * @param Schoolbox\Campus $campus
     * @param int              $date
     *
     * @internal param $campusId
     * @return int
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        if ($date) {
            $day = date('Y-m-d', $date);
        } else {
            $day = date('Y-m-d');
        }
        $result = $this->getDatabase()->getOne("
            SELECT TIMETABLE_DAY
            FROM CALENDAR
            WHERE [DATE] = {$this->getDatabase()->smartQuote($day)}
        ");
        return intval($result) ?: 0;
    }

    /**
     * Returns the name of the current day
     *
     * @param $dayNumber
     *
     * @return String
     */
    function getDayName($dayNumber)
    {
        // I don't think that PCSchools stores a day name.
        return 'Day '.$dayNumber;
    }

    /**
     * Calculates the current period based on the current time
     * The returned data structure is required for Dashboard Timetable Controller
     * PeriodNumber
     * PeriodNumberSeq
     *
     * @param Schoolbox\Campus $campus
     * @param int              $now
     *
     * @internal param $campusId
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        // Assume that definition doesn't vary per student
        // Should this use getFullTimetable then?
        $definition = $this->getTimetableDefinition(null, $campus, $now);
        foreach ($definition['number'] as $i => $number) {
            $sequence = $definition[$i]['sequence'];
            if ($now) {
                $start = strtotime($definition[$i]['start'], $now);
                $end   = strtotime($definition[$i]['end'], $now);
            } else {
                $start = strtotime($definition[$i]['start']);
                $end   = strtotime($definition[$i]['end']);
            }
            if ($start < $now && $now <= $end) {
                return [
                    'PeriodNumber'    => $number,
                    'PeriodNumberSeq' => $sequence,
                ];
            }
        }
        return [];
    }

    /**
     * Gets the query for fetching a user's timetable.
     *
     * @param \Schoolbox\User $user
     *
     * @return string
     */
    protected function getTimetableQuery(\Schoolbox\User $user)
    {
        $query = "
            SELECT
                TIMEPDHD.SORT_ORDER AS PeriodSequence,
                SUB.TEACHER AS StaffID,
                TIMEFILE.ROOM AS Room,
                [SUBJECT].[DESC] AS Description,
                TIMEFILE.DAY AS DayNumber,
                TIMEPDHD.SORT_ORDER AS PeriodNumber,
                [SUBJECT].ABBREV AS ClassCode
            FROM SUB
            INNER JOIN TIMEFILE
                ON SUB.SUBJECT_NO = TIMEFILE.SUBJECT
                AND SUB.[YEAR] = TIMEFILE.[YEAR]
                AND SUB.CLASS = TIMEFILE.CLASS
            INNER JOIN [SUBJECT]
                ON SUB.SUBJECT_NO = [SUBJECT].NO
            INNER JOIN TIMEPDHD
                ON TIMEFILE.PERIOD = TIMEPDHD.RECNUM
            WHERE SUB.PERIOD_ID = (SELECT TIME_PERIOD from sysfile)
        ";
        if ($user->getRole()->isStudent()) {
            $query .= 'AND SUB.STUDKEY = '.$this->getDatabase()->smartQuote($user->getExternalId());
        } elseif ($user->getRole()->isStaff()) {
            $query .= 'AND SUB.TEACHER = '.$this->getDatabase()->smartQuote($user->getExternalId());
        } else {
            return '';
        }
        return $query;
    }

    /**
     * Return a user's timetable for a specific day
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     * Has the structure for each period
     * periodName
     * periodStart
     * periodEnd
     * periodNumber
     * periodSequence
     * description
     * code
     * room
     * staffID
     * staffTitle
     * staffFirstname
     * staffSurname
     *
     * @param                  $externalUserId
     * @param                  $userId
     * @param Schoolbox\Campus $campus
     * @param                  $dayNumber
     *
     * @return mixed
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (!$dayNumber) {
            $dayNumber = $this->getDayNumber($campus);
        }

        // Get the closest date to today for this day number.
        $date = $this->getClosestDateForDayNumber($campus, $dayNumber);
        return $this->getTimetableForDate($externalUserId, $userId, $campus, $date);

    }

    protected function getClosestDateForDayNumber($campus, $dayNumber)
    {
//        $dayCount = $this->getDayCount();
        $query = "
            SELECT TOP 1 [DATE]
            FROM CALENDAR
            WHERE [TIMETABLE_DAY] = {$this->getDatabase()->smartQuote($dayNumber)}
            ORDER BY ABS(DATEDIFF(day, [DATE], @CurrentDate))
        ";
        return $this->getDatabase()->getOne($query);
    }

    /**
     * Return a user's timetable for a specific date
     * This was developed as synergetic has substitutions and attendance data available in a stored proc which
     * is date dependent
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     * Has the structure for each period
     * periodName
     * periodStart
     * periodEnd
     * periodNumber
     * periodSequence
     * description
     * code
     * room
     * staffID
     * staffTitle
     * staffFirstname
     * staffSurname
     *
     * @param                     $externalUserId
     * @param                     $userId
     * @param Schoolbox\Campus    $campus
     * @param Alaress_Date_Format $date
     *
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        // I need to know if $userId is a teacher or student
        try {
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        // Use that dumb stored proc here
        $studentExternalId = '';
        $teacherExternalId = '';
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }
        $params = [
            ['@DATE', $date->getDatabaseDate(), SQLVARCHAR, false, false, -1],
            ['@TIMEPERIOD', '', SQLVARCHAR, false, false, -1],
            ['@STUDKEY', $studentExternalId, SQLVARCHAR, false, false, -1],
            ['@TEACHERCODE', $teacherExternalId, SQLVARCHAR, false, false, -1],
        ];
        $response = $this->getDatabase()->executeProcedure('spSelTimetable', $params);

//        $query = $this->getTimetableQuery($user);
//        if (empty($query)) {
//            return [];
//        } else {
//            $query .= 'AND TIMEFILE.DAY = '.$this->getDatabase()->smartQuote($dayNumber);
//        }
//
//        // Mangle into the format getDayTimetable expects
//        $timetable           = [];
//        $timetableDefinition = $this->getTimetableDefinition($externalUserId, $campus);
//        foreach ($timetableDefinition['desc'] as $x => $name) {
//            $timetable[$x]['periodName']     = $timetableDefinition['desc'][$x];
//            $timetable[$x]['periodStart']    = $timetableDefinition['start'][$x];
//            $timetable[$x]['periodEnd']      = $timetableDefinition['end'][$x];
//            $timetable[$x]['periodNumber']   = $timetableDefinition['number'][$x];
//            $timetable[$x]['periodSequence'] = $timetableDefinition['sequence'][$x];
//            $timetable[$x]['class']          = array();
//        }
//
//        // Add classes into it
//        $result = $this->getDatabase()->query($query);
//        while ($desc = $this->getDatabase()->getNextArray($result)) {
//            foreach ($timetable as $x => $timetablePeriod) {
//                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber']
//                    && $timetablePeriod['periodSequence'] != 2
//                    && !isset($timetable[$x]['class'][$desc['ClassCode']])
//                ) {
//                    $timetable[$x]['class'][$desc['ClassCode']] = array(
//                        'description' => $desc['Description'],
//                        'code'        => $desc['ClassCode'],
//                        'campus'      => $desc['ClassCampus'],
//                        'room'        => $desc['Room'],
//                        'staffID'     => $desc['StaffID'],
//                    );
//                    break;
//                }
//            }
//        }
//        return $timetable;

//        return $this->getDayTimetable(
//            $externalUserId,
//            $userId,
//            $campus,
//            $this->getDayNumber($campus, $date->getUnixTimeStamp())
//        );
    }

    /**
     * Return the ENTIRE timetable for a user and campus
     * Assemble into a 2D array
     * Each row represents a Period across each Day in the cycle
     * [Period1 [Day1, Day2, Day3, DayN...]]
     * [Period2 [Day1, Day2, Day3, DayN...]]
     * [Period3 [Day1, Day2, Day3, DayN...]]
     * [PeriodN [Day1, Day2, Day3, DayN...]]
     * ...
     * $timetable[$period]['periodName']
     * $timetable[$period]['periodStart']
     * $timetable[$period]['periodEnd']
     * $timetable[$period]['periodNumber']
     * $timetable[$period]['periodSequence']
     * $timetable[$period]['day'][$day][] = array()
     * Similarly with $dayIndex
     * [Period1 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [Period2 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [Period3 [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * [PeriodN [Day1 [Class1, Class2...], Day2, Day3, DayN...]]
     * ...
     * $timetable[$periodIndex]['periodName']
     * $timetable[$periodIndex]['periodStart']
     * $timetable[$periodIndex]['periodEnd']
     * $timetable[$periodIndex]['periodNumber']
     * $timetable[$periodIndex]['periodSequence']
     * An array of classes for this period on this day
     * $timetable[$periodIndex]['day'][$dayIndex][] = array(
     *     description
     *     code
     *     campus
     *     room
     *     staffID
     *     staffFirstname
     *     staffSurname
     *     staffTitle
     * )
     *
     * @param                  $externalUserId
     * @param                  $userId
     * @param Schoolbox\Campus $campus
     *
     * @internal param $campusId
     * @return mixed
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        // I need to know if $userId is a teacher or student
        try {
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $query = $this->getTimetableQuery($user);
        if (empty($query)) {
            return [];
        }

        // Mangle into the format getDayTimetable expects
        $timetable           = [];
        $timetableDefinition = $this->getTimetableDefinition($externalUserId, $campus);
        $dayCount            = $timetableDefinition['dayCount'];
        foreach ($timetableDefinition['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetableDefinition['desc'][$x];
            $timetable[$x]['periodStart']    = $timetableDefinition['start'][$x];
            $timetable[$x]['periodEnd']      = $timetableDefinition['end'][$x];
            $timetable[$x]['periodNumber']   = $timetableDefinition['number'][$x];
            $timetable[$x]['periodSequence'] = $timetableDefinition['sequence'][$x];
            $timetable[$x]['day']            = array_fill(1, $dayCount, array());
        }

        // Add classes for each day into it
        $result = $this->getDatabase()->query($query);
        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']])
                ) {
                    $timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']] = array(
                        'description'    => $desc['Description'],
                        'code'           => $desc['ClassCode'],
                        'campus'         => $desc['ClassCampus'],
                        'room'           => $desc['Room'],
                        'staffID'        => $desc['StaffID'],
//                        'staffFirstname' => $desc['StaffPreferredName'],
//                        'staffSurname'   => $desc['StaffSurname'],
//                        'staffTitle'     => $desc['StaffTitle'],
                    );
                    break;
                }
            }
        }
        return $timetable;
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param  array|string $roomCodes
     *
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        // Not yet implemented
        return [];
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  MSSQL $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
