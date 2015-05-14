<?php

/**
 * External_Schoolpro_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class External_Schoolpro_Timetable implements External_TimetableInterface
{

    /**
     * An instance of MSSQL
     *
     * @var MSSQL
     */
    private $db = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDb(MSSQL::getInstance());
    }

    /**
     * Number of days per cycle
     *
     * You could derive this, or just hardcode it
     * (I've only seen data for 4 days)
     *
     * @var int
     */
    protected $cycleLength = 4;

    /**
     * Get a timetable definition
     *
     * @param string $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param int $day
     *
     * @return array|bool
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        $this->setCycleLength($campus->getTimetableDay());
        $vars = array(
            'sbGetTimetableDefinition',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pCampusID', $campus->getExtTimetableCode(), SQLINT4, false, false, -1),
            )
        );
        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);
        if ($result['success'] && count($result['result'])) {
            $allDefinitions = array();
            /* Only stack it up if this day is chosen
             */
            foreach ($result['result'] as $currentDefinition) {
                if (!$day || ($day == $currentDefinition['DayNumber'])) {
                    $allDefinitions['desc'][] = $currentDefinition['PeriodDescription'];
                    $allDefinitions['start'][] = $currentDefinition['TimeFrom'];
                    $allDefinitions['end'][] = $currentDefinition['TimeTo'];
                    $allDefinitions['number'][] = $currentDefinition['PeriodNumber'];
                    $allDefinitions['dayNumber'][] = $currentDefinition['DayNumber'];
                    $allDefinitions['sequence'][] = 1;
                }
            }
            /* Number of days per cycle
             * Used in getFullTimetable()
             */
            $allDefinitions['dayCount'] = $this->getCycleLength();
            return $allDefinitions;
        }
        return false;
    }

    /**
     * Get a day number
     *
     * @param \Schoolbox\Campus $campus
     * @param int $date
     *
     * @return bool
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        $this->setCycleLength($campus->getTimetableDay());

        if (!$date) {
            $date = $_SERVER['REQUEST_TIME'];
        }

        /* Convert from timestamp to datetime
         */
        if ($date) {
            $dateObj = new Alaress_Date_Format($date, 'U');
            $dateToUse = $dateObj->getDatabaseDate();
            $vars = array(
                'sbGetDayNumber',
                array(
                    array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                    array('@pCampusID', $campus->getExtTimetableCode(), SQLINT4, false, false, -1),
                    array('@pDateToUse', $dateToUse, SQLVARCHAR, false, false, -1),
                )
            );
            $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

            if ($result['success'] && count($result['result'])) {
                return $result['result'][0]['DayNumber'];
            }
        }
        return false;
    }

    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber)
    {
        return 'Day ' . $dayNumber;
    }

    /**
     * Get period number for a particular campus
     *
     * @param int|\Schoolbox\Campus $campus
     * @param int $now
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        $vars = array(
            'sbGetPeriodNumber',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pCampusID', $campus->getExtTimetableCode(), SQLINT4, false, false, -1),
            )
        );
        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);
        if ($result['success'] && count($result['result'])) {
            return array(
                'PeriodNumber'    => $result['result'][0]['Identifier'],
                'PeriodNumberSeq' => 1,
            );
        }
        return false;
    }

    /**
     * Get staff day timetable
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return array
     */
    private function getStaffDayTimetable($externalUserId, \Schoolbox\Campus $campus, $dayNumber)
    {
        $vars = array(
            'sbGetStaffDayTimetable',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
                array('@pCampusID', $campus->getExtTimetableCode(), SQLINT4, false, false, -1),
                array('@pDayNumber', $dayNumber, SQLINT4, false, false, -1),
            )
        );
        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);
        return $result;
    }

    /**
     * Get staff day timetable
     *
     * @param $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return array
     */
    private function getStudentDayTimetable($externalUserId, $campus, $dayNumber)
    {
        $vars = array(
            'sbGetStudentDayTimetable',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
                array('@pCampusID', $campus->getExtTimetableCode(), SQLINT4, false, false, -1),
                array('@pDayNumber', $dayNumber, SQLINT4, false, false, -1),
            )
        );
        $result = $this->getDb()->executeProcedure($vars[0], $vars[1]);
        return $result;
    }

    /**
     * Get the day timetable for a given person
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     * @param $dayNumber
     *
     * @return bool|void
     */
    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $groupHandler = new Group();
        if ($groupHandler->isStudent($userId)) {
            $result = $this->getStudentDayTimetable($externalUserId, $campus, $dayNumber);
        } else {
            /* Else assume staff
             */
            $result = $this->getStaffDayTimetable($externalUserId, $campus, $dayNumber);
        }
        $definition = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);
        if ($result['success'] && count($result['result'])) {
            $timetable = array();
            $allClasses = $result['result'];
            /* For every period defined in the timetable definition,
             * set up a stub stack which contains all the possible
             * periods for this day. We'll append actual classes to
             * this data structure.
             */
            for ($x = 0; isset($definition['desc'][$x]); $x++) {
                $timetable[$x]['periodName'] = $definition['desc'][$x];
                $timetable[$x]['periodStart'] = substr($definition['start'][$x], 0, 5);
                $timetable[$x]['periodEnd'] = substr($definition['end'][$x], 0, 5);
                $timetable[$x]['periodNumber'] = $definition['number'][$x];
                $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
                $timetable[$x]['class'] = array();
            }
            /* With the result set obtained above, append actual class
             * details to the period definition established in the previous loop
             *
             * Sometimes they can stuff up their db, leaving out periods - e.g.
             * Period 1, Period 2, Period 4, Period 5,6,7,8,9... (skipped Period 3)
             * in timetableDefinition, but will have a Period 3 class specified
             * in getStaffDayTimetable.
             *
             * Doing it in a linear fashion means the loops get stuck trying to
             * assign a Period 3 class to a nonexistent Period 3 definition.
             *
             * In other connectors this is done in a "merge" style operation
             * which requires only one loop, but I did it this way because
             * this kind of stuff is currently not documented well.
             */
            for ($x = 0; $x < count($timetable); $x++) {
                if (count($allClasses)) {
                    foreach ($allClasses as $currentClass) {
                        /* If this class occurs in this period, append its details
                         */
                        if ($timetable[$x]['periodNumber'] == $currentClass['Identifier']) {
                            $timetable[$x]['class'][] = array(
                                'description' => $currentClass['ClassDescription'],
                                'code'        => $currentClass['ClassCode'],
                                'room'        => $currentClass['ClassRoom'],
                                'staffID'     => $externalUserId,
                                'dayNumber'   => $currentClass['DayNumber'],
                            );
                            break;
                        }
                    }
                }
            }
            return $timetable;
        }
        return false;
    }

    /**
     * Return a user's timetable for a specific date
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     *
     * @param $externalUserId
     * @param $userId
     * @param Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }


    /**
     * Get a full timetable
     *
     * @param $externalUserId
     * @param $userId
     * @param \Schoolbox\Campus $campus
     *
     * @return array
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $this->setCycleLength($campus->getTimetableDay());
        $allDayTimetables = array();
        /* Get all the days' timetables first
         */
        for ($i = 1; $i <= $this->getCycleLength(); $i++) {
            $allDayTimetables[] = $this->getDayTimetable($externalUserId, $userId, $campus, $i);
        }
        $timetable = array();
        /* If the $row has values and the definition hasn't already been rendered,
         * subst those vars - otherwise do nothing. Sometimes it can be overwritten
         * with empty values, like on chub where there were days, but no subjects
         * in them, producing and empty $row and thus empty definitions
         */
        for ($period = 0; $period < count($allDayTimetables[0]); $period++) {
            for ($day = 1; $day <= $this->getCycleLength(); $day++) {
                $timetable[$period]['day'][$day] = array();
                if (!empty($allDayTimetables[$day-1][$period]['class'][0])) {
                    $timetable[$period]['day'][$day][] = $allDayTimetables[$day-1][$period]['class'][0];
                }
                /* TODO: Have these use convert(varchar(10), theDate, 120) in stored proc
                 */
                $start = substr($allDayTimetables[0][$period]['periodStart'], 0, 5);
                $end = substr($allDayTimetables[0][$period]['periodEnd'], 0, 5);
                /* Port across some values
                 */
                $timetable[$period]['periodName'] = $allDayTimetables[0][$period]['periodName'];
                $timetable[$period]['periodNumber'] = $allDayTimetables[0][$period]['periodNumber'];
                $timetable[$period]['periodStart'] = $start;
                $timetable[$period]['periodEnd'] = $end;
                $timetable[$period]['periodSequence'] = 1;
            }
        }
        return $timetable;
    }

    /**
     * TODO: No stored proc
     *
     * @param  string $roomCode
     *
     * @return array
     */
    public function getRoomTimetable($roomCode)
    {
        return array();
    }

    /* Getters/Setters
     */

    /**
     * @param int $cycleLength
     */
    private function setCycleLength($cycleLength)
    {
        $this->cycleLength = $cycleLength;
    }

    /**
     * @return int
     */
    private function getCycleLength()
    {
        return $this->cycleLength;
    }

    /**
     * Set db
     *
     * @param MSSQL $db
     */
    private function setDb(\MSSQL $db)
    {
        $this->db = $db;
    }

    /**
     * Get db
     *
     * @return MSSQL
     */
    private function getDb()
    {
        return $this->db;
    }
}
