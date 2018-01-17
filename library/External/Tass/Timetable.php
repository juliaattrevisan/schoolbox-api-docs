<?php

/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to TASS timetable info    *
 *                                                           *
 ************************************************************/
class External_Tass_Timetable implements External_TimetableInterface
{

    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Keeps track of calendar day events
     */
    private $tassDay;

    /**
     * @var External_UserInterface
     */
    private $externalUser;
    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());

        $this->groupHandler = new Group;
        $this->externalUser = new External_User('tass');
    }



    function getCampusCode(\Schoolbox\Campus $campus)
    {
        list($campusCode, $timetableCode) = explode('-', $campus->getExtTimetableCode());
        return $campusCode;
    }

    function getTimetableCode(\Schoolbox\Campus $campus)
    {
        list($campusCode, $timetableCode) = explode('-', $campus->getExtTimetableCode());
        return $timetableCode;
    }

    function getDays(\Schoolbox\Campus $campus)
    {
        return $campus->getTimetableDay();
    }

    function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0, $date = null)
    {

        $campusCode    = $this->getCampusCode($campus);
        $timetableCode = $this->getTimetableCode($campus);

        /* Fetch the longest day to use as our day template...  */
        if ($day == 0) {
            $query      = "SELECT tt_id, day_code, prd_num FROM ttdays WHERE tt_id = '" . $timetableCode . " ' AND cmpy_code = '$campusCode' ORDER BY prd_num DESC";
            $longestDay = $this->getDatabase()->getRow($query);
            $day        = $longestDay['day_code'];
        }

        $query = "SELECT prd_desc, prd_start, prd_end, prd_code, day_code FROM ttperiod
                WHERE tt_id = '" . $timetableCode . "' AND cmpy_code = '$campusCode' AND day_code='$day'
                ORDER BY prd_code";

        $statement = $this->getDatabase()->query($query);
        $periodCount = 0;
        while ($period = $statement->fetch()) {

            $timetableDef['desc'][]   = $period['prd_desc'];
            $timetableDef['start'][]  = date("g:ia", strtotime($period['prd_start']));
            $timetableDef['end'][]    = date("g:ia", strtotime($period['prd_end']));
            $timetableDef['number'][] = $period['prd_code'];
            $periodCount++;
        }

        $timetableDef['periodCount'] = $longestDay['prd_num'];
        $timetableDef['dayCount']    = $this->getDays($campus);
        return $timetableDef;

    }

    //gets current day number
    function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {

        if (!$date) {
            $date = time();
        }

        if (date('w', $date) == 0 || date('w', $date) == 6) {
            return false;
        }
        //get day count
        if ($this->getDays($campus) == 5) {
            return date("w", $date);
        } elseif ($this->getDays($campus) == 7) {
            return 0; //Not implemented
        } elseif ($this->getDays($campus) == 10) {
            $startDate = $this->getLastDay($date, $campus);

            if ($this->tassDay[$startDate] > 5) {
                $this->tassDay[$startDate] = $this->tassDay[$startDate] + 1;  //extra days for weekend
            }
            $startDate = $startDate - ($this->tassDay[$startDate] * 86400);

            if (!$startDate) {
                //Just assume the start of the year if we cannot find anything
                $startDate = mktime(0, 0, 0, 1, 1, date('Y'));
            }

            //find number of days between both dates
            $difference = floor(($date - $startDate) / 86400);
            //find number of weeks between 2 dates
            $weeks = intval(($difference) / 7);

            if (($weeks % 2) == 0) {
                $day = date('w', $date);
                if ($day < 1) {
                    //on sundays move it forward
                    $day = 1;
                }
            } else {
                $day = date('w', $date) + 5;
                if ($day < 6) {
                    //on sundays move it forward
                    $day = 6;
                }

            }
            return $day;

        } else {
            //unknown day number
            return 0;
        }
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


    function getLastDay($date, \Schoolbox\Campus $campus)
    {

        $nowmssqldate = date('Y-m-d', $date) . ' 00:00:00';

        $query = "SELECT ttdate, day_code FROM ttcalendar
                    WHERE ttdate <= '$nowmssqldate'
                    AND cmpy_code = '" . $this->getCampusCode($campus) . "'
                    AND tt_id = '" . $this->getTimetableCode($campus) . "'
                    ORDER BY ttdate DESC";

        $result               = $this->getDatabase()->getRow($query);
        $time                 = strtotime($result['ttdate']);
        $this->tassDay[$time] = $result['day_code'];

        return $time;
    }

    //returns the period number for the current time
    function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
    {
        return 0;
    }

    function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc
                        FROM studsub
                        JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                        JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester 
                                AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS null) AND studsub.yr_study=ttmast.year_num
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE yr_study = '$fileYear' AND studsub.semester = '$fileSemester'
                                AND studsub.stud_code = '$externalUserId'
                                AND day_code = '$dayNumber'
                        ORDER BY prd_code";
        } else {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '$fileYear' AND semester = '$fileSemester'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$externalUserId'
                            AND day_code = '$dayNumber'
                         ORDER BY prd_code";
        }

        $statement = $this->getDatabase()->query($query);

        $timetable = array();

        // Build the timetable
        if(is_array($timetabledef['desc'])) {
            foreach ($timetabledef['desc'] as $x => $name) {
                $timetable[$x]['periodName'] = $timetabledef['desc'][$x];
                $timetable[$x]['periodStart'] = $timetabledef['start'][$x];
                $timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
                $timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
                $timetable[$x]['periodSequence'] = 1;
                $timetable[$x]['class'] = array();
            }

            // Add classes into it
            while ($desc = $statement->fetch()) {
                $code = trim($desc['year_grp']) . '-' . trim($desc['sub_code']) . '-' . trim($desc['class']);
                foreach ($timetable as $x => $timetablePeriod) {
                    if ($desc['prd_code'] == $timetablePeriod['periodNumber']
                        && $timetablePeriod['periodSequence'] != 2
                        && !isset($timetable[$x]['class'][$code])
                    ) {
                        $timetable[$x]['class'][$code] = array(
                            'description' => $desc['sub_desc'],
                            'code'        => $code,
                            'room'        => $desc['room_code'],
                            'staffCode'   => $desc['tch_code'],
                        );
                        break;
                    }
                }
            }
        }
        return $timetable;

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


    function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus);

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc, day_code
                            FROM studsub
                            JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                            JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester
                                    AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS NULL) AND studsub.yr_study=ttmast.year_num
                            JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                            WHERE yr_study = '$fileYear' AND studsub.semester = '$fileSemester'
                                    AND studsub.stud_code = '$externalUserId'
                            ORDER BY prd_code, day_code";

        } else {
            $query = "SELECT room_code, tch_code, day_code, ttmast.sub_code, ttmast.year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '$fileYear' AND semester = '$fileSemester'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$externalUserId'
                         ORDER BY prd_code, day_code";
        }

        $dayCount  = $timetabledef['dayCount'];
        $statement    = $this->getDatabase()->query($query);
        $timetable = array();

        // Build the timetable
        // TODO do this somewhere generic as it's the same for all connectors
        if(is_array($timetabledef['desc'])) {
            foreach ($timetabledef['desc'] as $x => $name) {
                $timetable[$x]['periodName'] = $timetabledef['desc'][$x];
                $timetable[$x]['periodStart'] = $timetabledef['start'][$x];
                $timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
                $timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
                $timetable[$x]['periodSequence'] = 1;
                $timetable[$x]['day'] = array_fill(1, $dayCount, array());
            }

            // Build the class list
            while ($desc = $statement->fetch()) {
                $code = trim($desc['year_grp']) . '-' . trim($desc['sub_code']) . '-' . trim($desc['class']);
                foreach ($timetable as $x => $timetablePeriod) {
                    if ($desc['prd_code'] == $timetablePeriod['periodNumber']
                        && $timetablePeriod['periodSequence'] != 2
                        && !isset($timetable[$x]['day'][$desc['day_code']][$code])
                    ) {
                        $timetable[$x]['day'][$desc['day_code']][$code] = array(
                            'description' => $desc['sub_desc'],
                            'code'        => $code,
                            'room'        => $desc['room_code'],
                            'staffCode'   => $desc['tch_code'],
                        );
                        break;
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        if (!is_array($roomCodes)) {
            $roomCodes = [$roomCodes];
        }
        if (empty($roomCodes)) {
            return [];
        }
        $query = "
            SELECT
                room_code AS RoomCode,
                subtab.sub_desc AS Description,
                ttmast.day_code AS DayNumber,
                RTRIM(CAST(ttmast.cmpy_code AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.tt_id AS VARCHAR(20))) AS TimetableGroup,
                ttmast.cmpy_code AS ClassCampus,
                RTRIM(CAST(ttmast.year_grp AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.sub_code AS VARCHAR(20)))
                    + '-'
                    + RTRIM(CAST(ttmast.class AS VARCHAR(20)))
                    AS ClassCode,
                ttmast.tch_code AS StaffID,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                teacher.tch_name AS StaffSurname,
                CONVERT(VARCHAR(8), ttperiod.prd_start, 108) AS PeriodStart,
                CONVERT(VARCHAR(8), ttperiod.prd_end, 108) AS PeriodEnd,
                ttperiod.prd_desc AS PeriodName
            FROM ttmast
            LEFT JOIN subtab ON
                subtab.cmpy_code=ttmast.cmpy_code AND
                subtab.sub_code=ttmast.sub_code
            LEFT JOIN teacher ON
                teacher.cmpy_code=ttmast.cmpy_code AND
                teacher.tch_code=ttmast.tch_code
            LEFT JOIN ttperiod ON
                ttperiod.cmpy_code=ttmast.cmpy_code AND
                ttperiod.day_code=ttmast.day_code AND
                ttperiod.prd_code=ttmast.prd_code AND
                ttperiod.tt_id=ttmast.tt_id
            WHERE year_num  = :year
            AND   semester  = :semester
            AND   room_code IN (:rooms)
            ORDER BY
                ttmast.day_code ASC,
                ttmast.prd_code ASC
        ";
        $query = strtr(
            $query,
            array(
                ':semester' => "'" . $this->database->quote($this->externalUser->getCurrentSemester()) . "'",
                ':year'     => "'" . $this->database->quote($this->externalUser->getCurrentYear()) . "'",
                ':rooms'    => implode(',', array_map([$this->database, 'smartQuote'], $roomCodes)),
            )
        );
        return $this->database->trimAll($this->database->getAll($query));
    }

    private function setDatabase($database)
    {
        $this->database = $database;
    }

    private function getDatabase()
    {
        return $this->database;
    }
}
