<?php

/**
 * Implementation of Edumate Timetable connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Edumate_Timetable extends External_Edumate_Connection implements External_TimetableInterface
{

    /** @var array */
    protected $campusCache;

    /**
     * @param int $campusId
     * @return string
     */
    protected function getCampusCode($campusId)
    {
        if (empty($this->campusCache[$campusId])) {
            $this->campusCache[$campusId] = \Schoolbox\CampusMapper::getInstance()->findById($campusId)->getExtTimetableCode();
        }

        return $this->campusCache[$campusId];
    }

    /**
     * Returns the timetable definition for a particular campus *FOR A PARTICULAR DAY*
     *
     * @param $campusId
     * @param int $day
     * @return array
     */
    public function getTimetableDefinition($campusId, $day = 0)
    {

        $definition = array();
        $periodCount = 0;

        $stmt = $this->getDatabase()->prepare('
            SELECT period "desc", start_time "start", end_time "end", period_number "number", 1 "sequence"
            FROM db2inst1.schoolbox_timetable_definition td
            WHERE td.day_number = :day
            AND td.campus = :campus
            ORDER BY td.start_time ASC
        ');

        $stmt->execute(array(
            'day'    => $day ?: 1, // there is no day 0 in this definition
            'campus' => $this->getCampusCode($campusId)
        ));

        while ($r = $stmt->fetch(DB2::FETCH_ASSOC)) {
            $definition['desc'][] = $r['desc'];
            $definition['start'][] = $this->parseTime($r['start']);
            $definition['end'][] = $this->parseTime($r['end']);
            $definition['number'][] = $r['number'];
            $definition['sequence'][] = $r['sequence'];
            ++$periodCount;
        }

        if ($periodCount == 0) {
            return false;
        }

        $definition['periodCount'] = $periodCount;

        $definition['dayCount'] = $this->getCycleLength($campusId);

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
     * @param int $campusId
     * @return int
     */
    protected function getCycleLength($campusId)
    {
        $stmt = $this->getDatabase()->prepare('
            SELECT day_number
            FROM db2inst1.schoolbox_timetable_definition
            WHERE campus = :campus
            ORDER BY day_number DESC
            FETCH FIRST 1 ROW ONLY
        ');

        $stmt->execute(array('campus' => $this->getCampusCode($campusId)));

        return $stmt->fetchColumn();
    }


    /**
     * @param int $campusId
     * @param int $time
     * @return bool|string
     */
    public function getDayNumber($campusId, $time = 0)
    {
        $stmt = $this->getDatabase()->prepare('
            SELECT "DAY"
            FROM db2inst1.schoolbox_timetable_days
            WHERE "DATE" = :date
            AND "CAMPUS" = :campus
            FETCH FIRST 1 ROW ONLY
        ');

        $params = array(
            'date'   => date('Y-m-d', $time ?: time()),
            'campus' => $this->getCampusCode($campusId),
        );

        $stmt->execute($params);

        if ($row = $stmt->fetch(DB2::FETCH_ASSOC)) {
            return $row['DAY'];
        }

        return false;
    }

    /**
     * Calculates the current period based on the current time
     *
     * @param $campusId
     * @return int
     */
    public function getPeriodNumber($campusId)
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $now = $this->parseTime($now->format('Hi'));

        $defn = $this->getTimetableDefinition($campusId);
        $sequence = 0;

        for ($i = 0; $i < count($defn['sequence']); ++$i) {
            // Time definitions are now in format "g:ia"
            $defTime = new DateTime($defn['end'][$i], new DateTimeZone('UTC'));
            if ($now < $defTime->format('Hi')) {
                $sequence = $defn['sequence'][$i];
                break;
            }
        }
        return $sequence;
    }

    /**
     * Return a user's timetable for a specific day
     *
     * @param $externalUserId
     * @param $userId
     * @param $campusId int ignored
     * @param $dayNumber
     * @return array|bool
     */
    public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        if (!($definition = $this->getTimetableDefinition($campusId, $dayNumber))) {
            return false;
        }

        if ($this->getGroupHandler()->isStudent($userId)) {
            $type = 'student';
            $join = "
                LEFT JOIN db2inst1.schoolbox_studentclass c ON t.class_code = c.class_code
                WHERE c.student_number = :sn
            ";
        } elseif ($this->getGroupHandler()->isStaff($userId)) {
            $type = 'staff';
            $join = "
                LEFT JOIN db2inst1.schoolbox_teacherclass c ON t.class_code = c.class_code
                WHERE c.staff_number = :sn
            ";
        } else {
            return false;
        }

        $stmt = $this->getDatabase()->prepare(
            "
            SELECT period_number AS description, t.class_code AS code, c.{$type}_number AS staffID, room, cl.class
            FROM db2inst1.schoolbox_timetable t
            LEFT JOIN db2inst1.schoolbox_classes cl ON t.class_code = cl.class_code
            $join
            AND day_number = :d
        "
        );

        $stmt->execute(
            array(
                'sn' => $externalUserId,
                'd'  => $dayNumber
            )
        );

        $classes = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // index on description
            $classes[$row['DESCRIPTION']] = $row;
        }

        // arrange data as $timetable[$sequence] = array('period[Name|Start|End|Number|Sequence]');
        $timetable = array();
        for ($i = 0; isset($definition['sequence'][$i]); ++$i) {
            // the 'shortName' of the period
            $code = $definition['number'][$i];

            $timetable[$i] = array(
                'periodName'     => $definition['desc'][$i],
                'periodStart'    => $definition['start'][$i],
                'periodEnd'      => $definition['end'][$i],
                'periodNumber'   => $definition['number'][$i],
                'periodSequence' => $definition['sequence'][$i]
            );

            // if no class on this period, skip it
            if (isset($classes[$code])) {
                $timetable[$i]['class'][0]['description'] = $classes[$code]['CLASS'];
                $timetable[$i]['class'][0]['code'] = $classes[$code]['CODE'];
                $timetable[$i]['class'][0]['room'] = $classes[$code]['ROOM'];
                $timetable[$i]['class'][0]['staffID'] = $classes[$code]['STAFFID'];
            }

        }

        return $timetable;

    }

    /**
     * Return the ENTIRE timetable for a user
     *
     * @param $externalUserId
     * @param $userId
     * @param $campusId
     * @return array|bool
     */
    public function getFullTimetable($externalUserId, $userId, $campusId)
    {
        if (empty($externalUserId)) {
            return array();
        }
        $definition = $this->getTimetableDefinition($campusId);
        $dayCount = is_numeric($definition['dayCount']) ? $definition['dayCount'] : 0;

        if (!$dayCount) {
            return false;
        }

        if ($this->getGroupHandler()->isStudent($userId)) {
            $join = "
                LEFT JOIN db2inst1.schoolbox_studentclass c ON t.class_code = c.class_code
                WHERE c.student_number = :sn
            ";
        } elseif ($this->getGroupHandler()->isStaff($userId)) {
            $join = "
                WHERE t.staff_number = :sn
            ";
        } else {
            return false;
        }

        $stmt = $this->getDatabase()->prepare(
            "
            SELECT period_number AS description, t.day_number, t.class_code AS code, t.staff_number AS staffID, room, cl.class
            FROM db2inst1.schoolbox_timetable t
            LEFT JOIN db2inst1.schoolbox_classes cl ON t.class_code = cl.class_code
            $join
        "
        );

        $stmt->execute(
            array(
                'sn' => $externalUserId,
            )
        );

        // compile the returned data
        $classes = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[$row['DESCRIPTION']][$row['DAY_NUMBER']] = $row;
        }

        $timetable = array();
        for ($i = 0; isset($definition['sequence'][$i]); ++$i) {
// the 'shortName' of the period
            $code = $definition['number'][$i];

            $timetable[$i] = array(
                'periodName'     => $definition['desc'][$i],
                'periodStart'    => $definition['start'][$i],
                'periodEnd'      => $definition['end'][$i],
                'periodNumber'   => $definition['number'][$i],
                'periodSequence' => $definition['sequence'][$i]
            );

            for ($j = 1; $j <= $dayCount; ++$j) {
                // period has class for user
                if (isset($classes[$code][$j])) {
                    $timetable[$i]['day'][$j][0]['description'] = $classes[$code][$j]['CLASS'];
                    $timetable[$i]['day'][$j][0]['code'] = $classes[$code][$j]['CODE'];
                    $timetable[$i]['day'][$j][0]['room'] = $classes[$code][$j]['ROOM'];
                    $timetable[$i]['day'][$j][0]['staffID'] = $classes[$code][$j]['STAFFID'];
                } // user has not class in this period
                else {
                    $timetable[$i]['day'][$j] = array();
                }
            }
        }

        return $timetable;
    }

    /**
     * Retrieve the list of subjects for a user on a particular day
     *
     * @param $externalUserId
     * @param $dayNumber
     * @return bool
     */
    public function getDaySubjects($externalUserId, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        // Timetable.Room, SubjectClasses.Description, Timetable.PeriodNumber, Timetable.ClassCode, SubjectClasses.ClassCampus

        $user = $this->getUserHandler()->getBySynergy($externalUserId);

        $dayTimetable = $this->getDayTimetable($externalUserId, $user['uid'], 0, $dayNumber);

        if ($dayTimetable === false) {
            return false;
        }

        foreach ($dayTimetable as $k => $period) {
            if (!isset($period['code'])) {
                unset($dayTimetable[$k]);
            }
        }

        return array_values($dayTimetable);

    }

    /**
     * give a subject code and day this function returns the period that it occurs in
     * Assumption made that this should be the sequence rather than period name
     *
     * @param $classCode
     * @param $dayNumber
     * @return int|bool
     */
    public function getSubjectPeriod($classCode, $dayNumber)
    {
        $stmt = $this->getDatabase()->prepare('
            SELECT period_number
            FROM db2inst1.schoolbox_timetable t
            LEFT JOIN db2inst1.schoolbox_timetable_definition td
              ON t.campus = td.campus
              AND t.day_number = td.day_number
              AND t.period_number = td.period_number
            WHERE t.class_code = :code
            AND t.day = :day
        ');

        $stmt->execute(
            array(
                'code' => $classCode,
                'day'  => $dayNumber
            )
        );

        return (int)$stmt->fetchColumn() ?: false;
    }

    /**
     * Retrieves the name of a subject based on its class code
     *
     * @param $classCode
     * @return string
     */
    public function getSubjectName($classCode)
    {
        $stmt = $this->getDatabase()->prepare(
            "
                        SELECT class
                        FROM db2inst1.schoolbox_classes
                        WHERE class_code = :code
                    "
        );

        $stmt->execute(array('code' => $classCode));

        return $stmt->fetchColumn(0);
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
     * @param  string $roomCode
     * @return array
     */
    public function getRoomTimetable($roomCode)
    {
        $statement = $this->getDatabase()->prepare('
            SELECT
                cl.CLASS AS "Description",
                t.DAY_NUMBER AS "DayNumber",
                t.PERIOD_NUMBER AS "PeriodNumber",
                NULL AS "TimetableGroup",
                NULL AS "TimetableCampus",
                t.CLASS_CODE AS "ClassCode",
                t.STAFF_NUMBER AS "StaffID",
                st.SALUTATION AS "StaffTitle",
                st.FIRSTNAME AS "StaffPreferredName",
                st.SURNAME AS "StaffSurname"
            FROM db2inst1.schoolbox_timetable AS t
            INNER JOIN db2inst1.schoolbox_classes AS cl ON
                t.CLASS_CODE = cl.CLASS_CODE
            INNER JOIN db2inst1.schoolbox_staff AS st ON
                t.STAFF_NUMBER = st.STAFF_NUMBER
            WHERE t.ROOM = :room
            ORDER BY
                t.DAY_NUMBER ASC,
                t.PERIOD_NUMBER ASC
        ');
        $statement->execute(array('room' => $roomCode));

        // Join with Edumate's (currently hardcoded and campusless) timetable.
        $campusTimetable = $this->getTimetableDefinition(1);
        $result = array();
        foreach ($statement->fetchAll(DB2::FETCH_ASSOC) as $row) {
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
