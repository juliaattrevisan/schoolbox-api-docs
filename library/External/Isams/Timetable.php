<?php
/**
 * Isams Timetable Class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * Uncle iSam?
 *    _..._
 *   |||||||
 *   |||||||
 *  _|*****|_
 * ( '-...-' )
 * {`;-----;`}
 * {(| a  a|)}
 *  \|  _\ |/
 *   \  -  /
 *    '{_}`
 */

use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;

class External_Isams_Timetable implements External_TimetableInterface
{
    /** @var MSSQL $database */
    private $database;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Group */
    private $groupHandler;

    /** @var CampusMapper */
    private $campusMapper;

    /**
     * External_Doublefirst_Timetable constructor.
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('isams');
        $this->groupHandler = new Group();
        $this->campusMapper = new CampusMapper();
    }

    /**
     * Retrieve timetable definition
     *
     * @param string $externalUserId User we want the definition for
     * @param CampusModel $campus Campus we want the definition for
     * @param int $day School day we want the definition for
     * @param int $date UNIX timestamp of the date we want to get definition from
     * @return array
     */
    /**
     * Retrieve timetable definition
     *
     * @param string $externalUserId User we want the definition for
     * @param CampusModel $campus Campus we want the definition for
     * @param int $day School day we want the definition for
     * @param int $date UNIX timestamp of the date we want to get definition from
     * @return array
     */
    public function getTimetableDefinition($externalUserId, CampusModel $campus, $day = null, $date = null)
    {
        if (empty($day)) {
            $day = intval(Config::get('external_isams_default_day_id'));
        }

        // Get period information from database

        $this->externalUser->getStudent($externalUserId);

        $query =
            "
            SELECT
              PeriodID as PeriodNumber,
              1 AS PeriodNumberSeq,
              PeriodName AS Description,
              DayName as DayNumber,
              StartTime,
              EndTime
            FROM V_TimetableStructure
            WHERE CampusCode = '{$campus->getExtTimetableCode()}' AND DayID = {$day}
            ORDER BY DayID, CAST(StartTime AS TIME)
	    ";

        $periods = $this->getDatabase()->getAll($query);

        $definitionArray = [];

        foreach ($periods as $period) {
            if (!isset($found_periods[$period["PeriodNumber"]])) {
                $definition = [];
                $definition["desc"] = $period["Description"];
                $definition["start"] = $period["StartTime"];
                $definition["end"] = $period["EndTime"];
                $definition["number"] = $period["PeriodNumber"];
                $definition["sequence"] = $period["PeriodNumberSeq"];

                $definitionArray[] = $definition;
            }
        }

        $queryPeriodCount = "
            SELECT      COUNT(*) As PeriodCount 
            FROM        V_TimetableStructure 
            WHERE       DayID = {$day}
        ";

        $periods = $this->getDatabase()->getRow($queryPeriodCount);
        $definitionArray['periodCount'] = $periods['PeriodCount'];

        $queryDayCount = "
           SELECT     COUNT(*) AS DayCount
           FROM       (SELECT DISTINCT DayID from V_TimetableStructure WHERE CampusCode = '{$campus->getExtTimetableCode()}') as d
        ";

        $days = $this->getDatabase()->getRow($queryDayCount);
        $definitionArray['dayCount'] = $days['DayCount'];

        return $definitionArray;
    }

    /**
     * Retrieve current day number
     *
     * @param CampusModel $campus Campus of the date we are looking at
     * @param int $date UNIX timestamp of the date we are looking at
     * @return bool|string              Day number
     */
    public function getDayNumber(CampusModel $campus, $date = 0)
    {
        if ($date == 0) {
            $date = \Alaress_Date_Format::now();
        } else {
            $date = new \Alaress_Date_Format($date, "U");
        }

        // TODO: Need something added to database to work out real day number

        $queryDay = "
           SELECT     MIN(DayID) AS DayID
           FROM       V_TimetableStructure
           WHERE      CampusCode = '{$campus->getExtTimetableCode()}'
        ";

        $day = $this->getDatabase()->getRow($queryDay);

        if ($day) {
            return $day['DayID'];
        } else {
            return false;
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
        $queryDay = "
           SELECT     DayName 
           FROM       V_TimetableStructure
           WHERE      DayID = $dayNumber
        ";

        $day = $this->getDatabase()->getRow($queryDay);

        if ($day) {
            return $day['DayName'];
        }

        return $dayNumber;
    }

    /**
     * Returns the period number for the current time.
     *
     * @param CampusModel $campus A campus object (internal, not Doublefirst)
     * @param int $now Optional override of time
     * @param \Schoolbox\User $user User we are getting the period for (only required by DF)
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
    {
        $time = date("U");

        $queryPeriod = "
           SELECT     StartTime as TimeFrom,
                      EndTime as TimeTo,
                      PeriodID AS PeriodNumber,
                      1 AS PeriodNumberSeq
           FROM       V_TimetableStructure
           WHERE      CAST('{$time}' AS TIME) BETWEEN CAST(StartTime AS TIME) AND CAST(EndTime AS TIME) AND  
                      DayID = {$this->getDayNumber($campus, $time)}
        ";

        $period = $this->getDatabase()->getRow($queryPeriod);

        if ($period) {
            return ([
                'TimeFrom' => $period["TimeFrom"],
                'TimeTo' => $period["TimeTo"],
                'PeriodNumber' => $period["PeriodNumber"],
                'PeriodNumberSeq' => $period["PeriodNumberSeq"]
            ]);
        }

        return [];
    }

    /**
     * Retrieve timetable for a specific date
     *
     * @param $externalUserId
     * @param $userId
     * @param CampusModel $campus
     * @param Alaress_Date_Format $date
     * @return array|mixed
     */
    public function getTimetableForDate($externalUserId, $userId, CampusModel $campus, Alaress_Date_Format $date)
    {
        $day = $this->getDayNumber($campus, $date);
        return $this->getDayTimetable($externalUserId, $userId, $campus, $day, $date);
    }

    /**
     * Get timetabled classes for specified week day
     *
     * @param $externalUserId
     * @param $userId
     * @param CampusModel $campus
     * @param $dayNumber
     * @param null $date
     * @return array|bool
     */
    public function getDayTimetable($externalUserId, $userId, CampusModel $campus, $dayNumber, $date = null)
    {
        // Can't do anything without the user being supplied

        if (empty($externalUserId)) {
            return array();
        }

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        // Can't do anything without the timetable definition either

        if (!is_array($timetabledef)) {
            return false;
        }

        $timetable = array();

        // Build the timetable

        foreach ($timetabledef as $x => $tt) {
            $timetable[$x]['periodName'] = $tt['desc'];
            $timetable[$x]['periodStart'] = $tt['start'];
            $timetable[$x]['periodEnd'] = $tt['end'];
            $timetable[$x]['periodNumber'] = $tt['number'];
            $timetable[$x]['periodSequence'] = $tt['sequence'];
            $timetable[$x]['class'] = array();
        }

        // Add classes into it

        $queryTimetable = "
            SELECT  '' AS Room,
                    V_TimetableSchedule.ClassCode AS Description,
                    {$dayNumber} AS DayNumber,
                    PeriodID AS PeriodNumber,
                    V_TimetableSchedule.ClassCode AS ClassCode,
                    Tutor1ID AS StaffID
            FROM    V_TimetableSchedule
            JOIN    V_Class ON V_Class.ClassCode = V_TimetableSchedule.ClassCode
            WHERE   PeriodID IN (SELECT PeriodID FROM V_TimetableStructure WHERE DayID = {$dayNumber}) AND     
                    (
                        V_Class.Tutor1ID = '{$externalUserId}' OR
                        V_Class.Tutor2ID = '{$externalUserId}' OR
                        V_Class.StudentID = '{$externalUserId}'
                    )
        ";

        $result = $this->getDatabase()->query($queryTimetable);

        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber'] && !isset($timetable[$x]['class'][$desc['ClassCode']])) {
                    $timetable[$x]['class'][$desc['ClassCode']] = array(
                        'description' => $desc['Description'],
                        'code' => $desc['ClassCode'],
                        'campus' => $desc['ClassCampus'],
                        'room' => $desc['Room'],
                        'staffID' => $desc['StaffID']
                    );
                    break;
                }
            }
        }

        // Sort the timetable appropriately

        if (is_array($timetable)) {
            usort($timetable, array($this, 'sortTimetable'));
        }

        return $timetable;
    }

    /**
     * Get complete timetable
     *
     * @param $externalUserId
     * @param $userId
     * @param CampusModel $campus
     * @return array|bool
     */
    public function getFullTimetable($externalUserId, $userId, CampusModel $campus)
    {
        // Can't do anything with the user being supplied

        if (empty($externalUserId)) {
            return array();
        }

        /** @var array $timetabledef */
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $this->getDayNumber($campus));

        // Can't do anything without the timetable definition either

        if (!is_array($timetabledef)) {
            return false;
        }

        $queryTimetable = "
            SELECT  DISTINCT 
                        '' AS Room,
                        V_TimetableSchedule.ClassCode AS Description,
                        V_TimetableStructure.DayID AS DayNumber,
                        V_TimetableStructure.PeriodID AS PeriodNumber,
                        V_TimetableSchedule.ClassCode AS ClassCode,
                        Tutor1ID AS StaffID,
                        V_Staff.title AS StaffTitle,
                        V_Staff.teacherfirstname AS StaffPreferredName,
                        V_Staff.teacherlastname AS StaffSurname
            FROM        V_TimetableSchedule
            JOIN        V_Class ON V_Class.ClassCode = V_TimetableSchedule.ClassCode
            JOIN        V_TimetableStructure ON V_TimetableStructure.PeriodID = V_TimetableSchedule.PeriodID
            LEFT JOIN   V_Staff ON V_Staff.StaffID = V_Class.Tutor1ID 
            WHERE       (
                            V_Class.Tutor1ID = '{$externalUserId}' OR
                            V_Class.Tutor2ID = '{$externalUserId}' OR
                            V_Class.StudentID = '{$externalUserId}'
                        )
            ORDER BY V_TimetableStructure.DayID, V_TimetableStructure.PeriodID
        ";

        $result = $this->getDatabase()->query($queryTimetable);

        // Create the timetable framework

        $timetable = [];

        foreach ($timetabledef as $x => $tt) {
            $timetable[$x]['periodName'] = $tt['desc'];
            $timetable[$x]['periodStart'] = $tt['start'];
            $timetable[$x]['periodEnd'] = $tt['end'];
            $timetable[$x]['periodNumber'] = $tt['number'];
            $timetable[$x]['periodSequence'] = $tt['sequence'];

            // Fill in each day with blanks

            $timetable[$x]['day'] = array_fill(1, $timetabledef['dayCount'], array());
        }

        // Add classes for each day into it

        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber'] && !isset($timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']])) {

                    $timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']] =
                        [
                            'description' => $desc['Description'],
                            'code' => $desc['ClassCode'],
                            'campus' => $campus->getExtTimetableCode(),
                            'room' => $desc['Room'],
                            'staffID' => $desc['StaffID'],
                            'staffTitle' => $desc['StaffTitle'],
                            'staffFirstname' => $desc['StaffPreferredName'],
                            'staffSurname' => $desc['StaffSurname'],
                        ];

                    break;
                }
            }
        }

        // Sort the timetable appropriately

        if (is_array($timetable)) {
            usort($timetable, array($this, 'sortTimetable'));
        }

        return $timetable;
    }

    /**
     * Sort timetable in chronological order
     *
     * @param $a
     * @param $b
     * @return int
     */
    public function sortTimetable($a, $b)
    {
        $aTime = strtotime($a['periodStart']);
        $bTime = strtotime($b['periodStart']);

        if ($aTime == $bTime) {
            return 0;
        }

        return ($aTime > $bTime) ? +1 : -1;
    }

    /**
     * Gets the timetable for the given room.
     * @param string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        return [];
    }

    /**
     * @var MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
