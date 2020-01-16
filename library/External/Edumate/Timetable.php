<?php

/**
 * Implementation of Edumate Timetable connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/

 */
class External_Edumate_Timetable extends External_Edumate_Connection implements External_TimetableInterface
{

    /** @var array */
    protected $campusCache;

    /** @var Group $groupHandler */
    private $groupHandler;

    public function __construct()
    {
        $this->groupHandler = new Group();
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function getTimetableDefinition(?string $externalUserId, \Schoolbox\Campus $campus, $day = 0, $date = null): array
    {

        $definition = array();
        $periodCount = 0;

        $stmt = $this->getConnection()->prepareQuery('
            SELECT PERIOD "desc", START_TIME "start", END_TIME "end", PERIOD_NUMBER "number", 1 "sequence"
            FROM SCHOOLBOX_TIMETABLE_DEFINITION TD
            WHERE TD.DAY_NUMBER = :day
            AND TD.CAMPUS = :campus
            ORDER BY TD.START_TIME ASC
        ');

        $campusCode = explode(',', $campus->getExtTimetableCode());

        $stmt->execute(array(
            'day' => $day ?: 1, // there is no day 0 in this definition
            'campus' => $campusCode[0]
        ));

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $definition['desc'][] = $r['desc'];
            $definition['start'][] = $this->parseTime($r['start']);
            $definition['end'][] = $this->parseTime($r['end']);
            $definition['number'][] = $r['number'];
            $definition['sequence'][] = $r['sequence'];
            ++$periodCount;
        }

        if ($periodCount == 0) {
            return [];
        }

        $definition['periodCount'] = $periodCount;

        $definition['dayCount'] = $this->getCycleLength($campus);

        return $definition;

    }


    /**
     * @param string $time
     * @return string
     */
    protected function parseTime($time)
    {
        $timeObject = new DateTime($time, new DateTimeZone('UTC'));
        return $timeObject->format('g:ia');
    }

    /**
     * Helper function to retrieve the length of the timetable cycle in edumate
     * @param \Schoolbox\Campus $campus
     * @return int
     */
    protected function getCycleLength(\Schoolbox\Campus $campus)
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
     * @param int|\Schoolbox\Campus $campus
     * @param int $date
     * @return bool|string
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
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

        $date = time();

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
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, \Schoolbox\User $user = null): array
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
    public function getDayTimetable(string $externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber): array
    {
        if (empty($externalUserId)) {
            return array();
        }
        if (!($definition = $this->getTimetableDefinition($externalUserId, $campus, $dayNumber))) {
            return [];
        }

        if ($this->groupHandler->isStudent($userId)) {
            $type = 'STUDENT';
            $join = "
                LEFT JOIN SCHOOLBOX_STUDENTCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STUDENT_NUMBER = :sn
            ";
        } elseif ($this->groupHandler->isStaff($userId)) {
            $type = 'STAFF';
            $join = "
                LEFT JOIN SCHOOLBOX_TEACHERCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STAFF_NUMBER = :sn
            ";
        } else {
            return [];
        }

        $campusCode = explode(',', $campus->getExtTimetableCode());
        if (count($campusCode) > 1) {
            $campus = "( CAMPUS = :campus OR CAMPUS = :campus2 )";
        } else {
            $campus = "CAMPUS = :campus";
        }

        $stmt = $this->getConnection()->prepareQuery(
            "
            SELECT PERIOD_NUMBER, T.CLASS_CODE AS CODE, C.{$type}_NUMBER AS STAFFID, ROOM, CL.CLASS
            FROM SCHOOLBOX_TIMETABLE T
            LEFT JOIN SCHOOLBOX_CLASSES CL ON T.CLASS_CODE = CL.CLASS_CODE
            $join
            AND DAY_NUMBER = :d AND $campus
        "
        );

        $stmt->execute(
            array(
                'sn' => $externalUserId,
                'd' => $dayNumber,
                'campus' => $campusCode[0],
                'campus2' => $campusCode[1],
            )
        );

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
                if ($desc['PERIOD_NUMBER'] == $timetablePeriod['periodNumber']
                    && !isset($timetable[$x]['class'][$desc['CODE']])
                ) {
                    $timetable[$x]['class'][$desc['CODE']] = array(
                        'description' => $desc['CLASS'],
                        'code' => $desc['CODE'],
                        'campus' => $desc['CAMPUS'],
                        'room' => $desc['ROOM'],
                        'staffID' => $desc['STAFFID'],
                    );
                    break;
                }
            }
        }

        return $timetable;
    }

    /**
     * @inheritDoc
     */
    public function getTimetableForDate(string $externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date): array
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus,
            $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }

    /**
     * @inheritDoc
     */
    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus): array
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
            $join = "
                LEFT JOIN SCHOOLBOX_STUDENTCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STUDENT_NUMBER = :sn
            ";
        } elseif ($this->groupHandler->isStaff($userId)) {
            $join = "
                LEFT JOIN SCHOOLBOX_TEACHERCLASS C ON T.CLASS_CODE = C.CLASS_CODE
                WHERE C.STAFF_NUMBER = :sn
            ";
        } else {
            return [];
        }

        $campusCode = explode(',', $campus->getExtTimetableCode());

        if (count($campusCode) > 1) {
            $campusSql = "( CAMPUS = :campus OR CAMPUS = :campus2 )";
        } else {
            $campusSql = "CAMPUS = :campus";
        }

        $stmt = $this->getConnection()->prepareQuery(
            "
            SELECT PERIOD_NUMBER, T.DAY_NUMBER, T.CLASS_CODE AS CODE, T.STAFF_NUMBER AS STAFFID, ROOM, CL.CLASS
            FROM SCHOOLBOX_TIMETABLE T
            LEFT JOIN SCHOOLBOX_CLASSES CL ON T.CLASS_CODE = CL.CLASS_CODE
            $join
            AND $campusSql
            AND DAY_NUMBER <= :d
        "
        );

        $stmt->execute(
            array(
                'sn' => $externalUserId,
                'campus' => $campusCode[0],
                'campus2' => $campusCode[1],
                'd' => $this->getCycleLength($campus),
            )
        );

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
                if ($desc['PERIOD_NUMBER'] == $timetablePeriod['periodNumber']
                    && !isset($timetable[$x]['day'][$desc['DAY_NUMBER']][$desc['CODE']])
                ) {
                    $timetable[$x]['day'][$desc['DAY_NUMBER']][$desc['CODE']] = array(
                        'description' => $desc['CLASS'],
                        'code' => $desc['CODE'],
                        'room' => $desc['ROOM'],
                        'staffID' => $desc['STAFFID'],
                    );
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
     * @param  string[] $roomCodes
     * @return array
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        $result = [];
        foreach ($roomCodes as $roomCode) {
            $result = array_merge($result, $this->getOneRoomTimetable($roomCode));
        }
        return $result;
    }


    /**
     * @param string $roomCode
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function getOneRoomTimetable(string $roomCode): array
    {
        $statement = $this->getConnection()->prepareQuery('
            SELECT
                T.ROOM AS "RoomCode",
                CL.CLASS AS "Description",
                T.DAY_NUMBER AS "DayNumber",
                T.PERIOD_NUMBER AS "PeriodNumber",
                NULL AS "TimetableGroup",
                NULL AS "TimetableCampus",
                T.CLASS_CODE AS "ClassCode",
                T.STAFF_NUMBER AS "StaffID",
                ST.SALUTATION AS "StaffTitle",
                ST.FIRSTNAME AS "StaffPreferredName",
                ST.SURNAME AS "StaffSurname"
            FROM SCHOOLBOX_TIMETABLE AS T
            INNER JOIN SCHOOLBOX_CLASSES AS CL ON
                T.CLASS_CODE = CL.CLASS_CODE
            INNER JOIN SCHOOLBOX_STAFF AS ST ON
                T.STAFF_NUMBER = ST.STAFF_NUMBER
            WHERE T.ROOM = :room
            ORDER BY
                T.DAY_NUMBER ASC,
                T.PERIOD_NUMBER ASC
        ');
        $statement->execute(array('room' => $roomCode));

        // Join with Edumate's (currently hardcoded and campusless) timetable.
        $campus = new \Schoolbox\Campus;
        $campusTimetable = $this->getTimetableDefinition(null, $campus);
        $result = array();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Match day and period against a start and end.
            for ($i = 0; $i < $campusTimetable['periodCount']; $i++) {
                $periodNumber = $campusTimetable['number'][$i];
                $periodSequence = $campusTimetable['sequence'][$i];

                // (Ignore period sequence 2, everyone else does)
                if ($periodNumber == $row['PeriodNumber'] && $periodSequence != 2) {
                    $row['PeriodStart'] = $campusTimetable['start'][$i];
                    $row['PeriodEnd'] = $campusTimetable['end'][$i];
                    $row['PeriodName'] = $campusTimetable['desc'][$i];
                    $result[] = $row;
                    break;
                }
            }
        }
        return $result;
    }
}
