<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Predicate\Builder;
use Schoolbox\Campus;

/**
 * Class External_Edumate_Timetable
 *
 * Implementation of Edumate Timetable connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 */
class External_Edumate_Timetable extends External_Edumate_Connection implements External_TimetableInterface
{

    /**
     * @var Group $groupHandler
     */
    private $groupHandler;


    /**
     * External_Edumate_Timetable constructor.
     */
    public function __construct()
    {
        $this->groupHandler = new Group();
    }


    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        $periodCount = 0;

        $definition = [
            'desc'     => [],
            'start'    => [],
            'end'      => [],
            'number'   => [],
            'sequence' => [],
        ];

        $stmt = $this->getConnection()->prepareQuery(
            '
            SELECT PERIOD "desc", START_TIME "start", END_TIME "end", PERIOD_NUMBER "number", 1 "sequence"
            FROM SCHOOLBOX_TIMETABLE_DEFINITION TD
            WHERE TD.DAY_NUMBER = :day
            AND TD.CAMPUS = :campus
            ORDER BY TD.START_TIME ASC
        '
        );

        $campusCode = explode(',', $campus->getExtTimetableCode());

        $stmt->execute(
            [
                'day'    => $day ?: 1, // there is no day 0 in this definition
                'campus' => $campusCode[0],
            ]
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $definition['desc'][]     = $r['desc'];
            $definition['start'][]    = $this->parseTime($r['start']);
            $definition['end'][]      = $this->parseTime($r['end']);
            $definition['number'][]   = $r['number'];
            $definition['sequence'][] = $r['sequence'];
            ++$periodCount;
        }

        $definition['periodCount'] = $periodCount;
        $definition['dayCount']    = $this->getCycleLength($campus);

        return $definition;
    }


    /**
     * @param string $time
     * @return string
     * @throws Exception
     */
    protected function parseTime($time)
    {
        $timeObject = new DateTime($time, new DateTimeZone('UTC'));
        return $timeObject->format('g:ia');
    }


    /**
     * Helper function to retrieve the length of the timetable cycle in edumate
     * @param Campus $campus
     * @return int
     * @throws \Alaress\Database\Exception
     */
    protected function getCycleLength(Campus $campus)
    {
        $stmt = $this->getConnection()->prepareQuery('
            SELECT DAYS_IN_CYCLE
            FROM SCHOOLBOX_TIMETABLE_DAYS
            WHERE TIMETABLE_ID = :campus
            AND START_DATE <= :date
            AND END_DATE >= :date
            FETCH FIRST 1 ROW ONLY
        ');

        $campusCode = explode(',', $campus->getExtTimetableCode());
        $params = array(
            'date' => date('Y-m-d'),
            'campus' => $campusCode[0],
        );

        $stmt->execute($params);

        return $stmt->fetchColumn();
    }


    /**
     * @param Campus $campus
     * @param int               $date
     * @return bool|int
     * @throws \Alaress\Database\Exception
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        $stmt = $this->getConnection()->prepareQuery('
            SELECT START_DATE, CYCLE_START_DAY, DAYS_IN_CYCLE
            FROM SCHOOLBOX_TIMETABLE_DAYS
            WHERE START_DATE <= :date
            AND END_DATE >= :date
            AND TIMETABLE_ID = :campus
            ORDER BY START_DATE
            FETCH FIRST 1 ROW ONLY
        ');

        if ($date == 0) {
            $date = time();
        }

        // Return 0 if weekend
        if (date('N', $date) >= 6) {
            return 0;
        }

        // Increase timestamp to get business day properly
        $date++;

        $campusCode = explode(',', $campus->getExtTimetableCode());
        $params = array(
            'date' => date('Y-m-d', $date),
            'campus' => $campusCode[0],
        );

        $stmt->execute($params);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $start = strtotime($row['START_DATE']);

            $days = ceil(($date - $start) / (60 * 60 * 24));

            /* Calculate the business days from the even weeks */
            $businessDays = 5 * floor($days / 7);

            /* Handle the excess days */
            $excessDays = $days % 7;

            for ($i = 0; $i < $excessDays; $i++) {
                $currentExcessDay = $date - (60 * 60 * 24 * $i);

                /* If its not Saturday or Sunday then increment the business days */
                if (date('N', $currentExcessDay) < 6) {
                    $businessDays++;
                }
            }

            if (is_numeric($row['CYCLE_START_DAY'])) {
                $businessDays += ($row['CYCLE_START_DAY'] - 1);
            }

            $currentDay = $businessDays % $row['DAYS_IN_CYCLE'];

            if ($currentDay == 0) {
                return $row['DAYS_IN_CYCLE'];
            }

            return $currentDay;
        }

        return false;
    }


    /**
     * @inheritDoc
     */
    public function getDayName($dayNumber): string
    {
        return 'Day ' . $dayNumber;
    }


    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        $now = date('g:ia', $now ?: time());

        $defn = $this->getTimetableDefinition(null, $campus);
        $sequence = 0;

        for ($i = 0; $i < count($defn['sequence']); ++$i) {
            // Time definitions are now in format "g:ia"
            $defTime = new DateTime($defn['end'][$i], new DateTimeZone('UTC'));
            if ($now < $defTime->format('Hi')) {
                $sequence = $defn['sequence'][$i];
                break;
            }
        }
        $period['PeriodNumber'] = $sequence;
        return $period;
    }


    /**
     * @inheritDoc
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
    {
        if (empty($externalUserId)) {
            return array();
        }
        if (!($definition = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber))) {
            return [];
        }

        if ($this->groupHandler->isStudent($userId)) {
            $type = 'STUDENT';
            $join = '
                LEFT JOIN SCHOOLBOX_STUDENTCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STUDENT_NUMBER = :sn
            ';
        } elseif ($this->groupHandler->isStaff($userId)) {
            $type = 'STAFF';
            $join = '
                INNER JOIN SCHOOLBOX_TEACHERCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                AND T.STAFF_NUMBER = C.STAFF_NUMBER
                WHERE C.STAFF_NUMBER = :sn
            ';
        } else {
            return [];
        }

        $params = [
            'sn' => $externalUserId,
            'd'  => $dayNumber,
        ];

        $campusCodes    = explode(',', $campus->getExtTimetableCode());
        $campusBindings = Builder::getBindings($campusCodes, 'c');

        $stmt = $this->getConnection()->prepareQuery(
            "
            SELECT PERIOD_NUMBER, T.CLASS_CODE AS CODE, C.{$type}_NUMBER AS STAFFID, ROOM, CL.CLASS, CAMPUS
            FROM SCHOOLBOX_TIMETABLE T
            LEFT JOIN SCHOOLBOX_CLASSES CL ON T.CLASS_CODE = CL.CLASS_CODE
            $join
            AND DAY_NUMBER = :d
            AND CAMPUS IN ({$campusBindings->getPlaceholder()})
        "
        );

        $stmt->execute(array_merge($params, $campusBindings->getParams()));

        // Build the timetable
        $timetable = array();
        foreach ($definition['desc'] as $x => $name) {
            $timetable[$x]['periodName'] = $definition['desc'][$x];
            $timetable[$x]['periodStart'] = $definition['start'][$x];
            $timetable[$x]['periodEnd'] = $definition['end'][$x];
            $timetable[$x]['periodNumber'] = $definition['number'][$x];
            $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
            $timetable[$x]['class'] = array();
        }

        // Add classes for each day into it
        while ($desc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if (
                    $desc['PERIOD_NUMBER'] == $timetablePeriod['periodNumber']
                    && !isset($timetable[$x]['class'][$desc['CODE']])
                ) {
                    $timetable[$x]['class'][$desc['CODE']] = [
                        'description' => $desc['CLASS'],
                        'code'        => $desc['CODE'],
                        'campus'      => $desc['CAMPUS'],
                        'room'        => $desc['ROOM'],
                        'staffID'     => $desc['STAFFID'],
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
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
    {
        return $this->getDayTimetable(
            $externalUserId,
            $userId,
            $campus,
            $this->getDayNumber($campus, $date->getUnixTimeStamp())
        );
    }


    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (empty($externalUserId)) {
            return array();
        }
        $definition = $this->getTimetableDefinition($externalUserId, $campus);
        $dayCount = is_numeric($definition['dayCount']) ? $definition['dayCount'] : 0;

        if (!$dayCount) {
            return [];
        }

        if ($this->groupHandler->isStudent($userId)) {
            $join = '
                LEFT JOIN SCHOOLBOX_STUDENTCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STUDENT_NUMBER = :sn
            ';
        } elseif ($this->groupHandler->isStaff($userId)) {
            $join = '
                INNER JOIN SCHOOLBOX_TEACHERCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                AND T.STAFF_NUMBER = C.STAFF_NUMBER
                WHERE C.STAFF_NUMBER = :sn
            ';
        } else {
            return [];
        }

        $params = [
            'sn' => $externalUserId,
            'd'  => $this->getCycleLength($campus),
        ];

        $campusCodes    = explode(',', $campus->getExtTimetableCode());
        $campusBindings = Builder::getBindings($campusCodes, 'c');

        $stmt = $this->getConnection()->prepareQuery(
            "
            SELECT PERIOD_NUMBER, T.DAY_NUMBER, T.CLASS_CODE AS CODE, T.STAFF_NUMBER AS STAFFID, ROOM, CL.CLASS, CAMPUS
            FROM SCHOOLBOX_TIMETABLE T
            LEFT JOIN SCHOOLBOX_CLASSES CL ON T.CLASS_CODE = CL.CLASS_CODE
            $join
            AND CAMPUS IN ({$campusBindings->getPlaceholder()})
            AND DAY_NUMBER <= :d
        "
        );

        $stmt->execute(array_merge($params, $campusBindings->getParams()));

        // Build the timetable
        $timetable = array();
        foreach ($definition['desc'] as $x => $name) {
            $timetable[$x]['periodName'] = $definition['desc'][$x];
            $timetable[$x]['periodStart'] = $definition['start'][$x];
            $timetable[$x]['periodEnd'] = $definition['end'][$x];
            $timetable[$x]['periodNumber'] = $definition['number'][$x];
            $timetable[$x]['periodSequence'] = $definition['sequence'][$x];
            // Fill in each day with blanks
            $timetable[$x]['day'] = array_fill(1, $dayCount, array());
        }

        // Add classes for each day into it
        while ($desc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($timetable as $x => $timetablePeriod) {
                if (
                    $desc['PERIOD_NUMBER'] == $timetablePeriod['periodNumber']
                    && !isset($timetable[$x]['day'][$desc['DAY_NUMBER']][$desc['CODE']])
                ) {
                    $timetable[$x]['day'][$desc['DAY_NUMBER']][$desc['CODE']] = [
                        'description' => $desc['CLASS'],
                        'code'        => $desc['CODE'],
                        'campus'      => $desc['CAMPUS'],
                        'room'        => $desc['ROOM'],
                        'staffID'     => $desc['STAFFID'],
                    ];
                    break;
                }
            }
        }

        return $timetable;
    }


    /**
     * Gets the timetable for this room code.
     *
     * This implementation works with Edumate's peculiarities:
     * * Timetable def is hardcoded
     * * Timetable def ignores campus
     * * Edumate appears to have no concept of campuses
     * * getTimetableDefinition() returns DateTime objects
     *
     * @param string[] $roomCodes
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        /* Find all the occasions this room gets used, across all campuses everywhere
         */
        $campuses = \Schoolbox\CampusMapper::getInstance()->findAll();

        $result = [];

        foreach ($roomCodes as $roomCode) {
            $result = array_merge($result, $this->getOneRoomTimetable($roomCode, $campuses));
        }

        return $result;
    }


    /**
     * @param string                $roomCode
     * @param \Schoolbox\Collection $campuses
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function getOneRoomTimetable(string $roomCode, \Schoolbox\Collection $campuses): array
    {
        $result = [];
        $seen = [];

        $statement = $this->getConnection()->prepareQuery("
            SELECT
                T.ROOM AS ROOMCODE,
                CL.CLASS AS DESCRIPTION,
                T.DAY_NUMBER AS DAYNUMBER,
                T.PERIOD_NUMBER AS PERIODNUMBER,
                NULL AS TIMETABLEGROUP,
                NULL AS TIMETABLECAMPUS,
                T.CLASS_CODE AS CLASSCODE,
                T.STAFF_NUMBER AS STAFFID,
                ST.SALUTATION AS STAFFTITLE,
                ST.FIRSTNAME AS STAFFPREFERREDNAME,
                ST.SURNAME AS STAFFSURNAME
            FROM SCHOOLBOX_TIMETABLE AS T
            INNER JOIN SCHOOLBOX_CLASSES AS CL ON
                T.CLASS_CODE = CL.CLASS_CODE
            INNER JOIN SCHOOLBOX_STAFF AS ST ON
                T.STAFF_NUMBER = ST.STAFF_NUMBER
            WHERE T.ROOM = :room
            ORDER BY
                T.DAY_NUMBER ASC,
                T.PERIOD_NUMBER ASC
        ");

        $statement->execute([
            'room' => $roomCode
        ]);

        $rows = $statement->fetchAll();

        foreach ($campuses as $campus) {
            /* Get the timetable definition for $campus to see if $roomCode
             * appears in that campus' timetable somewhere
             */
            $campusTimetable = $this->getTimetableDefinition(null, $campus);

            foreach ($rows as $row) {
                /* If we saw this ClassCode in this (day, period) tuple before,
                 * then don't dupe it up
                 */
                $classCode = $row['CLASSCODE'];
                $day = $row['DAYNUMBER'];
                $period = $row['PERIODNUMBER'];

                if (isset($seen[$classCode][$day][$period])) {
                    continue;
                }

                /* Remember we saw this classCode on the given (day, period)
                 * We don't need to repeat the event if it spans multiple campuses
                 */
                $seen[$classCode][$day][$period] = $classCode;

                // Match day and period against a start and end.
                for ($i = 0; $i < $campusTimetable['periodCount']; $i++) {
                    $periodNumber   = $campusTimetable['number'][$i];
                    $periodSequence = $campusTimetable['sequence'][$i];

                    // (Ignore period sequence 2, everyone else does)
                    if ($periodNumber == $row['PERIODNUMBER'] && $periodSequence != 2) {
                        /* This is a scuffed db driver
                         *
                         * Manually reconstruct the data struct otherwise you get
                         * stuck in an eternally-processing script somehow
                         */
                        $result[] = [
                            'RoomCode' => $row['ROOMCODE'],
                            'Description' => $row['DESCRIPTION'],
                            'DayNumber' => $day,
                            'PeriodNumber' => $period,
                            'TimetableGroup' => $campus->getExtTimetableCode(),
                            'TimetableCampus' => $row['TIMETABLECAMPUS'],
                            'ClassCode' => $classCode,
                            'StaffID' => $row['STAFFID'],
                            'StaffTitle' => $row['STAFFTITLE'],
                            'StaffPreferredName' => $row['STAFFPREFERREDNAME'],
                            'StaffSurname' => $row['STAFFSURNAME'],
                            'PeriodStart' => $campusTimetable['start'][$i],
                            'PeriodEnd' => $campusTimetable['end'][$i],
                            'PeriodName' => $campusTimetable['desc'][$i]
                        ];

                        break;
                    }
                }
            }
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
}
