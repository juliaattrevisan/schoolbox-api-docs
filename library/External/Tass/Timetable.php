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

    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());

        $this->groupHandler = new Group;
        $this->campusMapper = new \Schoolbox\CampusMapper();
    }


    function getCampus($campus)
    {
        return $this->campusMapper->findByID($campus);
    }

    function getCampusCode($campus)
    {
        list($campusCode, $timetableCode) = explode('-', $this->getCampus($campus)->getExtTimetableCode());
        return $campusCode;
    }

    function getTimetableCode($campus)
    {
        list($campusCode, $timetableCode) = explode('-', $this->getCampus($campus)->getExtTimetableCode());
        return $timetableCode;
    }

    function getDays($campus)
    {

        $campus = $this->campusMapper->findByID($campus);
        return $campus->getTimetableDay();
    }

    function getTimetableDefinition($campus, $day = 0)
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

        $this->getDatabase()->query($query);
        $periodCount = 0;
        while ($period = $this->getDatabase()->getNextArray()) {
            preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['prd_end'], $TimeToMatches);
            preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['prd_start'], $TimeFromMatches);

            $timetableDef['desc'][]   = $period['prd_desc'];
            $timetableDef['start'][]  = (int)$TimeFromMatches[1] . ':' . $TimeFromMatches[2]
                . strtolower($TimeFromMatches[3]);
            $timetableDef['end'][]    = (int)$TimeToMatches[1] . ':' . $TimeToMatches[2] .
                strtolower($TimeToMatches[3]);
            $timetableDef['number'][] = $period['prd_code'];
            $periodCount++;
        }

        $timetableDef['periodCount'] = $longestDay['prd_num'];
        $timetableDef['dayCount']    = $this->getDays($campus);
        return $timetableDef;

    }

    //gets current day number
    function getDayNumber($campus, $date = 0)
    {

        if (!$date) {
            $date = mktime();
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

    function getLastDay($date, $campus)
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
    function getPeriodNumber($campus)
    {
        return 0;
    }

    function getDayTimetable($synergy_id, $userID, $campus, $daynumber)
    {
        if (empty($synergy_id)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($campus, $daynumber);

        if ($this->groupHandler->isStudent($userID)) {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc
                        FROM studsub
                        JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                        JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester 
                                AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS null) AND studsub.yr_study=ttmast.year_num
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE yr_study = '{$_SESSION['FileYear']}' AND studsub.semester = '{$_SESSION['FileSemester']}' 
                                AND studsub.stud_code = '$synergy_id'
                                AND day_code = '$daynumber' 
                        ORDER BY prd_code";
        } else {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '{$_SESSION['FileYear']}' AND semester = '{$_SESSION['FileSemester']}'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$synergy_id'
                            AND day_code = '$daynumber'
                         ORDER BY prd_code";
        }

        $result = $this->getDatabase()->query($query);

        $timetable = array();

        // Build the timetable
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = 1;
            $timetable[$x]['class']          = array();
        }

        // Add classes into it
        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['prd_code'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                ) {
                    $timetable[$x]['class'][] = array(
                        'description' => $desc['sub_desc'],
                        'code'        => trim($desc['year_grp'])
                            . '-' . trim($desc['sub_code'])
                            . '-' . trim($desc['class']),
                        'room'        => $desc['room_code'],
                        'staffCode'   => $desc['tch_code'],
                    );
                    break;
                }
            }
        }
        return $timetable;

    }

    function getFullTimetable($synergy_id, $userID, $campus)
    {
        if (empty($synergy_id)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($campus);

        if ($this->groupHandler->isStudent($userID)) {
            $query = "SELECT room_code, tch_code, ttmast.sub_code, ttmast.year_grp, ttmast.class, prd_code, sub_desc, day_code
                            FROM studsub
                            JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                            JOIN ttmast ON studsub.sub_code=ttmast.sub_code AND studsub.cmpy_code=ttmast.cmpy_code AND studsub.semester=ttmast.semester
                                    AND studsub.class=ttmast.class AND (student.year_grp=ttmast.year_grp OR ttmast.year_grp IS NULL) AND studsub.yr_study=ttmast.year_num
                            JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                            WHERE yr_study = '{$_SESSION['FileYear']}' AND studsub.semester = '{$_SESSION['FileSemester']}'
                                    AND studsub.stud_code = '$synergy_id'
                            ORDER BY prd_code, day_code";

        } else {
            $query = "SELECT room_code, tch_code, day_code, ttmast.sub_code, year_grp, class, prd_code, sub_desc
                        FROM ttmast
                        JOIN subtab ON subtab.sub_code=ttmast.sub_code AND subtab.cmpy_code=ttmast.cmpy_code
                        WHERE year_num = '{$_SESSION['FileYear']}' AND semester = '{$_SESSION['FileSemester']}'
                            AND ttmast.cmpy_code = '{$this->getCampusCode($campus)}'
                            AND tch_code = '$synergy_id'
                         ORDER BY prd_code, day_code";
        }

        $dayCount  = $timetabledef['dayCount'];
        $result    = $this->getDatabase()->query($query);
        $timetable = array();

        // Build the timetable
        // TODO do this somewhere generic as it's the same for all connectors
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = 1;
            $timetable[$x]['day']            = array_fill(1, $dayCount, array());
        }

        // Build the class list
        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['prd_code'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                ) {
                    $timetable[$x]['day'][$desc['day_code']][] = array(
                        'description'    => $desc['sub_desc'],
                        'code'           => trim($desc['year_grp'])
                            . '-' . trim($desc['sub_code']) . '-' . trim($desc['class']),
                        'room'           => $desc['room_code'],
                        'staffCode'      => $desc['tch_code'],
                    );
                    break;
                }
            }
        }
        return $timetable;
    }


    function getDaySubjects($synergy_id, $daynum)
    {
        if (empty($synergy_id)) {
            return array();
        }
        $query = "SELECT Timetable.Room, SubjectClasses.Description,
                         Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus
                         FROM SynergyOne.dbo.SubjectClasses SubjectClasses, SynergyOne.dbo.Timetable Timetable
                         WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                         Timetable.ClassCode = SubjectClasses.ClassCode AND Timetable.FileSemester =
                         SubjectClasses.FileSemester AND Timetable.FileType = SubjectClasses.FileType
                         AND Timetable.FileYear = SubjectClasses.FileYear AND
                         ((SubjectClasses.FileSemester=" . $_SESSION['FileSemester'] . ") AND (SubjectClasses.FileYear='" . $_SESSION['FileYear'] . "')
                         AND (Timetable.StaffID='$synergy_id')) AND Timetable.DayNumber = '$daynum'
                ORDER BY Timetable.PeriodNumber";

        return $this->getDatabase()->getAll($query);

    }

    function getSubjectPeriod($code, $day)
    {
        //give a subject code and day this function returns the period that it occurs in

        $query = "SELECT Timetable.PeriodNumber
            FROM SynergyOne.dbo.Timetable Timetable
            WHERE Timetable.FileSemester='" . $_SESSION['FileSemester'] . "' AND Timetable.FileYear='" . $_SESSION['FileYear'] . "'
            AND Timetable.DayNumber = '$day' AND Timetable.ClassCode = '$code'
        ";

        return $this->getDatabase()->getOne($query);

    }

    function getSubjectName($code)
    {

        $query = "SELECT SubjectClasses.Description
                        FROM SynergyOne.dbo.SubjectClasses SubjectClasses
                        WHERE SubjectClasses.ClassCode = '$code'";
        return $this->getDatabase()->getOne($query);
    }

    public function getRoomTimetable($roomCode)
    {
        $query = "
            SELECT
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
                ttperiod.prd_start AS PeriodStart,
                ttperiod.prd_end AS PeriodEnd,
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
            AND   room_code = :room
            ORDER BY
                ttmast.day_code ASC,
                ttmast.prd_code ASC
        ";
        $query = strtr(
            $query,
            array(
                ':semester' => "'" . $this->database->quote($_SESSION['FileSemester']) . "'",
                ':year'     => "'" . $this->database->quote($_SESSION['FileYear']) . "'",
                ':room'     => "'" . $this->database->quote($roomCode) . "'",
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
