<?php
/**
 * DoubleFirst Timetable Class
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;

class External_Doublefirst_Timetable implements External_TimetableInterface
{
    /** @var MSSQL $database */
    private $database;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Group */
    private $groupHandler;

    /** @var CampusMapper */
    private $campusMapper;

    /** @var External_Doublefirst_Helper */
    private $helper;

    /**
     * External_Doublefirst_Timetable constructor.
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('doublefirst');
        $this->groupHandler = new Group();
        $this->campusMapper = new CampusMapper();
        $this->helper = new External_Doublefirst_Helper();
    }

    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, CampusModel $campus, $day = 1, $date = null): array
    {
        $fileYear = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));

        /**
         * Doublefirst has timetable definitions for each year level. This is likely to cause
         * problems with teachers as they most likely don't have a year level.
         */

        $query = "SELECT * FROM V_PupilBasicInformation WHERE PupilID = '{$externalUserId}'";
        $studentDetails = $this->getDatabase()->getRow($query);

        /**
         * TODO: Hack for campuses
         * Every year level has a different timetable definition (There are three different times/names for Period 1
         * "Devotions", so I'm just taking the first year level in that campus)
         */

        $arrayTimetableCodes = explode(",", $campus->getExtTimetableCode());
        $yearGroups = $arrayTimetableCodes[0];

        // If we didn't get any hits in the student table, try the staff one

        if ($studentDetails) {
            $type = 'student';
        } else {
            $query = "SELECT * FROM VLStaff WHERE StaffId = '{$externalUserId}'";
            $staffDetails = $this->getDatabase()->getRow($query);

            if ($staffDetails) {
                $type = 'staff';
            } else {
                return [];
            }
        }
        // Get period information from database

        $query = "
            SELECT DISTINCT
            p.PeriodID as PeriodNumber,
            1 AS PeriodNumberSeq,
            CASE WHEN n.PeriodName IS NULL THEN CONCAT('Lesson ', p.PeriodID) ELSE n.PeriodName END AS Description,
            1 AS DayNumber,
            p.PeriodDuration,
            b.BreakDuration

            FROM AttendancePeriods p
            LEFT JOIN PeriodNames n ON
            p.AcademicYear = n.AcademicYear AND
            p.PeriodID = n.PeriodNumber AND
            n.yeargroupid = p.yeargroup

            LEFT JOIN AttendanceBreaks b ON
            b.AcademicYear = p.AcademicYear AND
            b.DayOfWeek = p.DayOfWeek AND
            b.BreakPeriodID = p.PeriodID AND
            b.YearGroup = p.yeargroup
	    ";

        if ($type == 'student') {
            $query .= "
	            WHERE
	            p.AcademicYear = '{$fileYear}' AND
	            p.YearGroup = '{$studentDetails['YearGroupID']}' AND
                p.DayOfWeek = 1
	        ";
        } else {

            /**
             * TODO: This is a hack, teachers could have multiple year levels- not sure how we're gonna work this out yet
             */

            $query .=
                "
		        WHERE
                    p.AcademicYear = '{$fileYear}' AND
                    p.YearGroup IN ({$yearGroups}) AND
                    p.DayOfWeek = 1
    		";
        }

        $query .= "ORDER BY PeriodNumber, BreakDuration DESC";

        $periods = $this->getDatabase()->getAll($query);

        /**
         * Doublefirst doesn't store period start/finish times. It stores a 'start of day' time as minutes from midnight
         * and then stores a 'period duration' (minutes) and each period with the exception of the last period in the day
         * also has a 'break duration' (minutes). To work out the start/finish times we must iterate through the periods
         * and keep track of the minutes as we go along (sigh)- MSSQL does have a perfectly good DATETIME field
         */

        // Get start of day time information from the database

        $queryTimes = "
            SELECT TOP 1 SchoolStartTime, NoOfPeriods
            FROM AttendancePeriodStructure
            WHERE YearGroup = '{$studentDetails['YearGroupID']}'
            AND AcademicYear = '{$fileYear}'
            AND DayOfWeek = 1
        ";

        $times = $this->getDatabase()->getRow($queryTimes);

        $definitionArray = [];

        // Start at the beginning of the day

        $time_current = $times['SchoolStartTime'];

        // Go through each period

        $found_periods = [];

        foreach ($periods as $period) {
            if (!isset($found_periods[$period["PeriodNumber"]])) {
                // TODO: Hack to get rid of duplicate Devotions period

                $found_periods[$period["PeriodNumber"]] = true;

                // Work out the period start/end time

                $period_start = $time_current;
                $period_end = $time_current + $period['PeriodDuration'];

                // If the period description is just a number prefix it with the word "Period" (eg. "1" => "Period 1")

                if (is_numeric($period["Description"])) {
                    $period["Description"] = "Period " . $period["Description"];
                }

                $definition = [];
                $definition["desc"] = $period["Description"];

                // Convert the start/end time which are in minutes from midnight into a readable time string

                $definition["start"] = $this->helper->getTimeFromMinutes($period_start);
                $definition["end"] = $this->helper->getTimeFromMinutes($period_end);
                $definition["number"] = $period["PeriodNumber"];
                $definition["sequence"] = $period["PeriodNumberSeq"];

                $definitionArray[] = $definition;

                // Increment current time by the periods duration

                $time_current += $period['PeriodDuration'];

                // And the break duration

                $time_current += $period['BreakDuration'];
            }
        }

        $query = "SELECT TimetableWeekLength FROM TimetableParameters WHERE AcademicYear = {$fileYear} ORDER BY LastUpdated DESC";
        $weekLength = $this->getDatabase()->getRow($query);

        $definitionArray['dayCount'] = $campus->getTimetableDay() * $weekLength['TimetableWeekLength'];
        $definitionArray['periodCount'] = $times['NoOfPeriods'];;

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

        return $this->helper->getDayNumber($date);
    }

    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber): string
    {
        // Nothing magic here as there is only one week

        return "Day {$dayNumber}";
    }

    /**
     * Returns the period number for the current time.
     *
     * @param CampusModel $campus A campus object (internal, not Doublefirst)
     * @param int $now Optional override of time
     * @param \Schoolbox\User $user User we are getting the period for (only required by DF)
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        /**
         * TODO: This doesn't/can't work for staff. Because each year level has a different timetable structure and
         * staff don't get allocated to a single year level like students do there always exists the opportunity for
         * a staff member to be in multiple periods at any one time.
         */

        // Make sure user was supplied, if not there is nothing we can do

        if ($user == null) {
            return [];
        }

        if ($user->getRole()->isStaff()) {
            return [];
        }

        // Get the time in minutes from midnight (sigh)

        if ($now != 0) {
            $time = date("G", $now) * 60 + date("i", $now);
        } else {
            $time = (date("G") * 60) + date("i");
        }

        // Get all periods

        $query =
            "
            SELECT DISTINCT
            m.PeriodNumber,
            1 AS PeriodNumberSeq,
            n.PeriodName AS Description,
            m.DayOfWeek AS DayNumber,
            p.PeriodDuration

            FROM
            TimetableMatrix m

            INNER JOIN PeriodNames n ON
            n.PeriodNumber = m.PeriodNumber AND
            n.YearGroupID = m.YearGroup AND
            n.AcademicYear = m.AcademicYear

            INNER JOIN AttendancePeriods p ON
            p.AcademicYear = m.AcademicYear AND
            p.YearGroup = m.YearGroup AND
            p.DayOfWeek = m.DayOfWeek AND
            p.PeriodID = m.PeriodNumber

            WHERE
            n.AcademicYear = '{$this->helper->getCurrentYear()}' AND
            n.YearGroupID = '{$campus->getExtTimetableCode()}' AND
            m.DayOfWeek = 1

            UNION

            SELECT
            BreakPeriodID AS PeriodNumber,
            2 AS PeriodNumberSeq,
            BreakName AS Description,
            DayOfWeek AS DayNumber,
            BreakDuration AS PeriodDuration

            FROM
            AttendanceBreaks

            WHERE
            AcademicYear = '{$this->helper->getCurrentYear()}' AND
            YearGroup = '{$campus->getExtTimetableCode()}' AND
            DayOfWeek = 1

            ORDER BY
            PeriodNumber,
            PeriodNumberSeq
        ";

        $periods = $this->getDatabase()->getAll($query);

        // Get the time the day starts

        $query = "SELECT * FROM V_PupilBasicInformation WHERE PupilID = " . $user->getExternalId();
        $studentDetails = $this->getDatabase()->getRow($query);

        $queryTimes =
            "
            SELECT TOP 1 SchoolStartTime
            FROM AttendancePeriodStructure
            WHERE YearGroup = '{$studentDetails['YearGroupID']}'
            AND AcademicYear = '{$this->helper->getCurrentYear()}'
            AND DayOfWeek = 1
        ";

        $times = $this->getDatabase()->getRow($queryTimes);

        $time_current = $times['SchoolStartTime'];

        // Begin the numbers dance keeping track of period times

        foreach ($periods as $period) {
            $period_start = $time_current;
            $period_end = $time_current + $period['PeriodDuration'];

            // Check if this is the current period

            if ($time >= $time_current && $time < $time_current + $period['PeriodDuration'] && $period['PeriodDuration'] > 0) {

                // Lets do some time based gymnastics because someone thought it'd be smart to store time as an integer

                $time_from = $this->helper->getTimeFromMinutes($period_start);
                $time_to = $this->helper->getTimeFromMinutes($period_end);

                return ([
                    'TimeFrom' => $time_from,
                    'TimeTo' => $time_to,
                    'PeriodNumber' => $period["PeriodNumber"],
                    'PeriodNumberSeq' => $period["PeriodNumberSeq"]
                ]);
            }

            $time_current += $period['PeriodDuration'];
        }

        // Not in a period, return a blank array

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, CampusModel $campus, Alaress_Date_Format $date): array
    {
        $day = $this->helper->getDayNumber($date);
        return $this->getDayTimetable($externalUserId, $userId, $campus, $day, $date);
    }

    /**
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, CampusModel $campus, $dayNumber, $date = null): array
    {
        // Can't do anything without the user being supplied
        if (empty($externalUserId)) {
            return array();
        }

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        // Can't do anything without the timetable definition either
        if (!is_array($timetabledef)) {
            return [];
        }

        /**
         * TODO: Total hack for campuses. There is no concept of campus, so we just use a list of year level IDs
         */

        $yearGroups = $campus->getExtTimetableCode();

        // Work out if the externalID is that of a teacher/student

        $query = "SELECT * FROM V_PupilBasicInformation WHERE PupilID = '{$externalUserId}'";
        $studentDetails = $this->getDatabase()->getRow($query);

        // If we didn't get any hits in the student table, try the staff one

        if (!$studentDetails) {
            $type = 'staff';
        } else {
            $type = 'student';
        }

        $fileYear = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));

        $query =
            "
            SELECT DISTINCT
            ssb.BlockName + ' - ' + ssbr.RoomName AS Room,
            sm.Name AS Description,
            tm.DayOfWeek + ((tm.WeekNumber - 1) * {$campus->getTimetableDay()}) AS DayNumber,
            tm.PeriodNumber,
            '{$campus->getExtTimetableCode()}' AS Campus,
            ygsd.SubjectDivisionName AS ClassCode,
        ";

        if ($type == 'student') {

            $query .=
                "
                '' AS StaffID,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                '' AS StaffSurname

                FROM TimetableClassRoom tcr

                LEFT JOIN YearGroupSubjectDivision ygsd ON
                ygsd.DivisionID = tcr.DivisionID AND
                ygsd.SubjectID = tcr.SubjectID AND
                ygsd.YearGroup = tcr.YearGroup AND
                ygsd.AcademicYear = tcr.AcademicYear

                LEFT JOIN SchoolSiteBlock ssb ON
                ssb.SiteID = tcr.SiteID AND
                ssb.BlockID = tcr.BlockID

                LEFT JOIN SchoolSiteBlockRooms ssbr ON
                ssbr.SiteID = tcr.SiteID AND
                ssbr.BlockID = tcr.BlockID AND
                ssbr.RoomID = tcr.RoomID

                LEFT JOIN BatchMaster bm ON
                bm.YearGroup = tcr.YearGroup AND
                bm.SubjectID = tcr.SubjectID AND
                bm.DivisionID = tcr.DivisionID

                JOIN PupilSubjects ps ON
                ps.YearGroup = tcr.YearGroup AND
                ps.SubjectID = tcr.SubjectID AND
                ps.DivisionID = tcr.DivisionID AND
                ps.SchoolYear = tcr.AcademicYear

                JOIN TimetableMatrix tm ON
                tm.MatrixID = tcr.MatrixID AND
                tm.AcademicYear = tcr.AcademicYear AND
                tm.YearGroup = tcr.YearGroup

                JOIN TimetableMaster tn ON
                tn.TimetableName = tm.TimetableName AND
                tn.IsWorking = 1


                JOIN SubjectMaster sm ON
                sm.SubjectID = tcr.SubjectID

                WHERE tcr.AcademicYear = '{$fileYear}'
                AND ps.PupilID = '{$externalUserId}'
                AND tm.DayOfWeek + ((tm.WeekNumber - 1) * {$campus->getTimetableDay()}) = {$dayNumber}

                ORDER BY DayNumber, PeriodNumber
            ";

        } else {

            $query .=
                "
                tcs.StaffID AS StaffID,
                '' AS StaffTitle,
                spd.ForeName AS StaffPreferredName,
                spd.SurName AS StaffSurname

                FROM TimetableClassRoom tcr

                LEFT JOIN YearGroupSubjectDivision ygsd ON
                ygsd.DivisionID = tcr.DivisionID AND
                ygsd.SubjectID = tcr.SubjectID AND
                ygsd.YearGroup = tcr.YearGroup AND
                ygsd.AcademicYear = tcr.AcademicYear

                LEFT JOIN SchoolSiteBlock ssb ON
                ssb.SiteID = tcr.SiteID AND
                ssb.BlockID = tcr.BlockID

                LEFT JOIN SchoolSiteBlockRooms ssbr ON
                ssbr.SiteID = tcr.SiteID AND
                ssbr.BlockID = tcr.BlockID AND
                ssbr.RoomID = tcr.RoomID

                LEFT JOIN BatchMaster bm ON
                bm.YearGroup = tcr.YearGroup AND
                bm.SubjectID = tcr.SubjectID AND
                bm.DivisionID = tcr.DivisionID

                JOIN TimetableMatrix tm ON
                tm.MatrixID = tcr.MatrixID AND
                tm.AcademicYear = tcr.AcademicYear AND
                tm.YearGroup = tcr.YearGroup

                JOIN TimetableClassStaff tcs ON
                tcs.YearGroup = tcr.YearGroup AND
                tcs.SubjectID = tcr.SubjectID AND
                tcs.DivisionID = tcr.DivisionID AND
                tcs.AcademicYear = tcr.AcademicYear AND
                tcs.MatrixID = tm.MatrixID

                LEFT JOIN StaffPersonalDetails spd ON
                spd.StaffID = tcs.StaffID

                JOIN SubjectMaster sm ON
                sm.SubjectID = tcr.SubjectID

                JOIN TimetableMaster tn ON
                tn.TimetableName = tm.TimetableName AND
                tn.IsWorking = 1

                WHERE tcr.AcademicYear = '{$fileYear}'
                AND tcs.StaffID = '{$externalUserId}'
                AND tcr.YearGroup IN ({$yearGroups})
                AND tm.DayOfWeek + ((tm.WeekNumber - 1) * {$campus->getTimetableDay()}) = {$dayNumber}
                ORDER BY DayNumber, PeriodNumber
            ";
        }

        $statement = $this->getDatabase()->query($query);

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

        while ($desc = $statement->fetch()) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['class'][$desc['ClassCode']])
                ) {
                    $timetable[$x]['class'][$desc['ClassCode']] = array(
                        'description' => $desc['Description'],
                        'code' => $desc['ClassCode'],
                        'campus' => $desc['ClassCampus'],
                        'room' => $desc['Room'],
                        'staffID' => $desc['StaffID'],
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
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, CampusModel $campus): array
    {
        // Can't do anything with the user being supplied

        if (empty($externalUserId)) {
            return array();
        }

        /** @var array $timetabledef */
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $this->getDayNumber($campus));

        // Can't do anything without the timetable definition either

        if (!is_array($timetabledef)) {
            return [];
        }

        /**
         * TODO: Total hack for campuses
         */

        $yearGroups = $campus->getExtTimetableCode();

        // Work out if the externalID is that of a teacher/student

        $query = "SELECT * FROM V_PupilBasicInformation WHERE PupilID = '{$externalUserId}'";
        $studentDetails = $this->getDatabase()->getRow($query);

        // If we didn't get any hits in the student table, try the staff one

        if (!$studentDetails) {
            $type = 'staff';
        } else {
            $query = "SELECT * FROM VLPupilDetails WHERE PupilId = '{$externalUserId}'";
            $studentDetails = $this->getDatabase()->getRow($query);

            if ($studentDetails) {
                $type = 'student';
            } else {
                return [];
            }
        }

        $fileYear = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));

        $query =
            "
            SELECT DISTINCT
            ssb.BlockName + ' - ' + ssbr.RoomName AS Room,
            sm.Name AS Description,
            tm.DayOfWeek + ((tm.WeekNumber - 1) * {$campus->getTimetableDay()}) AS DayNumber,
            tm.PeriodNumber,
            '{$campus->getExtTimetableCode()}' AS Campus,
            ygsd.SubjectDivisionName AS ClassCode,
        ";

        if ($type == 'student') {
            $query .=
                "
                '' AS StaffID,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                '' AS StaffSurname

                FROM TimetableClassRoom tcr

                LEFT JOIN YearGroupSubjectDivision ygsd ON
                ygsd.DivisionID = tcr.DivisionID AND
                ygsd.SubjectID = tcr.SubjectID AND
                ygsd.YearGroup = tcr.YearGroup AND
                ygsd.AcademicYear = tcr.AcademicYear

                LEFT JOIN SchoolSiteBlock ssb ON
                ssb.SiteID = tcr.SiteID AND
                ssb.BlockID = tcr.BlockID

                LEFT JOIN SchoolSiteBlockRooms ssbr ON
                ssbr.SiteID = tcr.SiteID AND
                ssbr.BlockID = tcr.BlockID AND
                ssbr.RoomID = tcr.RoomID

                LEFT JOIN BatchMaster bm ON
                bm.YearGroup = tcr.YearGroup AND
                bm.SubjectID = tcr.SubjectID AND
                bm.DivisionID = tcr.DivisionID

                JOIN PupilSubjects ps ON
                ps.YearGroup = tcr.YearGroup AND
                ps.SubjectID = tcr.SubjectID AND
                ps.DivisionID = tcr.DivisionID AND
                ps.SchoolYear = tcr.AcademicYear

                JOIN TimetableMatrix tm ON
                tm.MatrixID = tcr.MatrixID AND
                tm.AcademicYear = tcr.AcademicYear AND
                tm.YearGroup = tcr.YearGroup

                JOIN TimetableMaster tn ON
                tn.TimetableName = tm.TimetableName AND
                tn.IsWorking = 1


                JOIN SubjectMaster sm ON
                sm.SubjectID = tcr.SubjectID

                WHERE tcr.AcademicYear = '{$fileYear}'
                AND ps.PupilID = '{$externalUserId}'

                ORDER BY DayNumber, PeriodNumber
            ";

        } else {

            $query .=
                "
                tcs.StaffID AS StaffID,
                '' AS StaffTitle,
                spd.ForeName AS StaffPreferredName,
                spd.SurName AS StaffSurname

                FROM TimetableClassRoom tcr

                LEFT JOIN YearGroupSubjectDivision ygsd ON
                ygsd.DivisionID = tcr.DivisionID AND
                ygsd.SubjectID = tcr.SubjectID AND
                ygsd.YearGroup = tcr.YearGroup AND
                ygsd.AcademicYear = tcr.AcademicYear

                LEFT JOIN SchoolSiteBlock ssb ON
                ssb.SiteID = tcr.SiteID AND
                ssb.BlockID = tcr.BlockID

                LEFT JOIN SchoolSiteBlockRooms ssbr ON
                ssbr.SiteID = tcr.SiteID AND
                ssbr.BlockID = tcr.BlockID AND
                ssbr.RoomID = tcr.RoomID

                LEFT JOIN BatchMaster bm ON
                bm.YearGroup = tcr.YearGroup AND
                bm.SubjectID = tcr.SubjectID AND
                bm.DivisionID = tcr.DivisionID

                JOIN TimetableMatrix tm ON
                tm.MatrixID = tcr.MatrixID AND
                tm.AcademicYear = tcr.AcademicYear AND
                tm.YearGroup = tcr.YearGroup

                JOIN TimetableClassStaff tcs ON
                tcs.YearGroup = tcr.YearGroup AND
                tcs.SubjectID = tcr.SubjectID AND
                tcs.DivisionID = tcr.DivisionID AND
                tcs.AcademicYear = tcr.AcademicYear AND
                tcs.MatrixID = tm.MatrixID

                LEFT JOIN StaffPersonalDetails spd ON
                spd.StaffID = tcs.StaffID

                JOIN SubjectMaster sm ON
                sm.SubjectID = tcr.SubjectID

                JOIN TimetableMaster tn ON
                tn.TimetableName = tm.TimetableName AND
                tn.IsWorking = 1

                WHERE tcr.AcademicYear = '{$fileYear}'
                AND tcs.StaffID = '{$externalUserId}'
                AND tcr.YearGroup IN ({$yearGroups})
                ORDER BY DayNumber, PeriodNumber
            ";
        }

        $statement = $this->getDatabase()->query($query);

        $dayCount = $timetabledef['dayCount'];

        // Create the timetable framework

        $timetable = [];

        foreach ($timetabledef as $x => $tt) {
            $timetable[$x]['periodName'] = $tt['desc'];
            $timetable[$x]['periodStart'] = $tt['start'];
            $timetable[$x]['periodEnd'] = $tt['end'];
            $timetable[$x]['periodNumber'] = $tt['number'];
            $timetable[$x]['periodSequence'] = $tt['sequence'];

            // Fill in each day with blanks

            $timetable[$x]['day'] = array_fill(1, $dayCount, array());
        }

        // Add classes for each day into it

        while ($desc = $statement->fetch()) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber'] && !isset($timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']])) {

                    $timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']] =
                        [
                            'description' => $desc['Description'],
                            'code' => $desc['ClassCode'],
                            'campus' => $desc['ClassCampus'],
                            'room' => $desc['Room'],
                            'staffID' => $desc['StaffID'],
                            'staffFirstname' => $desc['StaffPreferredName'],
                            'staffSurname' => $desc['StaffSurname'],
                            'staffTitle' => $desc['StaffTitle'],
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
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
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

