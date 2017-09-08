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

class External_Isams_Timetable implements External_TimetableInterface
{
    use External_MssqlConnector;

    /**
     * External_Doublefirst_Timetable constructor.
     */
    public function __construct()
    {
        $this->setupDatabase();
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
        $dayId = $this->getDayId($campus, $date);

        $query =
            "
            SELECT
              V_Periods.intOrder as PeriodNumber,
              1 AS PeriodNumberSeq,
              PeriodName AS Description,
              V_Days.intOrder as DayNumber,
              StartTime,
              EndTime
            FROM V_TimetableStructure
            JOIN V_Days ON V_Days.TblTimetableManagerDaysID = V_TimetableStructure.DayID
            JOIN V_Periods ON V_Periods.TblTimetableManagerPeriodsID = V_TimetableStructure.PeriodID 
            WHERE CampusCode = :campus_code AND DayID = :day_id
            ORDER BY DayID, CAST(StartTime AS TIME)
	    ";
        $periods = $this->getConnector()->executeQueryIterator($query, [
            'campus_code'   => $campus->getExtTimetableCode(),
            'day_id'        => $dayId
        ]);

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
            WHERE       DayID = :day_id
        ";

        $periods = $this->getConnector()->executeQuery($queryPeriodCount,[
            'day_id' => $dayId
        ])->fetch();

        $definitionArray['periodCount'] = $periods['PeriodCount'];

        $queryDayCount = "
           SELECT     COUNT(*) AS DayCount
           FROM       (SELECT DISTINCT DayID from V_TimetableStructure WHERE CampusCode = :campus_code) AS d
        ";

        $days = $this->getConnector()->executeQuery($queryDayCount, [
            'campus_code' => $campus->getExtTimetableCode()
        ])->fetch();

        $definitionArray['dayCount'] = $days['DayCount'];

        return $definitionArray;
    }

    /**
     * Retrieve current day number
     *
     * @param CampusModel $campus Campus of the date we are looking at
     * @param int $date UNIX timestamp of the date we are looking at
     * @return int              Day number
     */
    public function getDayNumber(CampusModel $campus, $date = 0)
    {
        return date('w', strototime($date));
    }

    public function getDayId(CampusModel $campus, $date = 0)
    {
        // The days in Isams have no correlation to an actual calendar date- it is purely based on the day of the week
        // (Monday - Friday) and a week number (of which there is a single week set up for each campus).
        //
        // To work out the day number we are doing the following...
        //
        // 1. Start with the campus external timetable code (SS/PS/TK) stored in $campus->getExtTimetableCode()
        // 2. Look up the corresponding week ID number in V_Weeks where the 'txtShortName' field is equal to this campus code
        // 3. Look up the day number 'V_Days.TblTimetableManagerDaysId' using the week ID we found in step 2, along with the
        //    day of the week number (where 2 = Monday, 6 = Friday) which is stored in the 'intDay' field.

        // Add one because ISAMS starts days at 1 and PHP starts at 0

        if($date) {
            $dayOfWeek = date("w",strtotime($date)) + 1;
        }
        else {
            $dayOfWeek = date("w") + 1;
        }


        $queryDay = "
           SELECT   TblTimetableManagerDaysID as DayID 
           FROM     V_Days 
           WHERE    intWeek = (
              SELECT  TOP 1 MAX(TblTimetableManagerWeeksID) 
              FROM    V_Weeks 
              WHERE   txtShortName = :txt_short_name AND 
                      intActive = 1
           ) AND 
           intDay = :day_of_week
        ";

        $day = $this->getConnector()->executeQuery($queryDay, [
            'txt_short_name'    => $campus->getExtTimetableCode(),
            'day_of_week'       => $dayOfWeek
        ])->fetch();

        if ($day['DayID']) {
            return $day['DayID'];
        } else {
            // If that falls in a heap, use the default one specified in the config
            return (int)$this->config->get('external_isams_default_day_id');
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
        $names = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday'
        ];

        return $names[($dayNumber - 1) % 5];
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
           WHERE      CAST(:time_val AS TIME) BETWEEN CAST(StartTime AS TIME) AND CAST(EndTime AS TIME) AND  
                      DayID = :day_id
        ";

        $period = $this->getConnector()->executeQuery($queryPeriod, [
            'time_val'  => $time,
            'day_id'    => $this->getDayId($campus, $time)
        ])->fetch();

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

        $dayId = $this->getDayId($campus,$date);

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber, $date);

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
            SELECT  V_TimetableSchedule.venue AS Room,
                    V_TimetableSchedule.ClassName AS Description,
                    V_Days.intOrder AS DayNumber,
                    V_Periods.intOrder AS PeriodNumber,
                    V_TimetableSchedule.ClassCode AS ClassCode,
                    Tutor1ID AS StaffID
            FROM    V_TimetableSchedule
            JOIN    V_TimetableStructure ON V_TimetableStructure.PeriodID = V_TimetableSchedule.PeriodID 
            JOIN    V_Class ON V_Class.ClassCode = V_TimetableSchedule.ClassCode
            JOIN    V_Periods ON V_Periods.TblTimetableManagerPeriodsID = V_TimetableStructure.PeriodID
            JOIN    V_Days ON V_Days.TblTimetableManagerDaysID = V_TimetableStructure.DayID
            WHERE   V_TimetableStructure.PeriodID IN (SELECT PeriodID FROM V_TimetableStructure WHERE DayID = :day_id) AND   
                    CampusCode = :campus_code AND
                    (
                        V_Class.Tutor1ID = :external_user_id OR
                        V_Class.Tutor2ID = :external_user_id OR
                        V_Class.StudentID = :external_user_id
                    )
        ";

        $iterator = $this->getConnector()->executeQueryIterator($queryTimetable, [
            'day_id'            => $dayId,
            'campus_code'       => $campus->getExtTimetableCode(),
            'external_user_id'  => $externalUserId
        ]);

        foreach($iterator as $desc) {
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
            SELECT 
                        V_TimetableSchedule.venue AS Room,
                        V_TimetableSchedule.ClassName AS Description,
                        V_Days.intOrder AS DayNumber,
                        V_Periods.intOrder AS PeriodNumber,
                        V_TimetableSchedule.ClassCode AS ClassCode,
                        Tutor1ID AS StaffID,
                        V_Staff.title AS StaffTitle,
                        V_Staff.teacherfirstname AS StaffPreferredName,
                        V_Staff.teacherlastname AS StaffSurname
            FROM        V_TimetableSchedule
            JOIN        V_Class ON V_Class.ClassCode = V_TimetableSchedule.ClassCode
            JOIN        V_TimetableStructure ON V_TimetableStructure.PeriodID = V_TimetableSchedule.PeriodID
            JOIN        V_Periods ON V_Periods.TblTimetableManagerPeriodsID = V_TimetableStructure.PeriodID
            JOIN        V_Days ON V_Days.TblTimetableManagerDaysID = V_TimetableStructure.DayID
            LEFT JOIN   V_Staff ON V_Staff.StaffID = V_Class.Tutor1ID 
            WHERE       (
                            V_Class.Tutor1ID = :external_user_id OR
                            V_Class.Tutor2ID = :external_user_id OR
                            V_Class.StudentID = :external_user_id
                        )
                        AND CampusCode = :campus_code
            ORDER BY V_Days.intOrder, V_Periods.intOrder
        ";

        $iterator = $this->getConnector()->executeQueryIterator($queryTimetable, [
            'external_user_id'  => $externalUserId,
            'campus_code'       => $campus->getExtTimetableCode()
        ]);

        
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
        foreach ($iterator as $desc) {
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
}
