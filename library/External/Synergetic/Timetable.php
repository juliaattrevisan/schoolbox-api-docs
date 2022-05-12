<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Cache;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Database\Factory;

/**
 * Class External_Synergetic_Timetable
 *
 * Synergetic implementation of External_TimetableInterface
 */
class External_Synergetic_Timetable implements External_TimetableInterface
{
    use External_Synergetic_ValidateExternalIdTrait;


    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    public const FORMAT_ISO8601 = 126;


    /**
     * Default File Type used by dbo.spsTimetable
     */
    private const DEFAULT_FILE_TYPE = 'A';


    /**
     * @var IConnector $database
     */
    private $database;


    /**
     * @var External_User
     */
    private $externalUser;


    /**
     * @var array
     */
    protected $synergeticDay = [];


    /**
     * @var array|null
     */
    private $semesters;


    /**
     * @var Cache
     */
    private static $timetableDefinitionCache;


    /**
     * @var Cache
     */
    private static $dayNumberCache;


    /**
     * @var Cache
     */
    private static $timetableForDateCache;


    /**
     * @var Cache
     */
    private static $dayTimetableCache;


    /**
     * @var Group
     */
    private $groupHandler;


    /**
     * @var array
     */
    private $days;


    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
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
        $args = [$externalUserId, $campus, $day, $date];
        $key  = sprintf('%d-%s-%d-%s', $externalUserId === null ? '' : $externalUserId, $campus->getExtTimetableCode(), $day, $date);

        return self::$timetableDefinitionCache->get($key, $args);
    }


    /**
     * @param string|null $externalUserId
     * @param Campus      $campus
     * @param int         $day
     * @param string      $date
     * @return array
     */
    private function fetchTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        // check if there is a calendar event remapping the day number for this specific date
        $dateObject = null;
        if ($date != null) {
            $statement = $this->getDatabase()->query("SELECT CalendarType FROM CalendarEvents WHERE CalendarDate = '{$date}' AND CalendarType LIKE '%=D%'");
            if ($row = $statement->fetch()) {
                // Extract the Calendar Type and explode it to its two different parts
                $definitionDayOverride = explode("=", $row["CalendarType"]);
                $overrideDay           = trim(substr($definitionDayOverride[1], 1));

                // Only use the second part of the string and ignore the first characted.
                // This makes assumptions that Synergetic setup correctly with either D=D# or D#=D#, and the explode doesn't get a row without =
                if (!empty($overrideDay) && is_numeric($overrideDay)) {
                    // Yep, found overriding day number. Use this instead
                    $day = (int)$overrideDay;
                }
            }
            $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        }
        $params = [
            'fileSemester'   => $this->getFileSemester($externalUserId, $dateObject),
            'fileYear'       => $this->getFileYear($externalUserId, $dateObject),
            'timetableGroup' => $campus->getExtTimetableCode(),
            'day'            => $day,
        ];

        $allowedFileTypes    = $this->getAllowedFileTypes();
        $allowedFileTypesSql = '';
        if (!empty($allowedFileTypes)) {
            $fileTypeBindings = Builder::getBindings($allowedFileTypes, 'fileType');
            $allowedFileTypesSql = " TimetableDefinition.FileType IN ({$fileTypeBindings->getPlaceholder()}) AND ";
            $params = array_merge($params, $fileTypeBindings->getParams());
        }

        $query = "SELECT TimetableDefinition.PeriodNumber,
                         TimetableDefinition.PeriodNumberSeq,
                         TimetableDefinition.TimeFrom,
                         TimetableDefinition.TimeTo,
                         TimetableDefinition.Description,
                         TimetableDefinition.DayNumber,
                         TimetableDefinition.FileType
                    FROM dbo.TimetableDefinition TimetableDefinition
                    WHERE TimetableDefinition.FileYear = :fileYear AND
                        TimetableDefinition.FileSemester = :fileSemester AND
                        TimetableDefinition.TimetableGroup = :timetableGroup AND
                        {$allowedFileTypesSql}
                        TimetableDefinition.DayNumber IN (:day, '0')
                    ORDER BY TimetableDefinition.FileType,
                             TimetableDefinition.DayNumber DESC,
                             TimetableDefinition.PeriodNumber,
                             TimetableDefinition.PeriodNumberSeq
                    ";
        unset($statement);
        $statement = $this->getDatabase()->query($query, $params);

        $periodCount = 0;

        // If there are more than one FileTypes, dayNumber can be different (either the actual day number or default 0)
        // This map stores the dayNumber for each FileType
        $fileTypeDayNumberMap = [];

        while ($period = $statement->fetch()) {
            if (!key_exists($period['FileType'], $fileTypeDayNumberMap)) {
                // We assume the day number for this FileType to be this periods day number
                $fileTypeDayNumberMap[$period['FileType']] = $period['DayNumber'];
            } elseif ($period['DayNumber'] !== $fileTypeDayNumberMap[$period['FileType']]) {
                continue;
            }
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
     * @throws InvalidArgumentException
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        $key = sprintf('%s-%s', $campus->getExtTimetableCode(), date('Y-m-d', $date));

        return self::$dayNumberCache->get($key, func_get_args());
    }


    /**
     * @param Campus $campus
     * @param int    $date
     * @return int
     * @throws \Alaress\Database\Exception
     */
    private function fetchDayNumber(Campus $campus, $date = 0)
    {
        if (!$date) {
            $date = time();
        }

        if (empty($this->getFileSemesterFileYear(Alaress_Date_Format::newFromTimestamp($date)))) {
            return 0;
        }

        $statement = $this->getDatabase()->prepareProcedure('spgTimetableDay');

        $statement->bindInputs([
            'TimetableGroup' => $campus->getExtTimetableCode(),
            'Date'           => date('Y-m-d', $date),
        ]);

        $currentDay = null;

        $outputs['CurrentDay'] = [
            'value'  => &$currentDay,
            'type'   => PDO::PARAM_INT,
            'length' => 4,
        ];

        $statement->bindOutputs($outputs);
        $statement->execute();
        $statement->close();

        // @phpstan-ignore-next-line: $currentDay can be set by reference
        return $currentDay ?? 0;
    }


    /**
     * @param string|null              $externalId
     * @param Alaress_Date_Format|null $date
     * @return string|null
     */
    private function getFileYear(?string $externalId = null, ?Alaress_Date_Format $date = null): ?string
    {
        // if date is provided and is not today, get fileSemester and year from the date
        if ($date && !$date->isToday()) {
            $dateFileSemesterYear = $this->getFileSemesterFileYear($date);
            if (isset($dateFileSemesterYear['fileYear'])) {
                return $dateFileSemesterYear['fileYear'];
            }
        }
        return $this->externalUser->getCurrentYear($externalId);
    }


    /**
     * @param string|null              $externalId
     * @param Alaress_Date_Format|null $date
     * @return string|null
     */
    private function getFileSemester(?string $externalId = null, ?Alaress_Date_Format $date = null): ?string
    {
        // if date is provided and is not today, get fileSemester and year from the date
        if ($date && !$date->isToday()) {
            $dateFileSemesterYear = $this->getFileSemesterFileYear($date);
            if (isset($dateFileSemesterYear['fileSemester'])) {
                return $dateFileSemesterYear['fileSemester'];
            }
        }
        return $this->externalUser->getCurrentSemester($externalId);
    }


    /**
     * Is this date timestamp within the current file semester?
     *
     * If not, day numbers and timetables won't be shown.
     *
     * @param Alaress_Date_Format $date
     * @return array
     */
    private function getFileSemesterFileYear(Alaress_Date_Format $date): array
    {
        if (!$this->semesters) {
            $query  = '
            SELECT FileYear, FileSemester, CONVERT(DATE, StartDate) as SemesterStart, CONVERT(DATE, EndDate) as SemesterEnd
            FROM FileSemesters
            WHERE ActivatedFlag = 1
            AND EndDate >= (SELECT TOP 1 StartDate FROM FileSemesters WHERE FileYear = :year AND FileSemester = :semester ORDER BY StartDate)
        ';
            $params = [
                'year'     => $this->externalUser->getCurrentYear(),
                'semester' => $this->externalUser->getCurrentSemester(),
            ];

            $semesters = $this->database->query($query, $params)->fetchAll();

            foreach ($semesters as $semester) {
                $this->semesters[] = [
                    'fileYear'     => $semester['FileYear'],
                    'fileSemester' => $semester['FileSemester'],
                    'start'        => (new Alaress_Date_Format($semester['SemesterStart'], Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay(),
                    'end'          => (new Alaress_Date_Format($semester['SemesterEnd'], Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay(),
                ];
            }
        }

        if (is_array($this->semesters)) {
            foreach ($this->semesters as $semester) {
                if ($date->between($semester['start'], $semester['end'])) {
                    return [
                        'fileYear' => $semester['fileYear'],
                        'fileSemester' => $semester['fileSemester'],
                    ];
                }
            }
        }

        return [];
    }


    /**
     * Returns the day name
     *
     * @param string $dayNumber
     * @return string
     */
    public function getDayName($dayNumber): string
    {
        $query  = "SELECT Description FROM luDays WHERE Code = :code";
        $result = $this->getDatabase()->getRow($query, ['code' => (string)$dayNumber]);

        if (isset($result['Description']) && $result['Description'] !== '') {
            return $result['Description'];
        }

        return 'Day ' . $dayNumber;
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
        $day = $this->getDayNumber($campus, $now);

        // cannot believe the something like 'LEFT(CONVERT(VARCHAR...' is needed
        $query = "
            SELECT TOP 1 t.TimeFrom, t.TimeTo, t.PeriodNumber, t.PeriodNumberSeq
            FROM TimetableDefinition t
            WHERE t.FileSemester = :fileSemester
            AND t.FileYear = :fileYear
            AND t.TimetableGroup = :timetableGroup
            AND (t.DayNumber = :dayNumber OR t.DayNumber = '0')
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeFrom, " . self::FORMAT_ISO8601 . "), 10), t.TimeFrom) < :secondsAfterMidnight1
            AND DATEDIFF(S, LEFT(CONVERT(VARCHAR(20), t.TimeTo, " . self::FORMAT_ISO8601 . "), 10), t.TimeTo) > :secondsAfterMidnight2
            ORDER BY t.DayNumber DESC
        ";

        return $this->getDatabase()->getRow(
            $query,
            [
                'fileSemester'          => $this->externalUser->getCurrentSemester(),
                'fileYear'              => $this->externalUser->getCurrentYear(),
                'timetableGroup'        => $campusString,
                'dayNumber'             => $day,
                'secondsAfterMidnight1' => $secondsAfterMidnight,
                'secondsAfterMidnight2' => $secondsAfterMidnight,
            ]
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
     * @return array
     * @throws InvalidArgumentException
     * @throws \Alaress\Database\Exception
     */
    private function fetchTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        $dayNumber = $this->getDayNumber($campus, $date->getUnixTimeStamp());

        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $timetableDef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber, $date->getDatabaseDate());

        if (!is_array($timetableDef['desc'])) {
            return [];
        }

        $timetable = [];

        $campusString = $campus->getExtTimetableCode();

        $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $fileSemester = $this->getFileSemester($externalUserId, $dateObject);
        $fileYear     = $this->getFileYear($externalUserId, $dateObject);

        $allowedFileTypes = $this->getAllowedFileTypes();

        // If the file type is not provided, set it to 'A'
        if (empty($allowedFileTypes)) {
            $allowedFileTypes = [self::DEFAULT_FILE_TYPE];
        }

        $periodsForSequenceIds = [];

        // pvTimetableDefinitionAll
        $periodList = [];
        $statement  = $this->database->prepareProcedure('dbo.spsTimetable');
        $statement->bindInputs(
            [
                'FileType'                  => '',
                'FileYear'                  => $fileYear,
                'FileSemester'              => $fileSemester,
                'ID'                        => $externalUserId,
                'Date'                      => $date->getDatabaseDate(),
                'IncludeStaffScheduleFlag'  => 1,
                'CheckAttendancesExistFlag' => 1,
                'IncludeSparesFlag'         => 0,
                'IncludeRecessFlag'         => 0,
            ]
        );

        $iterator = $statement->getIterator();

        foreach ($iterator as $spRow) {
            // As spsTimetable does not sometimes return the specified fileType only, make sure to remove them
            if (!in_array($spRow['FileType'], $allowedFileTypes, false)) {
                continue;
            }

            if (strcasecmp($spRow['TimetableGroup'], $campusString) === 0) {
                // There is a SeqLinkedTo field, which is only set for linked classes.
                $sequenceId            = $spRow['SeqLinkedTo'] ?? null;
                // 'Always', 'Never', 'Prompt'
                $multiPeriodAttendance = $spRow['MultiPeriodAttendanceMode'] ? $spRow['MultiPeriodAttendanceMode'] === 'Always' : false;

                if (isset($sequenceId) && $multiPeriodAttendance) {
                    $periodsForSequenceIds[$sequenceId][] = $spRow['Period'];
                }

                $period = [
                    'Room'                      => $spRow['Room'],
                    'Description'               => $spRow['ClassDescription'],
                    'PeriodNumber'              => $spRow['Period'],
                    'ClassCampus'               => $spRow['ClassCampus'],
                    'ClassCode'                 => $spRow['ClassCode'],
                    'FileType'                  => $spRow['FileType'],
                    'StaffID'                   => $spRow['StaffID'],
                    'SubstituteFlag'            => $spRow['SubstituteFlag'],
                    'SubstitutedFlag'           => $spRow['SubstitutedFlag'],
                    'PeriodSequence'            => $sequenceId,
                    'AttendancePeriods'         => [$spRow['Period']],
                    'AttendanceMarked'          => $spRow['IsMarkedFlag'],
                    'AttendanceEnabled'         => $spRow['AttendanceEntryEnabledFlag'],
                    'ClassCancelled'            => $spRow['ClassCancelledFlag'],
                    'MultiPeriodAttendanceMode' => $multiPeriodAttendance,
                ];
                $period['Key'] = $this->determinePeriodUniqueIdentifier($period);
                $periodList[]  = $period;
            }
        }

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

        // Use getDayTimetable() for anything the proc didn't find
        $fallbackPeriodList = $this->getTimetablePeriods(
            $externalUserId,
            (int)$userId,
            (int)$dayNumber,
            $allowedFileTypes,
            $fileSemester,
            $fileYear,
            $campus
        );
        $uniquePeriodKeys = array_column($periodList, 'Key');
        foreach ($fallbackPeriodList as $fallbackPeriod) {
            // If PeriodNumber + ClassCampus + ClassCode + FileType match an
            // existing period, then don't add it again
            $fallbackPeriod['Key'] = $this->determinePeriodUniqueIdentifier($fallbackPeriod);
            if (!in_array($fallbackPeriod['Key'], $uniquePeriodKeys)) {
                $periodList[] = $fallbackPeriod;
            }
        }

        // Sort the periods and period sequences for niceness after adding all
        // of the periods
        usort(
            $periodList,
            static function ($a, $b) {
                return $a['PeriodNumber'] <=> $b['PeriodNumber'];
            }
        );

        // Add classes into it
        $timetable = $this->appendPeriodsToTimetable($timetableDef, $periodList, $timetable);

        usort($timetable, [$this, 'sortTimetable']);

        return $timetable;
    }


    /**
     * Determines a unique identifier for this period.
     *
     * A period is identified by its number, and the campus+code+filetype of the
     * class within it.
     *
     * @param array $period A period record, as returned by `getTimetablePeriods`
     *                      or built by `fetchTimetableForDate`.
     * @return string
     */
    private function determinePeriodUniqueIdentifier(array $period): string
    {
        return serialize([
            'period'   => (int)$period['PeriodNumber'],
            'campus'   => (string)$period['ClassCampus'],
            'code'     => (string)$period['ClassCode'],
            'fileType' => (string)$period['FileType'],
        ]);
    }


    /**
     * @param array $timetableDef
     * @param array $periodList
     * @param array $timetable
     * @return array
     */
    private function appendPeriodsToTimetable(array $timetableDef, array $periodList, array $timetable)
    {
        if (empty($timetable)) {
            // Initialize the array
            foreach ($timetableDef['desc'] as $x => $name) {
                $timetable[$x] =
                    [
                        'periodName'     => $timetableDef['desc'][$x],
                        'periodStart'    => $timetableDef['start'][$x],
                        'periodEnd'      => $timetableDef['end'][$x],
                        'periodNumber'   => $timetableDef['number'][$x],
                        'periodSequence' => $timetableDef['sequence'][$x],
                        'class'          => [],
                    ];
            }
        }
        // Add classes into it
        foreach ($periodList as $desc) {
            foreach ($timetable as $x => $timetablePeriod) {
                if (
                    $desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['class'][$desc['ClassCode']])
                ) {
                    $timetable[$x]['class'][$desc['ClassCode']] = [
                        'description'       => $desc['Description'],
                        'code'              => $desc['ClassCode'],
                        'campus'            => $desc['ClassCampus'],
                        'room'              => $desc['Room'],
                        'staffID'           => $desc['StaffID'],
                        'FileType'          => $desc['FileType'],
                        // The fields below are from spsTimetable procedure
                        'SubstituteFlag'    => $desc['SubstituteFlag'] ?? null,
                        'SubstitutedFlag'   => $desc['SubstitutedFlag'] ?? null,
                        'AttendanceMarked'  => $desc['AttendanceMarked'] ?? null,
                        'AttendanceEnabled' => isset($desc['AttendanceEnabled']) ? $desc['AttendanceEnabled'] && !$desc['ClassCancelled'] : false,
                        'AttendancePeriods' => $desc['AttendancePeriods'] ?? null,
                        'ClassCancelled'    => $desc['ClassCancelled'] ?? null,
                    ];
                    break;
                }
            }
        }
        return $timetable;
    }


    /**
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        $key = sprintf('%d-%d-%s-%d-%s', $externalUserId, $userId, $campus->getExtTimetableCode(), $dayNumber, $date);

        return self::$dayTimetableCache->get($key, func_get_args());
    }


    /**
     * @param string                   $externalUserId
     * @param int                      $userId
     * @param Campus                   $campus
     * @param int                      $dayNumber
     * @param Alaress_Date_Format|null $date
     * @return array
     */
    private function fetchDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }
        $timetableDef = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber, $date->getDatabaseDate());

        if (!is_array($timetableDef['desc'])) {
            return [];
        }

        $fileSemester = $this->getFileSemester($externalUserId, $date);
        $fileYear     = $this->getFileYear($externalUserId, $date);
        $periodList   = $this->getTimetablePeriods($externalUserId, (int)$userId, (int)$dayNumber, null, $fileSemester, $fileYear, $campus);

        $timetable = $this->appendPeriodsToTimetable($timetableDef, $periodList, []);
        if (is_array($timetable)) {
            usort($timetable, [$this, 'sortTimetable']);
        }

        return $timetable;
    }


    /**
     * @param string              $externalUserId
     * @param int                 $userId
     * @param int                 $dayNumber
     * @param array|string[]|null $fileTypes
     * @param string              $fileSemester
     * @param string              $fileYear
     * @param Campus              $campus
     * @return array
     */
    private function getTimetablePeriods(string $externalUserId, int $userId, int $dayNumber, ?array $fileTypes, string $fileSemester, string $fileYear, Campus $campus): array
    {
        $externalUserId = $this->database->legacyQuote($externalUserId);
        $fileSemester   = $this->database->legacyQuote($fileSemester);
        $fileYear       = $this->database->legacyQuote($fileYear);
        $dayNumber      = $this->database->legacyQuote($dayNumber);
        $campusString   = $campus->getExtTimetableCode();

        // Get file types to limit the data to
        $allowedFileTypes = $this->getAllowedFileTypes();
        if (isset($fileTypes)) {
            $allowedFileTypes = array_intersect($allowedFileTypes, $fileTypes);
        }

        $quotedFileTypes    = empty($allowedFileTypes)
            ? ''
            : implode(',', array_map([$this->database, 'legacyQuote',], $allowedFileTypes));
        $allowedFileTypeSql = empty($quotedFileTypes) ? '' : " Timetable.FileType IN ({$quotedFileTypes}) AND ";

        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT
                Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCampus, Timetable.ClassCode,Timetable.FileType, StaffID
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
                Timetable.FileSemester={$fileSemester} AND
                Timetable.FileYear={$fileYear} AND
                (StudentClasses.ID={$externalUserId} AND
                Timetable.DayNumber = {$dayNumber} AND
                Timetable.TimetableGroup = '$campusString' AND
                {$allowedFileTypeSql}
                StudentClasses.StartDate <= CAST(GETDATE() AS DATE) AND
                (StudentClasses.StopDate >= CAST(GETDATE() AS DATE) OR StudentClasses.StopDate IS NULL))
                ORDER BY Timetable.PeriodNumber";
        } else {
            $query = "SELECT Timetable.Room, SubjectClasses.Description,
                Timetable.PeriodNumber, Timetable.ClassCampus, Timetable.ClassCode,Timetable.FileType,
                Timetable.StaffID,
                Timetable.PeriodNumber as AttendancePeriod
                FROM dbo.SubjectClasses SubjectClasses,
                dbo.Timetable Timetable
                WHERE Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                Timetable.ClassCode = SubjectClasses.ClassCode AND
                Timetable.FileSemester = SubjectClasses.FileSemester AND
                Timetable.FileType = SubjectClasses.FileType AND
                Timetable.FileYear = SubjectClasses.FileYear AND
                SubjectClasses.FileSemester={$fileSemester} AND
                SubjectClasses.FileYear={$fileYear} AND
                Timetable.TimetableGroup = '$campusString' AND
                {$allowedFileTypeSql}
                Timetable.StaffID={$externalUserId} AND
                Timetable.DayNumber = {$dayNumber}
                ORDER BY Timetable.PeriodNumber";
        }

        $statement = $this->getDatabase()->query($query);

        return $statement->fetchAll();
    }


    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $timetabledef = $this->getTimetableDefinition($externalUserId, $campus, $this->getDayNumber($campus));

        if (!is_array($timetabledef['desc'])) {
            return [];
        }

        $campusString   = $this->database->legacyQuote($campus->getExtTimetableCode());
        $fileSemester   = $this->database->legacyQuote($this->externalUser->getCurrentSemester($externalUserId));
        $fileYear       = $this->database->legacyQuote($this->externalUser->getCurrentYear($externalUserId));
        $externalUserId = $this->database->legacyQuote($externalUserId);

        $allowedFileTypes    = $this->getAllowedFileTypes();
        $quotedFileTypes     = empty($allowedFileTypes)
            ? ''
            : implode(',', array_map([$this->getDatabase(), 'legacyQuote',], $allowedFileTypes));
        $allowedFileTypesSql = empty($quotedFileTypes) ? '' : " Timetable.FileType IN ({$quotedFileTypes}) AND ";

        if ($this->groupHandler->isStudent($userId)) {
            $query = "SELECT
                        Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
                        Timetable.ClassCampus, Timetable.ClassCode, Timetable.StaffID, vStaff.StaffTitle, vStaff.StaffPreferredName,
                        vStaff.StaffSurname, Timetable.TimetableSeq AS sequence
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
                        ((Timetable.FileSemester = {$fileSemester}) AND
                        (Timetable.FileYear = {$fileYear}) AND
                        Timetable.TimetableGroup = {$campusString} AND
                        {$allowedFileTypesSql}
                        (StudentClasses.ID = {$externalUserId}))
                        AND StudentClasses.StartDate <= CAST(GETDATE() AS DATE) AND (StudentClasses.StopDate >= CAST(GETDATE() AS DATE) OR StudentClasses.StopDate IS NULL)
                        AND DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . '
                    ORDER BY Timetable.PeriodNumber, Timetable.DayNumber';
        } else {
            // if user has no timetable, $timetabledef['dayCount'] is NULL, not 0, which breaks SQL query
            if ($timetabledef['dayCount'] == null) {
                $timetabledef['dayCount'] = 0;
            }

            $query = "SELECT
                        Timetable.Room, SubjectClasses.Description, Timetable.DayNumber, Timetable.PeriodNumber,
                        Timetable.ClassCampus, Timetable.ClassCode, Timetable.TimetableSeq AS sequence
                    FROM
                        dbo.SubjectClasses SubjectClasses,
                        dbo.Timetable Timetable
                    WHERE
                        Timetable.ClassCampus = SubjectClasses.ClassCampus AND
                        Timetable.ClassCode = SubjectClasses.ClassCode AND
                        Timetable.FileSemester = SubjectClasses.FileSemester AND
                        Timetable.FileType = SubjectClasses.FileType AND
                        Timetable.FileYear = SubjectClasses.FileYear AND
                        SubjectClasses.FileSemester = {$fileSemester} AND
                        SubjectClasses.FileYear = {$fileYear} AND
                        Timetable.TimetableGroup = {$campusString} AND
                        Timetable.StaffID = {$externalUserId} AND
                        {$allowedFileTypesSql}
                        DayNumber > 0 AND DayNumber <= " . $timetabledef['dayCount'] . '
                    ORDER BY Timetable.PeriodNumber, Timetable.DayNumber';
        }

        $dayCount = $timetabledef['dayCount'];

        $statement = $this->getDatabase()->query($query);

        // Build the timetable
        $timetable = [];

        foreach ($timetabledef['desc'] as $x => $name) {
            $timetable[$x]['periodName']     = $timetabledef['desc'][$x];
            $timetable[$x]['periodStart']    = $timetabledef['start'][$x];
            $timetable[$x]['periodEnd']      = $timetabledef['end'][$x];
            $timetable[$x]['periodNumber']   = $timetabledef['number'][$x];
            $timetable[$x]['periodSequence'] = $timetabledef['sequence'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day'] = array_fill(1, $dayCount, []);
        }

        // Add classes for each day into it

        while ($desc = $statement->fetch()) {
            foreach ($timetable as $x => $timetablePeriod) {
                if (
                    $desc['PeriodNumber'] == $timetablePeriod['periodNumber']
                    && $timetablePeriod['periodSequence'] != 2
                    && !isset($timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']])
                ) {
                    $timetable[$x]['day'][$desc['DayNumber']][$desc['ClassCode']] = [
                        'description'    => $desc['Description'],
                        'code'           => $desc['ClassCode'],
                        'campus'         => $desc['ClassCampus'],
                        'room'           => $desc['Room'],
                        'staffID'        => $desc['StaffID'] ?? null,
                        'staffFirstname' => $desc['StaffPreferredName'] ?? null,
                        'staffSurname'   => $desc['StaffSurname'] ?? null,
                        'staffTitle'     => $desc['StaffTitle'] ?? null,
                        'sequence'       => (int)$desc['sequence']
                    ];

                    break;
                }
            }
        }

        if (is_array($timetable)) {
            usort($timetable, [$this, 'sortTimetable']);

            /* Sort each of the classes occurring in each (day, period) tuple
             * Sorted uniquely in synergetic by the sequence field
             */
            foreach ($timetable as &$periodData) {
                foreach ($periodData['day'] as $day => &$dayData) {
                    /* Sort the concurrent classes by their sequence field
                     * Assume ascending order is the way it should display
                     */
                    uasort($dayData, static function (array $a, array $b) {
                        return $a['sequence'] < $b['sequence'] ? -1 : 1;
                    });
                }
            }
        }
        return $timetable;
    }


    /**
     * Sorts timetable by period start
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortTimetable(array $a, array $b): int
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
    public function getTotalDays(Campus $campus)
    {
        if (!isset($this->days[$campus->getExtTimetableCode()])) {
            $query  = "SELECT CycleDays FROM luTimetableGroup WHERE Code = '{$campus->getExtTimetableCode()}'";
            $result = $this->getDatabase()->getRow($query);

            $this->days[$campus->getExtTimetableCode()] = !$result ? 0 : $result['CycleDays'];
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

        $query = '
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
        ';
        $query = strtr(
            $query,
            [
                ':semester' => $this->database->legacyQuote($this->externalUser->getCurrentSemester()),
                ':year'     => $this->database->legacyQuote($this->externalUser->getCurrentYear()),
                ':rooms'    => implode(',', array_map([$this->database, 'legacyQuote'], $filteredRoomCodes)),
            ]
        );

        // Jam the timetable definition in.
        $campusList          = [];
        $campusTimetableList = [];
        $result              = [];
        foreach ($this->database->getAll($query) ?: [] as $row) {
            $timetableDay  = $row['DayNumber'];
            $timetableCode = $row['TimetableGroup'];

            // Load the campus for this code if I haven't tried already
            if (!isset($campusList[$timetableCode])) {
                try {
                    $campusList[$timetableCode] = CampusMapper::getInstance()->findOneByColumn(
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
     * @return array
     */
    private function getAllowedFileTypes()
    {
        if (!$this->externalUser->getDatabase() instanceof External_Synergetic_User) {
            return [];
        }

        return $this->externalUser->getDatabase()->getAllowedFileTypes();
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return [];
    }


    /**
     * @var IConnector $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return IConnector
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
