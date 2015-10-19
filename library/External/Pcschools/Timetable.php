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
            'dayCount'    => $this->getDatabase()->getOne(
                "SELECT MAX(DAY) FROM TIMEFILE WHERE TIME_PERIOD = (SELECT TIME_PERIOD from sysfile)"
            ),
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
        // TODO: Implement getDayTimetable() method.
        if (!$dayNumber) {
            $dayNumber = $this->getDayNumber($campus);
        }

        // I need to know if $userId is a teacher or student
        $result = [];
        try {
            $user = $this->userMapper->findById($userId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return $result;
        }
        if ($user->getRole()->isStudent()) {
            // use current PERIOD_ID?
            $query = "
                SELECT
                    SUB.STUDKEY AS Student,
                    SUB.TEACHER AS Teacher,
                    '' AS Room,
                    [SUBJECT].[DESC] AS Description,
--                    TIMEPDHD.SORT_ORDER AS PeriodNumber,
                    '' AS ClassCampus,
                    [SUBJECT].ABBREV AS ClassCode,
                    SUB.TEACHER AS StaffID
                FROM SUB
                INNER JOIN TIMEFILE
                    ON SUB.SUBJECT_NO = TIMEFILE.SUBJECT
                    AND SUB.[YEAR] = TIMEFILE.[YEAR]
                    AND SUB.CLASS = TIMEFILE.CLASS
                INNER JOIN [SUBJECT]
                    ON SUB.SUBJECT_NO = [SUBJECT].NO
--                INNER JOIN TIMEPDHD
--                    ON [SUBJECT].ABBREV = TIMEPDHD.CODE
                WHERE TIMEFILE.DAY = {$this->getDatabase()->smartQuote($dayNumber)}
                AND   SUB.STUDKEY = {$this->getDatabase()->smartQuote($externalUserId)}
            ";
        } elseif ($user->getRole()->isStaff()) {

        } else {
            return $result;
        }

        // Run the query
        return $this->getDatabase()->getAll($query);
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
        return $this->getDayTimetable(
            $externalUserId,
            $userId,
            $campus,
            $this->getDayNumber($campus, $date->getUnixTimeStamp())
        );
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
        // TODO: Implement getFullTimetable() method.
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
