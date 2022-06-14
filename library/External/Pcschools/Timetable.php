<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Database\Factory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Class External_Pcschools_Timetable
 */
class External_Pcschools_Timetable implements External_TimetableInterface
{

    /**
     * Name of stored proc to retrieve a timetable
     *
     * @var string
     */
    protected $timetableStoredProc = 'spSelTimeTable';


    /**
     * @var IConnector
     */
    private $database;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
        $this->userMapper = UserMapper::getInstance();
    }


    /**
     * The headings of the timetable, going horizontally from left to right containing all the times and day numbers
     *
     * Has the structure for each period
     *
     * $timetableDef['desc'][]
     * $timetableDef['start'][]
     * $timetableDef['end'][]
     * $timetableDef['number'][]
     * $timetableDef['sequence'][]
     *
     * Padua College uses a different timetable for most days of the week- to avoid dealing with this we are taking
     * the Day 1 timetable only and clobbering the rest of the week with the same timetable. I am however selecting the
     * period number in the format DD.PP (day number.period number- e.g "01.05")
     *
     * @param string|null $externalUserId If set, the external user ID to get definition for.
     * @param Campus $campus
     * @param int    $day
     *
     * @return array
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0): array
    {
        // TODO: Hardcoded until we handle differing times each day of the timetable

        $day = 1;

        $sql = "
            SELECT DISTINCT   CONCAT(DAY, '.', TIMEOFDAY) AS NUMBER,
                              HEADING,
                              TIME_START,
                              TIME_END
            FROM              TIMEPDHD
            WHERE             TYPE <> 'Y' AND DAY = {$day}
            ORDER BY          TIME_START ASC
        ";

        $timetableDays = $this->getDatabase()->getAll($sql);

        $result = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'dayCount'    => $this->getDayCount(),
            'periodCount' => $this->getPeriodCount(),
        ];

        foreach ($timetableDays as $timetableDay) {
            $result['desc'][]     = $timetableDay['HEADING'];
            $result['start'][]    = date("g:ia", strtotime($timetableDay['TIME_START']));
            $result['end'][]      = date("g:ia", strtotime($timetableDay['TIME_END']));
            $result['number'][]   = $timetableDay['NUMBER'];
            $result['sequence'][] = 1;
        }

        return $result;
    }


    /**
     * Gets the number of days in this timetable.
     *
     * @return int
     */
    protected function getDayCount()
    {
        return $this->getDatabase()
                    ->getOne('SELECT MAX(DAY) FROM TIMEFILE WHERE TIME_PERIOD = (SELECT TIME_PERIOD FROM sysfile)');
    }


    /**
     * Gets the maximum number of periods for a day from the database. This is again a kluge and it is not a one-size
     * fits all database, and allows for different number of periods each day. As such this just grabs the maximum
     * possible.
     *
     * @return array|bool
     */
    protected function getPeriodCount()
    {
        return $this->getDatabase()
                    ->getOne('SELECT MAX(SUBQUERY.PERIODS) FROM (SELECT COUNT(*) AS PERIODS FROM TIMEPDHD GROUP BY TIMEPDHD.DAY) AS SUBQUERY');
    }


    /**
     * Get the current day number for a given campus
     *
     * @param Campus $campus
     * @param int    $date
     *
     * @internal param $campusId
     * @return int
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        if ($date) {
            $day = date('Y-m-d', $date);
        } else {
            $day = date('Y-m-d');
        }

        $result = $this->getDatabase()
                       ->getOne("SELECT TIMETABLE_DAY FROM CALENDAR WHERE [DATE] = {$this->getDatabase()->legacyQuote($day)}");

        return intval($result) ?: 0;
    }


    /**
     * Returns the name of the current day
     *
     * @param int $dayNumber
     *
     * @return String
     */
    public function getDayName($dayNumber): string
    {
        return 'Day ' . $dayNumber;
    }


    /**
     * @inheritDoc
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
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
     * {@inheritdoc}
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        if (!(new External_Pcschools_ValidCampusSpec())->isSatisfiedBy($campus)) {
            return [];
        }

        try {
            $user = $this->userMapper->findById($userId);
        } catch (MapperException $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure($this->timetableStoredProc);
        $statement->bindInputs([
            'DATE'        => date('Ymd'),
            'TIMEPERIOD'  => $timePeriod,
            'STUDKEY'     => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();

        $statement->close();

        // The $homeClasses definition is mostly included within dayPeriods

        $timetable = [];

        foreach ($dayPeriods as $period) {
            // Y periods seem to contain day name definition, skipping these
            // D contains duties (eg. Yard duty)
            // T contains timetabled classes
            // N contains random stuff (Dismissal)
            //
            // TODO: Possibly make a config variable of types to include

            if ($period["TYPE"] != "Y") {
                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $periodNumber = '01.' . $period['CODE'];

                $timetable[$periodNumber]['periodName']     = $period['HEADING'];
                $timetable[$periodNumber]['periodStart']    = $period['TIME_START'];
                $timetable[$periodNumber]['periodEnd']      = $period['TIME_END'];
                $timetable[$periodNumber]['periodSort']     = str_pad(sprintf('%.2f', $period['TIME_START']), 5, '0', STR_PAD_LEFT);
                $timetable[$periodNumber]['periodNumber']   = $periodNumber;
                $timetable[$periodNumber]['periodSequence'] = 1;
                $timetable[$periodNumber]['class']          = [];
            }
        }

        ksort($timetable);

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            if ($dayClass['SUPERDATE'] == null || $dayClass['SUPERDATE'] == date("Y-m-d") . " 00:00:00") {
                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $period = '01.' . $dayClass['PERIOD'];

                $classCode = $timePeriod . '/' . $dayClass['YEAR'] . $dayClass['ABBREV'] . $dayClass['CLASS'];

                if ($dayNumber == $dayClass['DAY']) {
                    $timetable[$period]['class'][$classCode] = [
                        'description'        => $dayClass['DESC'],
                        'code'               => $classCode,
                        'campus'             => $timePeriod,
                        'room'               => $dayClass['ROOM'],
                        'staffID'            => $dayClass['TEACHER_CODE'],
                    ];
                }
            }
        }

        usort($timetable, function ($a, $b) {
            return strcmp($a['periodSort'], $b['periodSort']);
        });

        return $timetable;
    }


    /**
     * @inheritDoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        if (!(new External_Pcschools_ValidCampusSpec())->isSatisfiedBy($campus)) {
            return [];
        }

        try {
            $user = $this->userMapper->findById($userId);
        } catch (MapperException $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();

        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure($this->timetableStoredProc);
        $statement->bindInputs([
            'DATE'        => $date->getUsingFormat('Ymd'),
            'TIMEPERIOD'  => $timePeriod,
            'STUDKEY'     => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();
        // $homeClasses = $statement->fetchAll();
        $statement->nextRowset();
        $weekDays = $statement->fetchAll();
        $statement->nextRowset();
        // $terms = $statement->fetchAll();
        $statement->close();

        // The $homeClasses definition is mostly included within dayPeriods

        $timetable = [];

        foreach ($dayPeriods as $period) {
            // TODO: Possibly make a config variable of types to include
            if ($period["TYPE"] != "Y") {
                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $periodNumber = '01.' . $period['CODE'];

                $timetable[$periodNumber]['periodName']     = $period['HEADING'];
                $timetable[$periodNumber]['periodStart']    = sprintf('%.2f', $period['TIME_START']);
                $timetable[$periodNumber]['periodEnd']      = sprintf('%.2f', $period['TIME_END']);
                $timetable[$periodNumber]['periodSort']     = str_pad(sprintf('%.2f', $period['TIME_START']), 5, '0', STR_PAD_LEFT);
                $timetable[$periodNumber]['periodNumber']   = $periodNumber;
                $timetable[$periodNumber]['periodSequence'] = 1;
                $timetable[$periodNumber]['class']          = [];
            }
        }

        ksort($timetable);

        // What's the current day number?

        $dayNumber = 0;

        foreach ($weekDays as $weekDay) {
            try {
                // 2020-08-10 00:00:00.000
                $adf = new Alaress_Date_Format($weekDay['DATE'], Alaress_Date_Format::DATABASE_FORMAT_MLS);
            } catch (Exception $e) {
                continue;
            }
            if ($adf->isSameDay($date)) {
                $dayNumber = $weekDay['TIMETABLE_DAY'];
                break;
            }
        }

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            if ($dayClass['SUPERDATE'] == null || substr($dayClass['SUPERDATE'], 0, 10) === date("Y-m-d")) {
                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $period = '01.' . $dayClass['PERIOD'];

                $classCode = $timePeriod . '/' . $dayClass['YEAR'] . $dayClass['ABBREV'] . $dayClass['CLASS'];

                if ($dayNumber == $dayClass['DAY']) {
                    $timetable[$period]['class'][$classCode] = [
                        'description'        => "{$period} {$dayClass['DESC']}",
                        'code'               => $classCode,
                        'campus'             => $timePeriod,
                        'room'               => $dayClass['ROOM'],
                        'staffID'            => $dayClass['TEACHER_CODE'],
                    ];
                }
            }
        }

        usort($timetable, function ($a, $b) {
            return strcmp($a['periodSort'], $b['periodSort']);
        });

        return $timetable;
    }


    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (!(new External_Pcschools_ValidCampusSpec())->isSatisfiedBy($campus)) {
            return [];
        }

        // I need to know if $userId is a teacher or student
        try {
            $user = $this->userMapper->findById($userId);
        } catch (MapperException $e) {
            return [];
        }

        $studentExternalId = '';
        $teacherExternalId = '';
        $timePeriod        = $campus->getExtTimetableCode();
        if ($user->getRole()->isStudent()) {
            $studentExternalId = $user->getExternalId();
        } elseif ($user->getRole()->isStaff()) {
            $teacherExternalId = $user->getExternalId();
        }

        $statement = $this->getDatabase()->prepareProcedure($this->timetableStoredProc);
        $statement->bindInputs([
            'DATE'        => date('Ymd'),
            'TIMEPERIOD'  => $timePeriod,
            'STUDKEY'     => $studentExternalId,
            'TEACHERCODE' => $teacherExternalId,
        ]);

        $statement->execute();

        $dayPeriods = $statement->fetchAll();
        $statement->nextRowset();
        $dayClasses = $statement->fetchAll();
        $statement->nextRowset();
        // $homeClasses = $statement->fetchAll();
        $statement->nextRowset();
        // $weekDays = $statement->fetchAll();
        $statement->nextRowset();
        $terms = $statement->fetchAll();
        $statement->close();

        $term = array_shift($terms);

        // The $homeClasses definition is mostly included within dayPeriods

        $timetable = [];
        $dayCount  = $term['TIMEDAYS'];

        foreach ($dayPeriods as $period) {
            // TODO: Possibly make a config variable of types to include
            if ($period["TYPE"] != "Y") {
                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $periodNumber = '01.' . $period['CODE'];

                $timetable[$periodNumber]['periodName']     = $period['HEADING'];
                $timetable[$periodNumber]['periodStart']    = sprintf('%.2f', $period['TIME_START']);
                $timetable[$periodNumber]['periodEnd']      = sprintf('%.2f', $period['TIME_END']);
                $timetable[$periodNumber]['periodSort']     = str_pad(sprintf('%.2f', $period['TIME_START']), 5, '0', STR_PAD_LEFT);
                $timetable[$periodNumber]['periodNumber']   = $periodNumber;
                $timetable[$periodNumber]['periodSequence'] = 1;
                $timetable[$periodNumber]['day']            = array_fill(1, $dayCount, []);
            }
        }

        // Add classes for each day into it
        foreach ($dayClasses as $dayClass) {
            if ($dayClass['SUPERDATE'] == null || $dayClass['SUPERDATE'] == date("Y-m-d") . " 00:00:00") {
                $day = $dayClass['DAY'];

                // TODO: Remove hardcoded day one once Schoolbox supports different day/times
                $period = '01.' . $dayClass['PERIOD'];

                $classCode = $timePeriod . '/' . $dayClass['YEAR'] . $dayClass['ABBREV'] . $dayClass['CLASS'];

                $timetable[$period]['day'][$day][$classCode] = [
                    'description'        => $dayClass['DESC'],
                    'code'               => $classCode,
                    'campus'             => $timePeriod,
                    'room'               => $dayClass['ROOM'],
                    'staffID'            => $dayClass['TEACHER_CODE'],
                ];
            }
        }

        usort($timetable, function ($a, $b) {
            return strcmp($a['periodSort'], $b['periodSort']);
        });

        return $timetable;
    }


    /**
     * @inheritDoc
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        // Split the requested room codes into a CSV string for use in the query
        array_walk($roomCodes, function (&$value) {
            $value = $this->getDatabase()->legacyQuote($value);
        });

        $roomCodesCsv = implode(",", $roomCodes);

        // We don't have any information at this point in time as to which user, date, or other context we are in- so
        // we sadly just end up blindly picking any campus to get a value to use for TIME_PERIOD in our queries
        $timePeriod = '';

        $campuses = CampusMapper::getInstance()->findAll();

        $spec     = new External_Pcschools_ValidCampusSpec();
        foreach ($campuses as $campus) {
            if ($spec->isSatisfiedBy($campus)) {
                $timePeriod = $campus->getExtTimetableCode();
                break;
            }
        }

        if ($timePeriod === '') {
            return [];
        }

        $quotedTimePeriod = $this->getDatabase()->legacyQuote($timePeriod);

        // Run this mess

        $sql = "
            SELECT
              TIMEFILE.DAY            AS TIMETABLE_DAY,
              TIMEFILE.TIME_PERIOD    AS TIMETABLE_GROUP,
              TIMEFILE.PERIOD         AS TIMETABLE_PERIOD,
              TIMEPDHD.TIME_START     AS PERIOD_START,
              TIMEPDHD.TIME_END       AS PERIOD_END,
              TIMEPDHD.HEADING        AS PERIOD_NAME,
              TIMEFILE.ROOM           AS ROOM_CODE,
              TIMEROOM.[DESC]         AS ROOM_NAME,
              TIMEFILE.YEAR           AS CLASS_YEAR_LEVEL,
              TEACHER.TEACHER_CODE    AS TEACHER_CODE,
              TEACHER.NAME_FOR_REPORT AS TEACHER_NAME,
              TTABLE.SUB_CODE         AS SUBJECT_CODE,
              SUBJECT.[DESC]          AS SUBJECT_NAME
            FROM TIMEFILE
              LEFT JOIN TTABLE ON TTABLE.PCS_SUB_NO = TIMEFILE.SUBJECT AND TTABLE.CLASS_PCS = TIMEFILE.CLASS AND TTABLE.YEAR_PCS = TIMEFILE.YEAR
              LEFT JOIN SUBJECT ON SUBJECT.NO = TTABLE.PCS_SUB_NO
              LEFT JOIN TEACHER ON TEACHER.TEACHER_CODE = TIMEFILE.TEACHER
              LEFT JOIN TIMEROOM ON TIMEROOM.ABBREV = TIMEFILE.ROOM
              LEFT JOIN TIMEPDHD ON TIMEPDHD.DAY = TIMEFILE.DAY AND TIMEPDHD.TIMEOFDAY = CAST(TIMEFILE.PERIOD AS VARCHAR(5)) AND
              CASE
                WHEN TIMEPDHD.RESTRICT_YR = '*C' THEN 1
                WHEN LEFT(TIMEPDHD.RESTRICT_YR, 1) <> '*' AND TIMEFILE.YEAR = TIMEPDHD.RESTRICT_YR THEN 1
                WHEN LEFT(TIMEPDHD.RESTRICT_YR, 1) = '*' AND TIMEPDHD.INCLUDES LIKE '%|' + TIMEFILE.YEAR + '|%' THEN 1
                ELSE 0
              END = 1
            WHERE TIME_PERIOD = {$quotedTimePeriod} AND TIMEFILE.ROOM IN ({$roomCodesCsv})
        ";

        $classes = $this->getDatabase()->getAll($sql);

        $result = [];

        foreach ($classes as $class) {
            // If we don't have a start/end time- skip it.

            if (trim($class['PERIOD_START']) === '' || trim($class['PERIOD_END']) === '') {
                continue;
            }

            $periodStartArray = explode('.', $class['PERIOD_START']);
            $periodStartAMPM  = $periodStartArray[0] >= 12 ? 'PM' : 'AM';

            if ($periodStartArray[0] > 12) {
                $periodStartArray[0] -= 12;
            }

            if ($periodStartArray[0] < 10) {
                $periodStartArray[0] = "0{$periodStartArray[0]}";
            }

            if ($periodStartArray[1] === null) {
                $periodStartArray[1] = '00';
            } elseif ($periodStartArray[1] < 10 && strlen($periodStartArray[1]) < 2) {
                $periodStartArray[1] = "{$periodStartArray[1]}0";
            }

            $periodStart = "{$periodStartArray[0]}:{$periodStartArray[1]}{$periodStartAMPM}";

            $periodEndArray = explode('.', $class['PERIOD_END']);
            $periodEndAMPM  = $periodEndArray[0] >= 12 ? 'PM' : 'AM';

            if ($periodEndArray[0] > 12) {
                $periodEndArray[0] -= 12;
            }

            if ($periodEndArray[0] < 10) {
                $periodEndArray[0] = "0{$periodEndArray[0]}";
            }

            if ($periodEndArray[1] === null) {
                $periodEndArray[1] = '00';
            } elseif ($periodEndArray[1] < 10 && strlen($periodEndArray[1]) < 2) {
                $periodEndArray[1] = "{$periodEndArray[1]}0";
            }

            $periodEnd = "{$periodEndArray[0]}:{$periodEndArray[1]}{$periodEndAMPM}";

            $result[] = [
                'DayNumber'      => $class['TIMETABLE_DAY'],
                'TimetableGroup' => $timePeriod,
                'PeriodStart'    => $periodStart,
                'PeriodEnd'      => $periodEnd,
                'PeriodName'     => $class['PERIOD_NAME'],
                'Description'    => "{$class['SUBJECT_NAME']}\n{$class["TEACHER_NAME"]}",
                'StaffID'        => $class['TEACHER_CODE'],
                'ClassCode'      => $class['SUBJECT_CODE'],
                'RoomCode'       => $class['ROOM_CODE'],
            ];
        }

        return $result;
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        return [];
    }


    /**
     * @return IConnector
     */
    private function getDatabase()
    {
        return $this->database;
    }


    /**
     * @param  IConnector $database
     *
     * @return $this
     */
    private function setDatabase($database)
    {
        $this->database = $database;

        return $this;
    }
}
