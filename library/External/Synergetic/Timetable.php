<?php
/**
 * Synergetic implementation of External_TimetableInterface
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Cache;
use Schoolbox\Campus;

class External_Synergetic_Timetable implements External_TimetableInterface
{
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    const FORMAT_ISO8601 = 126;

    /** @var MSSQL $database */
    private $database;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var array */
    protected $synergeticDay = [];

    /** @var Cache */
    private static $timetableDefinitionCache;

    /** @var Cache */
    private static $dayNumberCache;

    /** @var Cache */
    private static $timetableForDateCache;

    /** @var Cache */
    private static $dayTimetableCache;

    /** @var Group */
    private $groupHandler;

    /** @var array */
    private $days;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('synergetic');
        $this->groupHandler = new Group();

        self::$timetableDefinitionCache = new Cache(function ($externalUserId, $campusId, $day = 0, $date = null) {
            return $this->fetchTimetableDefinition($externalUserId, $campusId, $day, $date);
        });

        self::$timetableForDateCache = new Cache(function ($externalUserId, $userId, Campus $campus, Alaress_Date_Format $date) {
            return $this->fetchTimetableForDate($externalUserId, $userId, $campus, $date);
        });

        self::$dayTimetableCache = new Cache(function ($externalUserId, $userId, Campus $campus, $dayNumber, $date = null) {
            return $this->fetchDayTimetable($externalUserId, $userId, $campus, $dayNumber, $date);
        });

        self::$dayNumberCache = new Cache(function (Campus $campus, $date = 0) {
            return $this->fetchDayNumber($campus, $date);
        });
    }


    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        $args = [ $externalUserId, $campus, $day, $date ];
        $key  = sprintf('%d-%s-%d-%s', $externalUserId === null ? '' : $externalUserId, $campus->getExtTimetableCode(), $day, $date);

        return self::$timetableDefinitionCache->get($key, $args);
    }


    /**
     * @param string|null $externalUserId
     * @param Campus $campus
     * @param int    $day
     * @param string $date
     * @return array
     * @throws InvalidArgumentException
     */
    private function fetchTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        // check if there is a calendar event remapping the day number for this specific date

        if ($date != null)
        {
            $statement = $this->getDatabase()->query("SELECT CalendarType FROM CalendarEvents WHERE CalendarDate = '{$date}' AND CalendarType LIKE 'D=D%'");

            if ($row = $statement->fetch()) {
                $overrideDay = trim(substr($row["CalendarType"], 3));
                if (!empty($overrideDay) && is_numeric($overrideDay)) {

                    // Yep, found overriding day number. Use this instead

                    $day = $overrideDay;
                }
            }
        }

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        $query = "SELECT TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq, TimetableDefinition.TimeFrom,
					TimetableDefinition.TimeTo, TimetableDefinition.Description, TimetableDefinition.DayNumber
					FROM dbo.TimetableDefinition TimetableDefinition
					WHERE TimetableDefinition.FileYear='{$fileYear}' AND
						TimetableDefinition.FileSemester='{$fileSemester}' AND
						TimetableDefinition.TimetableGroup='{$campus->getExtTimetableCode()}' AND
						(TimetableDefinition.DayNumber = '$day' )
					ORDER BY TimetableDefinition.PeriodNumber, TimetableDefinition.PeriodNumberSeq
					";
        $statement = $this->getDatabase()->query($query);

        $periodCount = 0;

        while ($period = $statement->fetch()) {


            $timetableDef['desc'][]     = $period['Description'];
            $timetableDef['start'][]    = date("g:ia", strtotime($period['TimeFrom']));
            $timetableDef['end'][]      = date("g:ia", strtotime($period['TimeTo']));
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


    /**
     * @param Campus $campus
     * @param int    $date
     * @return bool|int|string
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        $key = sprintf('%s-%s', $campus->getExtTimetableCode(), date('Y-m-d', $date));

        return self::$dayNumberCache->get($key, func_get_args());
    }


    /**
     * @param Campus $campus
     * @param int    $date
     * @return bool|int|string
     */
    private function fetchDayNumber(Campus $campus, $date = 0)
    {
        if (!$date) {
            $date = time();
        }

        if (!$this->isDateWithinCurrentFileSemester($date)) {
            return 0;
        }

        $statement = $this->getDatabase()->prepareProcedure('spgTimetableDay');

        $statement->bindInputs([
            'TimetableGroup' => $campus->getExtTimetableCode(),
            'Date' => date('Y-m-d', $date)
        ]);

        $currentDay = null;

        $outputs['CurrentDay'] = [
            'value' => &$currentDay,
            'type' => PDO::PARAM_INT,
            'length' => 4
        ];

        $statement->bindOutputs($outputs);
        $statement->execute();
        $statement->close();

        return $currentDay ?? 0;
    }


    /**
     * Is this date timestamp within the current file semester?
     *
     * If not, day numbers and timetables won't be shown.
     *
     * @param int $date
     * @return bool
     */
    private function isDateWithinCurrentFileSemester(int $date): bool
    {
        $query = '
            SELECT COUNT(*)
            FROM   FileSemesters
            WHERE  FileYear                 =  :year
            AND    FileSemester             =  :semester
            AND    CONVERT(DATE, StartDate) <= :startDate
            AND    CONVERT(DATE, EndDate)   >= :endDate
        ';
        $dateObject = Alaress_Date_Format::newFromTimestamp($date);
        $params     = [
            'year'      => $this->externalUser->getCurrentYear(),
            'semester'  => $this->externalUser->getCurrentSemester(),
            'startDate' => $dateObject->getDatabaseDate(),
            'endDate'   => $dateObject->getDatabaseDate(),
        ];

        return $this->database->query($query, $params)->fetchColumn() > 0;
    }


    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber): string
    {
        $query = "SELECT Description FROM luDays WHERE Code = '{$this->getDatabase()->quote($dayNumber)}'";
        $result = $this->getDatabase()->getRow($query);

        if($result['Description']) {
            return $result['Description'];
        }

        return 'Day ' . $dayNumber;

    }


    public function getLastSynergeticDay($date, Campus $campus)
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
			FROM dbo.CalendarEvents CalendarEvents
			WHERE (CalendarEvents.CalendarType LIKE '" . $campus->getExtTimetableCode() . "_D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
			ORDER BY CalendarEvents.CalendarDate DESC";

        $statement = $this->getDatabase()->query($query);
        //Cache all date and days into variable
        while ($day = $statement->fetch()) {
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
                FROM dbo.CalendarEvents CalendarEvents
                WHERE (CalendarEvents.CalendarType LIKE 'D%') AND CalendarEvents.CalendarDate <= '$nowmssqldate'
                ORDER BY CalendarEvents.CalendarDate DESC";

            $statement = $this->getDatabase()->query($query);
            //Cache all date and days into variable
            while ($day = $statement->fetch()) {
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
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
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
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeFrom, ".self::FORMAT_ISO8601."), 10), t.TimeFrom) < '%d'
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeTo, ".self::FORMAT_ISO8601."), 10), t.TimeTo) > '%d'
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
        ) ?: [];
    }


    /**
     * @inheritDoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        $key = sprintf('%d-%d-%s-%s', $externalUserId, $userId, $campus->getExtTimetableCode(), $date->getDatabaseDate());

        return self::$timetableForDateCache->get($key, func_get_args());
    }


    /**
     * @param string              $externalUserId
     * @param int                 $userId
     * @param Campus              $campus
     * @param Alaress_Date_Format $date
     * @throws InvalidArgumentException
     * @return array
     */
    private function fetchTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        $dayNumber = $this->getDayNumber($campus, $date->getUnixTimeStamp());

        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $periodList = [];
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber);

        if (!is_array($timetabledef['desc'])) {
            return [];
        }

        $campusString = $campus->getExtTimetableCode();

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));

        $statement = $this->database->prepareProcedure('dbo.spsTimetable');
        $statement->bindInputs(
            [
                'FileYear' => $fileYear,
                'FileSemester' => $fileSemester,
                'ID' => $externalUserId,
                'Date' => $date->getDatabaseDate(),
                'IncludeStaffScheduleFlag' => 1,
                'CheckAttendancesExistFlag' => 1,
                'IncludeSparesFlag' => 0,
                'IncludeRecessFlag' => 0
            ]
        );

        $iterator = $statement->getIterator();

        $count = 0;

        $periodsForSequenceIds = [];
        foreach ($iterator as $spRow) {
            if (strcasecmp($spRow['TimetableGroup'], $campusString) === 0) {
                // There is a SeqLinkedTo field, which is only set for linked classes.
                $sequenceId            = $spRow['SeqLinkedTo'] ?? null;
                $multiPeriodAttendance = (bool)($spRow['MultiPeriodAttendanceMode'] ?? false);
                if (isset($sequenceId) && $multiPeriodAttendance) {
                    $periodsForSequenceIds[$sequenceId][] = $spRow['Period'];
                }
                $periodList[] = [
                    'Room'                      => $spRow['Room'],
                    'Description'               => $spRow['ClassDescription'],
                    'PeriodNumber'              => $spRow['Period'],
                    'ClassCampus'               => $spRow['ClassCampus'],
                    'ClassCode'                 => $spRow['ClassCode'],
                    'FileType'                  => $spRow['FileType'],
                    'SubstituteFlag'            => $spRow['SubstituteFlag'],
                    'SubstitutedFlag'           => $spRow['SubstitutedFlag'],
                    'PeriodSequence'            => $sequenceId,
                    'AttendancePeriods'         => [$spRow['Period']],
                    'AttendanceMarked'          => $spRow['IsMarkedFlag'],
                    'AttendanceEnabled'         => $spRow['AttendanceEntryEnabledFlag'],
                    'ClassCancelled'            => $spRow['ClassCancelledFlag'],
                    'MultiPeriodAttendanceMode' => $multiPeriodAttendance,
                ];
            }
            $count++;
        }

        // Sort the periods and period sequences for niceness
        usort(
            $periodList,
            static function ($a, $b) {
                return $a['PeriodNumber'] <=> $b['PeriodNumber'];
            }
        );
        foreach ($periodsForSequenceIds as &$periodsForSequenceId) {
            sort($periodsForSequenceId);
            $periodsForSequenceId = array_unique($periodsForSequenceId);
        }
        unset($periodsForSequenceId);

        // Merge period numbers for attendance all together
        foreach ($periodList as &$period) {
            $periodSequence = $period['PeriodSequence'];
            if (isset($periodsForSequenceIds[$periodSequence]) && $period['MultiPeriodAttendanceMode']) {
                $period['AttendancePeriods'] = $periodsForSequenceIds[$periodSequence];

                // Clear the list of periods for sequence IDs, so that later
                // classes in the sequence don't get an attendance link.
                $periodsForSequenceIds[$periodSequence] = [];
            }
        }
        unset($period);

        if ($count == 0) {
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
                        'FileType'          => $desc['FileType'],
                        'SubstituteFlag'    => $desc['SubstituteFlag'],
                        'SubstitutedFlag'   => $desc['SubstitutedFlag'],
                        'AttendanceMarked'  => $desc['AttendanceMarked'],
                        'AttendanceEnabled' => $desc['AttendanceEnabled'] && !$desc['ClassCancelled'],
                        'AttendancePeriods' => $desc['AttendancePeriods'],
                        'ClassCancelled'    => $desc['ClassCancelled'],
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



    /**
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, $date = null): array
    {
        $key = sprintf('%d-%d-%s-%d-%s', $externalUserId, $userId, $campus->getExtTimetableCode(), $dayNumber, $date);

        return self::$dayTimetableCache->get($key, func_get_args());
    }


    /**
     * @param string    $externalUserId
     * @param int    $userId
     * @param Campus $campus
     * @param int    $dayNumber
     * @param string $date
     * @throws InvalidArgumentException
     * @throws MssqlQueryException
     * @return array
     */
    private function fetchDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, $date = null): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }
        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber, $date);

        if (!is_array($timetabledef)) {
            return [];
        }

        $campusString = $campus->getExtTimetableCode();

        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear($externalUserId));
        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT
				Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCampus, Timetable.ClassCode, StaffID
				FROM
				dbo.StudentClasses StudentClasses,
				dbo.SubjectClasses SubjectClasses,
				dbo.Timetable Timetable
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
				FROM dbo.SubjectClasses SubjectClasses,
				dbo.Timetable Timetable
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

        $statement = $this->getDatabase()->query($query);

        //for each period
        $timetable = array();

        // Build the timetable
        if(is_array($timetabledef['desc'])) {
            foreach ($timetabledef['desc'] as $x => $name) {
                $timetable[$x]['periodName'] = $timetabledef['desc'][$x];
                $timetable[$x]['periodStart'] = $timetabledef['start'][$x];
                $timetable[$x]['periodEnd'] = $timetabledef['end'][$x];
                $timetable[$x]['periodNumber'] = $timetabledef['number'][$x];
                $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
                $timetable[$x]['class'] = array();
            }
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

    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $this->getDayNumber($campus));

        if (!is_array($timetabledef['desc'])) {
            return [];
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
						dbo.StudentClasses StudentClasses,
						dbo.SubjectClasses SubjectClasses,
						dbo.Timetable Timetable
					LEFT JOIN
						dbo.vStaff vStaff ON Timetable.StaffID = vStaff.StaffID
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
						AND DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . "
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
						dbo.SubjectClasses SubjectClasses,
						dbo.Timetable Timetable
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
						DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . "
					ORDER BY Timetable.PeriodNumber, Timetable.DayNumber";
        }

        $dayCount = $timetabledef['dayCount'];

        $statement = $this->getDatabase()->query($query);

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

        while ($desc = $statement->fetch()) {
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
     * @param Campus $campus
     * @return int
     */
    public function getTotalDays(Campus $campus) {

        if(! isset($this->days[$campus->getExtTimetableCode()])) {

            $query = "SELECT CycleDays FROM luTimetableGroup WHERE Code = '{$campus->getExtTimetableCode()}'";
            $result = $this->getDatabase()->getRow($query);

            $this->days[$campus->getExtTimetableCode()] = $result['CycleDays'];
        }

        return $this->days[$campus->getExtTimetableCode()];
    }


    /**
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        $filteredRoomCodes = array_filter($roomCodes);
        if (empty($filteredRoomCodes)) {
            return [];
        }
        $query = "
			SELECT
				SubjectClasses.Description,
				Timetable.Room AS RoomCode,
				Timetable.DayNumber,
				Timetable.PeriodNumber,
				Timetable.TimetableGroup,
				Timetable.ClassCampus,
				Timetable.ClassCode,
				Timetable.StaffID,
				vStaff.StaffTitle,
				vStaff.StaffPreferredName,
				vStaff.StaffSurname
			FROM dbo.Timetable Timetable
			LEFT JOIN dbo.vStaff vStaff ON Timetable.StaffID = vStaff.StaffID
			LEFT JOIN dbo.SubjectClasses SubjectClasses ON
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
                ':rooms'    => implode(',', array_map([$this->database, 'smartQuote'], $filteredRoomCodes)),
            )
        );

        // Jam the timetable definition in.
        $campusList          = array();
        $campusTimetableList = array();
        $result              = array();
        foreach ($this->database->getAll($query) ?: array() as $row) {
            $timetableDay  = $row['DayNumber'];
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
            if ($campusList[$timetableCode] instanceof Campus) {
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
