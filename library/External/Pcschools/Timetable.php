<?php

use Schoolbox\CampusMapper;

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
     * @var \Schoolbox\CampusMapper
     */
    private $campusMapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
//        // Required, as using @LOGIC_NO
//        $this->getDatabase()->query('SET ANSI_PADDING ON');
//        $this->getDatabase()->query('SET ANSI_WARNINGS ON');
        $this->userMapper = new \Schoolbox\UserMapper();
        $this->campusMapper = new \Schoolbox\CampusMapper();
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
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
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
     * {@inheritdoc}
     *
     * @param                  $externalUserId
     * @param                  $userId
     * @param Schoolbox\Campus $campus
     * @param                  $dayNumber
     * @throws \Alaress\Database\Exception
     * @return array
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        // I need to know if $userId is a teacher or student
        try {
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure('spSelTimetable');
        $statement->bindInputs([
            'DATE' => date('Ymd'),
            'TIMEPERIOD' => $timePeriod,
            'STUDKEY' => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();

        $statement->close();

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            $timetable[$order]['periodStart']    = $period['TIME_START'];
            $timetable[$order]['periodEnd']      = $period['TIME_END'];
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['class']          = [];
        }
        ksort($timetable);

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $code   = $timePeriod.'/'.$dayClass['YEAR'].$dayClass['ABBREV'].$dayClass['CLASS'];
            if ($dayNumber == $dayClass['DAY']) {
                $timetable[$period]['class'][$code] = array(
                    'description'        => $dayClass['DESC'],
                    'code'               => $code,
                    'campus'             => $timePeriod,
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
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure('spSelTimetable');
        $statement->bindInputs([
            'DATE' => $date->getUsingFormat('Ymd'),
            'TIMEPERIOD' => $timePeriod,
            'STUDKEY' => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();
//        $homeClasses = $statement->fetchAll();
        $statement->nextRowset();
        $weekDays = $statement->fetchAll();
        $statement->nextRowset();
//        $terms = $statement->fetchAll();
        $statement->close();

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            $timetable[$order]['periodStart']    = sprintf('%.2f', $period['TIME_START']);
            $timetable[$order]['periodEnd']      = sprintf('%.2f', $period['TIME_END']);
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['class']          = [];
        }
        ksort($timetable);

        // What's the current day number?
        $dayNumber = 0;
        foreach ($weekDays as $weekDay) {
            try {
                $adf = new Alaress_Date_Format($weekDay['DATE'], Alaress_Date_Format::DATABASE_FORMAT);
            } catch (Exception $e) {
                continue;
            }
            if ($adf->isSameDay($date)) {
                $dayNumber = $weekDay['TIMETABLE_DAY'];
                break;
            }
        }

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $code   = $timePeriod.'/'.$dayClass['YEAR'].$dayClass['ABBREV'].$dayClass['CLASS'];
            if ($dayNumber == $dayClass['DAY']) {
                $timetable[$period]['class'][$code] = array(
                    'description'        => $dayClass['DESC'],
                    'code'               => $code,
                    'campus'             => $timePeriod,
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
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure('spSelTimetable');
        $statement->bindInputs([
            'DATE' => date('Ymd'),
            'TIMEPERIOD' => $timePeriod,
            'STUDKEY' => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();
//        $homeClasses = $statement->fetchAll();
        $statement->nextRowset();
//        $weekDays = $statement->fetchAll();
        $statement->nextRowset();
        $terms = $statement->fetchAll();
        $statement->close();

        $term = array_shift($terms);

        // The $homeClasses definition is mostly included within dayPeriods
        $timetable = [];
        $dayCount  = $term['TIMEDAYS'];
        foreach ($dayPeriods as $period) {
            // Order the list by sortOrder
            $order = $period['SORT_ORDER'];
            $timetable[$order]['periodName']     = $period['HEADING'];
            $timetable[$order]['periodStart']    = sprintf('%.2f', $period['TIME_START']);
            $timetable[$order]['periodEnd']      = sprintf('%.2f', $period['TIME_END']);
            $timetable[$order]['periodNumber']   = $order;
            $timetable[$order]['periodSequence'] = 1;
            $timetable[$order]['day']            = array_fill(1, $dayCount, array());
        }
        ksort($timetable);

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            $period = $dayClass['PERIOD'];
            $day    = $dayClass['DAY'];
            $code   = $timePeriod.'/'.$dayClass['YEAR'].$dayClass['ABBREV'].$dayClass['CLASS'];
            $timetable[$period]['day'][$day][$code] = array(
                'description'        => $dayClass['DESC'],
                'code'               => $code,
                'campus'             => $timePeriod,
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
        // Split the requested room codes into a CSV string for use in the query

        if (!is_array($roomCodes)) {
            $roomCodes = [$roomCodes];
        }

        array_walk($roomCodes, function (&$value, &$key) {
            $value = "'" . $this->getDatabase()->quote($value) . "'";
        });

        $roomCodes = implode(",", $roomCodes);

        // We don't have any information at this point in time as to which user, date, or other context we are in- so
        // we sadly just end up blindly picking any campus to get a value to use for TIME_PERIOD in our queries

        $timePeriod = '';

        $campuses = CampusMapper::getInstance()->findAll();

        foreach ($campuses as $campus) {
            $timePeriod = trim($campus->getExtTimetableCode());
            if ($timePeriod !== '') {
                break;
            }
        }

        if ($timePeriod === '') {
            return [];
        }

        $timePeriod = $this->getDatabase()->quote($timePeriod);

        // Run this mess

        $sql = "
            SELECT
              TIMEFILE.DAY            AS TIMETABLE_DAY,
              TIMEFILE.TIME_PERIOD    AS TIMETABLE_GROUP,
              TIMEFILE.PERIOD         AS TIMETABLE_PERIOD,
              TIMEPDHD.TIME_START     AS PERIOD_START,
              TIMEPDHD.TIME_END       AS PERIOD_END,
              TIMEPDHD.HEADING        AS PERIOD_NAME,
              TIMEFILE.ROOM           AS ROOM_CODE,
              TIMEROOM.[DESC]         AS ROOM_NAME,
              TIMEFILE.YEAR           AS CLASS_YEAR_LEVEL,
              TEACHER.TEACHER_CODE    AS TEACHER_CODE,
              TEACHER.NAME_FOR_REPORT AS TEACHER_NAME,
              TTABLE.SUB_CODE         AS SUBJECT_CODE,
              SUBJECT.[DESC]          AS SUBJECT_NAME
            FROM TIMEFILE
              LEFT JOIN TTABLE ON TTABLE.PCS_SUB_NO = TIMEFILE.SUBJECT AND TTABLE.CLASS_PCS = TIMEFILE.CLASS AND TTABLE.YEAR_PCS = TIMEFILE.YEAR
              LEFT JOIN SUBJECT ON SUBJECT.NO = TTABLE.PCS_SUB_NO
              LEFT JOIN TEACHER ON TEACHER.TEACHER_CODE = TIMEFILE.TEACHER
              LEFT JOIN TIMEROOM ON TIMEROOM.ABBREV = TIMEFILE.ROOM
              LEFT JOIN TIMEPDHD ON TIMEPDHD.DAY = TIMEFILE.DAY AND TIMEPDHD.TIMEOFDAY = CAST(TIMEFILE.PERIOD AS VARCHAR(5)) AND
              CASE
                WHEN TIMEPDHD.RESTRICT_YR = '*C' THEN 1                                                                            
                WHEN LEFT(TIMEPDHD.RESTRICT_YR, 1) <> '*' AND TIMEFILE.YEAR = TIMEPDHD.RESTRICT_YR THEN 1                          
                WHEN LEFT(TIMEPDHD.RESTRICT_YR, 1) = '*' AND TIMEPDHD.INCLUDES LIKE '%|' + TIMEFILE.YEAR + '|%' THEN 1             
                ELSE 0
              END = 1
            WHERE TIME_PERIOD = '{$timePeriod}' AND TIMEFILE.ROOM IN ({$roomCodes})
        ";

        $classes = $this->getDatabase()->getAll($sql);

        $result = [];

        foreach ($classes as $class) {

            // If we don't have a start/end time- skip it.

            if (trim($class['PERIOD_START']) === '' || trim($class['PERIOD_END']) === '') {
                continue;
            }

            $periodStartArray = explode('.', $class['PERIOD_START']);
            $periodStartAMPM = $periodStartArray[0] >= 12 ? 'PM' : 'AM';

            if ($periodStartArray[0] > 12) {
                $periodStartArray[0] -= 12;
            }

            if ($periodStartArray[0] < 10) {
                $periodStartArray[0] = "0{$periodStartArray[0]}";
            }

            if ($periodStartArray[1] === null) {
                $periodStartArray[1] = '00';
            } else if ($periodStartArray[1] < 10 && strlen($periodStartArray[1]) < 2) {
                $periodStartArray[1] = "{$periodStartArray[1]}0";
            }

            $periodStart = "{$periodStartArray[0]}:{$periodStartArray[1]}{$periodStartAMPM}";

            $periodEndArray = explode('.', $class['PERIOD_END']);
            $periodEndAMPM = $periodEndArray[0] >= 12 ? 'PM' : 'AM';

            if ($periodEndArray[0] > 12) {
                $periodEndArray[0] -= 12;
            }

            if ($periodEndArray[0] < 10) {
                $periodEndArray[0] = "0{$periodEndArray[0]}";
            }

            if ($periodEndArray[1] === null) {
                $periodEndArray[1] = '00';
            } else if ($periodEndArray[1] < 10 && strlen($periodEndArray[1]) < 2) {
                $periodEndArray[1] = "{$periodEndArray[1]}0";
            }

            $periodEnd = "{$periodEndArray[0]}:{$periodEndArray[1]}{$periodEndAMPM}";

            $result[] = [
                'DayNumber' => $class['TIMETABLE_DAY'],
                'TimetableGroup' => $timePeriod,
                'PeriodStart' => $periodStart,
                'PeriodEnd' => $periodEnd,
                'PeriodName' => $class['PERIOD_NAME'],
                'Description' => "{$class['SUBJECT_NAME']}\n{$class["TEACHER_NAME"]}",
            ];
        }

        return $result;
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
