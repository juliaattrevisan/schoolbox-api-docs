<?php
/**
 * Synergetic implementation of External_TimetableInterface
 *
 * @author    Alaress Pty Ltd
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Timetable implements External_TimetableInterface
{

    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    protected $synergeticDay = array();

    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('synergetic');
        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
        $this->groupHandler = new Group();
        $this->campusMapper = new \Schoolbox\CampusMapper();
    }

    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        $query = "SELECT TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq, TimetableDefinition.TimeFrom,
					TimetableDefinition.TimeTo, TimetableDefinition.Description, TimetableDefinition.DayNumber
					FROM SynergyOne.dbo.TimetableDefinition TimetableDefinition
					WHERE TimetableDefinition.FileYear='{$fileYear}' AND
						TimetableDefinition.FileSemester='{$fileSemester}' AND
						TimetableDefinition.TimetableGroup='{$campus->getExtTimetableCode()}' AND
						(TimetableDefinition.DayNumber = '$day' )
					ORDER BY TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq
					";
        $this->getDatabase()->query($query);

        $periodCount = 0;

        while ($period = $this->getDatabase()->getNextArray()) {
            preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeTo'], $TimeToMatches);
            preg_match('/([0-9]+):([0-9]+):[0-9]+:[0-9]+(AM|PM)/', $period['TimeFrom'], $TimeFromMatches);

            $timetableDef['desc'][]     = $period['Description'];
            $timetableDef['start'][]    =
                (int)$TimeFromMatches[1]
                . ':' . $TimeFromMatches[2]
                . strtolower($TimeFromMatches[3]);
            $timetableDef['end'][]      =
                (int)$TimeToMatches[1]
                . ':' . $TimeToMatches[2]
                . strtolower($TimeToMatches[3]);
            $timetableDef['number'][]   = $period['PeriodNumber'];
            $timetableDef['sequence'][] = $period['PeriodNumberSeq'];
            $periodCount++;
        }

        //Fall back to day 0 if nothing found
        if ($periodCount == 0 && $day != 0) {
            return $this->getTimetableDefinition($externalUserId, $campus);
        }

        $timetableDef['periodCount'] = $periodCount;
        $timetableDef['dayCount']    = $this->getTotalDays($campus);
        return $timetableDef;
    }

    //gets current day number
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {

        $days = $this->getTotalDays($campus);

        if (!$date) {
            $date = time();
        }

        $result = $this->getDatabase()->executeProcedure(
            'spgTimetableDay',
            array(
                array('@TimeTableGroup', $campus->getExtTimetableCode(), SQLVARCHAR, false, false, -1),
                array('@Date', date('Y-m-d', $date), SQLVARCHAR, false, false, -1),
                array('@CurrentDay', 'day', SQLINT4, true, false, -1),
            )
        );
        if ($result['success'] && isset($result['output']['@CurrentDay'])) {
            return $result['output']['@CurrentDay'];
        }

        if (date('w', $date) == 0 || date('w', $date) == 6) {
            return false;
        }

        if ($days == 5) {
            // if operating on a 5-day timetable
            $day = date("w", $date);
            if ($day < 1 || $day > 5) {
                $day = 1;
            }

            // calc offset
            $startDate = $this->getLastSynergeticDay($date, $campus);
            if ($startDate) {
                $offset = ($this->synergeticDay[$startDate] - 1) + (date('N', $startDate) + 4);

                // timetable does not start on a Monday: calculate the offset for display
                $today = ($day + $days) - $offset;

                // if offset pushes "today" back further than the diaplayed week, wraparound
                while ($today <= 0) {
                    $today += $days;
                }

                // if offset is negative, mod to bring back into this week
                if ($today > $days) {
                    $today %= $days;
                }

                return $today;
            } else {
                return $day;
            }

        } elseif ($days == 7) {
            // if operating on a 7-day timetable
            $startDate = $this->getLastSynergeticDay($date, $campus);

            //find number of days between both dates
            $difference = intval(($date - $startDate) / 86400);

            //find number of weekends between 2 dates
            $weekends = intval(($difference + date("w", $startDate)) / 7);

            $currentDay = ($this->synergeticDay[$startDate] + $difference - 2 * $weekends - 1) % 7 + 1;

            //make correction for sunday (otherwise we travel back in time)
            if (date("w", $date) == 0) {
                $currentDay = $currentDay % 7 + 1;
            }
            return $currentDay;
        } elseif ($days == 10) {
            // if operating on a 10-day timetable
            $startDate = $this->getLastSynergeticDay($date, $campus);

            if ($this->synergeticDay[$startDate] > 5) {
                $this->synergeticDay[$startDate] = $this->synergeticDay[$startDate] + 1;
            } //extra days for weekend

            $startDate = $startDate - ($this->synergeticDay[$startDate] * 86400);

            if (!$startDate) {
                // Just assume the start of the year if we cannot find anything
                $startDate = mktime(0, 0, 0, 1, 1, date('Y'));
            }

            //find number of days between both dates
            $difference = floor(($date - $startDate) / 86400);
            //find number of weeks between 2 dates
            $weeks = intval(($difference) / 7);

            if (($weeks % 2) == 0) {
                $day = date('w', $date);
                if ($day < 1) {
                    // on sundays move it forward
                    $day = 1;
                }
            } else {
                $day = date('w', $date) + 5;
                if ($day < 6) {
                    // on sundays move it forward
                    $day = 6;
                }

            }
            return $day;
        } else {
            // unknown day number
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
        $query = "SELECT Description FROM luDays WHERE Code = '{$this->getDatabase()->quote($dayNumber)}'";
        $result = $this->getDatabase()->getRow($query);

        if($result['Description']) {
            return $result['Description'];
        }
        else {
            return 'Day ' . $dayNumber;
        }

    }


    public function getLastSynergeticDay($date, \Schoolbox\Campus $campus)
    {
        //This function gets all start of term days entered into synergetic from the date provided backwards.
        //It caches this info so you can get it later again.
        //Be careful if you call this function with a date in the past then in the future it may cause an error
        //TODO:  Make function cache entire year then have better smarts at working out which day to use.

        //date format yyyy-dd-mm 00:00:00

        //Access cache first
        if (!empty($this->synergeticDay)) {
            foreach ($this->synergeticDay as $tdate => $tday) {
                if ($date > $tdate) {
                    return $tdate;
                }
            }
        }

        $days = $this->getTotalDays($campus);

        $nowmssqldate = date('Y-m-d', $date) . ' 00:00:00';

        /* First attempt to get calendar dates based on campus */

        $query = "SELECT *, CONVERT(CHAR(19),CalendarEvents.CalendarDate) as startDate, CalendarEvents.CalendarType as type
			FROM SynergyOne.dbo.CalendarEvents CalendarEvents
			WHERE (CalendarEvents.CalendarType LIKE '" . $campus->getExtTimetableCode() . "_D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
			ORDER BY CalendarEvents.CalendarDate DESC";

        $result = $this->getDatabase()->query($query);
        //Cache all date and days into variable
        while ($day = $this->getDatabase()->getNextArray($result)) {
            //cut out d from CalenderType string
            preg_match('/d([0-9]+)/i', $day['type'], $match);
            if ($match[1] > 0 and $match[1] <= $days) {
                $this->synergeticDay[strtotime($day['startDate'])] = $match[1];
            }
        }

        //yes this is meant to get the first element and return it
        if (!empty($this->synergeticDay)) {
            foreach ($this->synergeticDay as $date => $day) {
                return $date;
            }
        } else {
            /*  Else get calendar dates from the general pool   */
            $query = "SELECT *, CONVERT(CHAR(19),CalendarEvents.CalendarDate) AS startDate, CalendarEvents.CalendarType AS type
                FROM SynergyOne.dbo.CalendarEvents CalendarEvents
                WHERE (CalendarEvents.CalendarType LIKE 'D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
                ORDER BY CalendarEvents.CalendarDate DESC";

            $result = $this->getDatabase()->query($query);
            //Cache all date and days into variable
            while ($day = $this->getDatabase()->getNextArray($result)) {
                //cut out d from CalenderType string
                preg_match('/d([0-9]+)/i', $day['type'], $match);
                if ($match[1] > 0 and $match[1] <= $days) {
                    $this->synergeticDay[strtotime($day['startDate'])] = $match[1];
                }
            }

            if (!empty($this->synergeticDay)) {
                foreach ($this->synergeticDay as $date => $day) {
                    return $date;
                }
            }
        }

        return false;
    }

    /**
     * Returns the period number for the current time.
     *
     * @todo campus object should be passed instead of campus ID
     *       Either that, or a timetable group code, as all other external DB
     *       calls use the key in the external DB, instead of the Schoolbox key.
     *
     * @param \Schoolbox\Campus $campus A campus object (internal, not Synergetic)
     * @param int $now Optional override of time
     *
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        $now = $now ?: time();

        $secondsAfterMidnight = $now - strtotime('00:00:00', $now);

        $campusString = $campus->getExtTimetableCode();

        // firstly, what day number is it?
        $targetDay = $this->getDayNumber($campus);

        // get the day numbers used for this campus
        $query = "
            SELECT DISTINCT DayNumber
            FROM TimetableDefinition t
            WHERE t.FileSemester = '%s'
            AND t.FileYear = '%s'
            AND t.TimetableGroup = '%s'
        ";

        // get available day numbers
        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
        $dayNumbers = array_map(
            function ($r) {
                return $r['DayNumber'];
            },
            $this->getDatabase()->getAll(
                sprintf(
                    $query,
                    $fileSemester,
                    $fileYear,
                    $campusString
                )
            )
        );

        $day = in_array($targetDay, $dayNumbers) ? $targetDay : 0;

        // cannot believe the something like 'LEFT(CONVERT(VARCHAR...' is needed
        $query = "
            SELECT t.TimeFrom, t.TimeTo, t.PeriodNumber, t.PeriodNumberSeq
            FROM TimetableDefinition t
            WHERE t.FileSemester = '%s'
            AND t.FileYear = '%s'
            AND t.TimetableGroup = '%s'
            AND t.DayNumber = '%d'
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeFrom, 126), 10), t.TimeFrom) < '%d'
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeTo, 126), 10), t.TimeTo) > '%d'
        ";

        return $this->getDatabase()->getRow(
            sprintf(
                $query,
                $fileSemester,
                $fileYear,
                $campusString,
                $day,
                $secondsAfterMidnight,
                $secondsAfterMidnight
            )
        );
    }

    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date) {

        $dayNumber = $this->getDayNumber($campus,$date->getUnixTimeStamp());

        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        if (!is_array($timetabledef)) {
            return false;
        }

        $campusString = $campus->getExtTimetableCode();

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));

        $result = $this->getDatabase()->executeProcedure('dbo.spsTimetable',
            array(
                array('@FileYear', $fileYear, SQLINT4),
                array('@FileSemester', $fileSemester, SQLINT4),
                array('@ID', $externalUserId, SQLINT4),
                array('@Date', $date->getDatabaseDate(), SQLVARCHAR),
                array('@IncludeStaffScheduleFlag',1, SQLINT4),
                array('@CheckAttendancesExistFlag',1, SQLINT4),
                array('@IncludeSparesFlag', 0, SQLINT4),
                array('@IncludeRecessFlag', 0, SQLINT4),
            )
        );

        $periodList = array();

        if($result['success']) {
            foreach($result['result'] as $spRow) {
                if (strcasecmp($spRow['TimetableGroup'], $campusString) === 0) {
                    $periodList[] = array(
                        'Room'              => $spRow['Room'],
                        'Description'       => $spRow['ClassDescription'],
                        'PeriodNumber'      => $spRow['Period'],
                        'ClassCampus'       => $spRow['ClassCampus'],
                        'ClassCode'         => $spRow['ClassCode'],
                        'SubstituteFlag'    => $spRow['SubstituteFlag'],
                        'SubstitutedFlag'   => $spRow['SubstitutedFlag'],
                        'AttendanceMarked'  => $spRow['IsMarkedFlag'],
                        'AttendanceEnabled' => $spRow['AttendanceEntryEnabledFlag'],

                    );

                }

            }
        } else {
            // Fall back to getDayTimetable()
            return $this->getDayTimetable(
                $externalUserId,
                $userId,
                $campus,
                $dayNumber
            );
        }

        //for each period
        $timetable = array();

        // Build the timetable
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            $timetable[$x]['class']          = array();
        }

        // Add classes into it
        foreach ($periodList as $desc) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['class'][$desc['ClassCode']])
                ) {
                    $timetable[$x]['class'][$desc['ClassCode']] = array(
                        'description'       => $desc['Description'],
                        'code'              => $desc['ClassCode'],
                        'campus'            => $desc['ClassCampus'],
                        'room'              => $desc['Room'],
                        'staffID'           => $desc['StaffID'],
                        'SubstituteFlag'    => $desc['SubstituteFlag'],
                        'SubstitutedFlag'   => $desc['SubstitutedFlag'],
                        'AttendanceMarked'  => $desc['AttendanceMarked'],
                        'AttendanceEnabled' => $desc['AttendanceEnabled'],
                    );
                    break;
                }
            }
        }

        if (is_array($timetable)) {
            usort($timetable, array($this, 'sortTimetable'));
        }

        return $timetable;

    }


     public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        if (!is_array($timetabledef)) {
            return false;
        }

        $campusString = $campus->getExtTimetableCode();

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT
				Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCampus, Timetable.ClassCode, StaffID
				FROM
				SynergyOne.dbo.StudentClasses StudentClasses,
				SynergyOne.dbo.SubjectClasses SubjectClasses,
				SynergyOne.dbo.Timetable Timetable
				WHERE
				Timetable.ClassCampus = SubjectClasses.ClassCampus AND
				Timetable.ClassCode = SubjectClasses.ClassCode AND
				Timetable.FileSemester = SubjectClasses.FileSemester AND
				Timetable.FileType = SubjectClasses.FileType AND
				Timetable.FileYear = SubjectClasses.FileYear AND
				StudentClasses.FileSemester = SubjectClasses.FileSemester AND
				StudentClasses.FileType = SubjectClasses.FileType AND
				StudentClasses.FileYear = SubjectClasses.FileYear AND
				StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
				StudentClasses.ClassCode = SubjectClasses.ClassCode AND
				Timetable.FileSemester='" . $fileSemester . "' AND
				Timetable.FileYear='" . $fileYear . "' AND
				(StudentClasses.ID='$externalUserId' AND
				Timetable.DayNumber = '$dayNumber' AND
				Timetable.TimetableGroup = '$campusString' AND
				StudentClasses.StartDate <= getdate() AND
				(StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL))
				ORDER BY Timetable.PeriodNumber";
        } else {
            $query = "SELECT Timetable.Room, SubjectClasses.Description,
				Timetable.PeriodNumber, Timetable.ClassCampus, Timetable.ClassCode
				FROM SynergyOne.dbo.SubjectClasses SubjectClasses,
				SynergyOne.dbo.Timetable Timetable
				WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
				Timetable.ClassCode = SubjectClasses.ClassCode AND
				Timetable.FileSemester = SubjectClasses.FileSemester AND
				Timetable.FileType = SubjectClasses.FileType AND
				Timetable.FileYear = SubjectClasses.FileYear AND
				SubjectClasses.FileSemester='" . $fileSemester . "' AND
				SubjectClasses.FileYear='" . $fileYear . "' AND
				Timetable.TimetableGroup = '$campusString' AND
				Timetable.StaffID='$externalUserId' AND
				Timetable.DayNumber = '$dayNumber'
				ORDER BY Timetable.PeriodNumber";
        }

        $result = $this->getDatabase()->query($query);

        //for each period
        $timetable = array();

        // Build the timetable
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            $timetable[$x]['class']          = array();
        }

        // Add classes into it
        while ($desc = $this->getDatabase()->getNextArray($result)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if ($desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['class'][$desc['ClassCode']])
                ) {
                    $timetable[$x]['class'][$desc['ClassCode']] = array(
                        'description' => $desc['Description'],
                        'code'        => $desc['ClassCode'],
                        'campus'      => $desc['ClassCampus'],
                        'room'        => $desc['Room'],
                        'staffID'     => $desc['StaffID'],
                    );
                    break;
                }
            }
        }

        if (is_array($timetable)) {
            usort($timetable, array($this, 'sortTimetable'));
        }

        return $timetable;

    }

    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $this->getDayNumber($campus));

        if (!is_array($timetabledef['desc'])) {
            return false;
        }

        $campusString = $campus->getExtTimetableCode();


        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT
						Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
						Timetable.ClassCampus, Timetable.ClassCode, Timetable.StaffID, vStaff.StaffTitle, vStaff.StaffPreferredName,
						vStaff.StaffSurname
					FROM
						SynergyOne.dbo.StudentClasses StudentClasses,
						SynergyOne.dbo.SubjectClasses SubjectClasses,
						SynergyOne.dbo.Timetable Timetable
					LEFT JOIN
						SynergyOne.dbo.vStaff vStaff ON Timetable.StaffID = vStaff.StaffID
					WHERE
						Timetable.ClassCampus = SubjectClasses.ClassCampus AND
						Timetable.ClassCode = SubjectClasses.ClassCode AND
						Timetable.FileSemester = SubjectClasses.FileSemester AND
						Timetable.FileType = SubjectClasses.FileType AND
						Timetable.FileYear = SubjectClasses.FileYear AND
						StudentClasses.FileSemester = SubjectClasses.FileSemester AND
						StudentClasses.FileType = SubjectClasses.FileType AND
						StudentClasses.FileYear = SubjectClasses.FileYear AND
						StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
						StudentClasses.ClassCode = SubjectClasses.ClassCode AND
						((Timetable.FileSemester=" . $fileSemester . ") AND
						(Timetable.FileYear=" . $fileYear . ") AND
						Timetable.TimetableGroup = '$campusString' AND
						(StudentClasses.ID='$externalUserId'))
						AND StudentClasses.StartDate <= getdate() AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
						AND DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . " AND PeriodNumber <= " . $timetabledef['periodCount'] . "
					ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
        } else {
            // if user has no timetable, $timetabledef['dayCount'] is NULL, not 0, which breaks SQL query
            if ($timetabledef['dayCount'] == null) {
                $timetabledef['dayCount'] = 0;
            }

            $query = "SELECT
						Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
						Timetable.ClassCampus, Timetable.ClassCode
					FROM
						SynergyOne.dbo.SubjectClasses SubjectClasses,
						SynergyOne.dbo.Timetable Timetable
					WHERE
						Timetable.ClassCampus = SubjectClasses.ClassCampus AND
						Timetable.ClassCode = SubjectClasses.ClassCode AND
						Timetable.FileSemester = SubjectClasses.FileSemester AND
						Timetable.FileType = SubjectClasses.FileType AND
						Timetable.FileYear = SubjectClasses.FileYear AND
						SubjectClasses.FileSemester='" . $fileSemester . "' AND
						SubjectClasses.FileYear='" . $fileYear . "' AND
						Timetable.TimetableGroup = '$campusString' AND
						Timetable.StaffID='$externalUserId' AND
						DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . " AND
						PeriodNumber <= " . $timetabledef['periodCount'] . "
					ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
        }

        $dayCount = $timetabledef['dayCount'];

        $result = $this->getDatabase()->query($query);

        // Build the timetable
        $timetable = array();
        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day']            = array_fill(1, $dayCount, array());
        }

        // Add classes for each day into it
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
                        'staffFirstname' => $desc['StaffPreferredName'],
                        'staffSurname'   => $desc['StaffSurname'],
                        'staffTitle'     => $desc['StaffTitle'],
                    );
                    break;
                }
            }
        }

        if (is_array($timetable)) {
            usort($timetable, array($this, 'sortTimetable'));
        }
        return $timetable;

    }

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
     * Returns the number of days in a particular timetable groups cycle
     * Note that this means you do not need to set the days in the campus table for synergetic, we fetch it directly
     *
     * @param \Schoolbox\Campus $campus
     * @return int
     */
    public function getTotalDays(\Schoolbox\Campus $campus) {

        if(! isset($this->days[$campus->getExtTimetableCode()])) {

            $query = "SELECT CycleDays FROM luTimetableGroup WHERE Code = '{$campus->getExtTimetableCode()}'";
            $result = $this->getDatabase()->getRow($query);

            $this->days[$campus->getExtTimetableCode()] = $result['CycleDays'];
        }

        return $this->days[$campus->getExtTimetableCode()];
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param string $roomCodes
     *
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
				SubjectClasses.Description,
				Timetable.Room,
				Timetable.DayNumber,
				Timetable.PeriodNumber,
				Timetable.TimetableGroup,
				Timetable.ClassCampus,
				Timetable.ClassCode,
				Timetable.StaffID,
				vStaff.StaffTitle,
				vStaff.StaffPreferredName,
				vStaff.StaffSurname
			FROM SynergyOne.dbo.Timetable Timetable
			LEFT JOIN SynergyOne.dbo.vStaff vStaff ON Timetable.StaffID = vStaff.StaffID
			LEFT JOIN SynergyOne.dbo.SubjectClasses SubjectClasses ON
				Timetable.ClassCampus = SubjectClasses.ClassCampus AND
				Timetable.ClassCode = SubjectClasses.ClassCode AND
				Timetable.FileSemester = SubjectClasses.FileSemester AND
				Timetable.FileType = SubjectClasses.FileType AND
				Timetable.FileYear = SubjectClasses.FileYear
			WHERE Timetable.FileSemester=:semester
			AND   Timetable.FileYear=:year
			AND   Timetable.Room IN (:rooms)
			ORDER BY
				Timetable.DayNumber ASC,
				Timetable.PeriodNumber ASC
		";
        $query = strtr(
            $query,
            array(
                ':semester' => "'" . $this->database->quote($this->externalUser->getCurrentSemester()) . "'",
                ':year'     => "'" . $this->database->quote($this->externalUser->getCurrentYear()) . "'",
                ':rooms'    => implode(',', array_map([$this->database, 'smartQuote'], $roomCodes)),
            )
        );

        // Jam the timetable definition in.
        $campusList          = array();
        $campusTimetableList = array();
        $result              = array();
        foreach ($this->database->getAll($query) ?: array() as $row) {
            $timetableDay  = $row['TimetableDay'];
            $timetableCode = $row['TimetableGroup'];

            // Load the campus for this code if I haven't tried already
            if (!isset($campusList[$timetableCode])) {
                try {
                    $campusList[$timetableCode] = \Schoolbox\CampusMapper::getInstance()->findOneByColumn(
                        'ext_timetable_code',
                        $timetableCode
                    );
                } catch (\Schoolbox\Mapper\Exception $e) {
                    // No campus or timetable found: set a blank one
                    $campusList[$timetableCode] = false;
                }
            }

            // Load the timetable def for this campus and code if I haven't tried already
            if ($campusList[$timetableCode] instanceof \Schoolbox\Campus) {
                if (!isset($campusTimetableList[$timetableCode][$timetableDay])) {
                    $campusTimetableList[$timetableCode][$timetableDay] = $this->getTimetableDefinition(
                        null,
                        $campusList[$timetableCode],
                        $timetableDay
                    );
                }
            } else {
                $campusTimetableList[$timetableCode][$timetableDay] = false;
            }

            // Did I try and get nothing? skip if so
            $campusTimetable = $campusTimetableList[$timetableCode][$timetableDay];
            if (empty($campusTimetable)) {
                continue;
            }

            // Match day and period against a start and end.
            for ($i = 0; $i < $campusTimetable['periodCount']; $i++) {
                $periodNumber   = $campusTimetable['number'][$i];
                $periodSequence = $campusTimetable['sequence'][$i];

                // (Ignore period sequence 2, everyone else does)
                if ($periodNumber == $row['PeriodNumber'] && $periodSequence != 2) {
                    $row['PeriodStart'] = $campusTimetable['start'][$i];
                    $row['PeriodEnd']   = $campusTimetable['end'][$i];
                    $row['PeriodName']  = $campusTimetable['desc'][$i];
                    $result[]           = $row;
                    break;
                }
            }
        }
        return $result;
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
