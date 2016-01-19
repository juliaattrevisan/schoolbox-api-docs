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
                CODE,
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
            // CODE isn't part of default definition, but I need it for matching
            $result['code'][]     = $timetableDay['CODE'];
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
     * Gets period start and end times.
     *
     * @return array
     */
    protected function getPeriodTimes()
    {
        $periods = $this->getDatabase()->getAll("
            SELECT
                CODE,
                TIME_START,
                TIME_END
            FROM TIMEPDHD
            ORDER BY SORT_ORDER ASC
        ");
        $result = [];
        foreach ($periods as $period) {
            // CODE isn't part of default definition, but I need it for matching
            $result[$period['CODE']] = [
                'start' => $period['TIME_START'],
                'end'   => $period['TIME_END'],
            ];
        }
        return $result;
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
        // I need to know if $userId is a teacher or student
        try {
            $user = $this->userMapper->findByExternalId($externalUserId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $this->getDatabase()->query('SET ANSI_PADDING ON');
        $this->getDatabase()->query('SET ANSI_WARNINGS ON');

        $studentExternalId = '';
        $teacherExternalId = '';
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }
        $params = [
            ['@DATE', date('Ymd'), SQLVARCHAR, false, false, -1],
            ['@TIMEPERIOD', $campus->getExtTimetableCode(), SQLVARCHAR, false, false, -1],
            ['@STUDKEY', $studentExternalId, SQLVARCHAR, false, false, -1],
            ['@TEACHERCODE', $teacherExternalId, SQLVARCHAR, false, false, -1],
            ['@LOGIC_NO', 2, SQLINT1, false, false, -1],
        ];
        $response = $this->getDatabase()->executeProcedure('spSelTimetable', $params, true);
        list($dayPeriods, $dayClasses, $homeClasses, $weekDays, $terms) = $response['result'];

        $periodTimes = $this->getPeriodTimes();

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $code  = $period['CODE'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            $timetable[$order]['periodStart']    = $periodTimes[$code]['start'];
            $timetable[$order]['periodEnd']      = $periodTimes[$code]['end'];
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['class']          = [];
        }
        ksort($timetable);

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $code   = $dayClass['ABBREV'];
            if ($dayNumber == $dayClass['DAY']) {
                $timetable[$period]['class'][$code] = array(
                    'description'        => $dayClass['DESC'],
                    'code'               => $code,
                    'campus'             => '',
                    'room'               => $dayClass['ROOM'],
                    'StaffPreferredName' => $dayClass['NAME_FOR_REPORT'],
                );
            }
        }
        return $timetable;
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
            $user = $this->userMapper->findByExternalId($externalUserId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $this->getDatabase()->query('SET ANSI_PADDING ON');
        $this->getDatabase()->query('SET ANSI_WARNINGS ON');

        $studentExternalId = '';
        $teacherExternalId = '';
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }
        $params = [
            ['@DATE', $date->getUsingFormat('Ymd'), SQLVARCHAR, false, false, -1],
            ['@TIMEPERIOD', $campus->getExtTimetableCode(), SQLVARCHAR, false, false, -1],
            ['@STUDKEY', $studentExternalId, SQLVARCHAR, false, false, -1],
            ['@TEACHERCODE', $teacherExternalId, SQLVARCHAR, false, false, -1],
            ['@LOGIC_NO', 2, SQLINT1, false, false, -1],
        ];
        $response = $this->getDatabase()->executeProcedure('spSelTimetable', $params, true);
        list($dayPeriods, $dayClasses, $homeClasses, $weekDays, $terms) = $response['result'];

        $periodTimes = $this->getPeriodTimes();

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $code  = $period['CODE'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            $timetable[$order]['periodStart']    = $periodTimes[$code]['start'];
            $timetable[$order]['periodEnd']      = $periodTimes[$code]['end'];
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['class']          = [];
        }
        ksort($timetable);

        // What's the current day number?
        $dayNumber    = 0;
        $databaseDate = $date->getDatabaseDate();
        foreach ($weekDays as $weekDay) {
            try {
                $adf = new Alaress_Date_Format($weekDay['DATE'], 'M d Y+|');
            } catch (Exception $e) {
                continue;
            }
            if ($adf->getDatabaseDate() == $databaseDate) {
                $dayNumber = $weekDay['TIMETABLE_DAY'];
                break;
            }
        }

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $code   = $dayClass['ABBREV'];
            if ($dayNumber == $dayClass['DAY']) {
                $timetable[$period]['class'][$code] = array(
                    'description'        => $dayClass['DESC'],
                    'code'               => $code,
                    'campus'             => '',
                    'room'               => $dayClass['ROOM'],
                    'StaffPreferredName' => $dayClass['NAME_FOR_REPORT'],
                );
            }
        }
        return $timetable;
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
            $user = $this->userMapper->findByExternalId($externalUserId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $this->getDatabase()->query('SET ANSI_PADDING ON');
        $this->getDatabase()->query('SET ANSI_WARNINGS ON');

        $studentExternalId = '';
        $teacherExternalId = '';
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }
        $params = [
            ['@DATE', date('Ymd'), SQLVARCHAR, false, false, -1],
            ['@TIMEPERIOD', $campus->getExtTimetableCode(), SQLVARCHAR, false, false, -1],
            ['@STUDKEY', $studentExternalId, SQLVARCHAR, false, false, -1],
            ['@TEACHERCODE', $teacherExternalId, SQLVARCHAR, false, false, -1],
            ['@LOGIC_NO', 2, SQLINT1, false, false, -1],
        ];
        $response = $this->getDatabase()->executeProcedure('spSelTimetable', $params, true);
        list($dayPeriods, $dayClasses, $homeClasses, $weekDays, $terms) = $response['result'];

        $term = array_shift($terms);

        $periodTimes = $this->getPeriodTimes();

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        $dayCount  = $term['TIMEDAYS'];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $code  = $period['CODE'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            // Where do periodStart and periodEnd come from?
            $timetable[$order]['periodStart']    = $periodTimes[$code]['start'];
            $timetable[$order]['periodEnd']      = $periodTimes[$code]['end'];
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['day']            = array_fill(1, $dayCount, array());
        }
        ksort($timetable);

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $day    = $dayClass['DAY'];
            $code   = $dayClass['ABBREV'];
            $timetable[$period]['day'][$day][$code] = array(
                'description'        => $dayClass['DESC'],
                'code'               => $code,
                'campus'             => '',
                'room'               => $dayClass['ROOM'],
                'StaffPreferredName' => $dayClass['NAME_FOR_REPORT'],
            );
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
