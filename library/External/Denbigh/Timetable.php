<?php

/************************************************************
 *                                                           *
 *        Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to Denbigh timetable info        *
 *                                                           *
 ************************************************************/
class External_Denbigh_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;

    private $internalDatabase;

    private $externalDatabase;

    private $group;

    public function __construct()
    {
        /* Set internal database */
        $this->setInternalDatabase(Database::getInstance());

        /* Establish connection to external database */
        $database = new Database();

        $hostName = Config::get('external_db_host');
        $databaseName = Config::get('external_db_db');
        $username = Config::get('external_db_user');
        $password = Config::get('external_db_passwd');

        $database->connect("mysql:host=$hostName;dbname=$databaseName", $username, $password, array('throw' => true));

        $this->setExternalDatabase($database);

        $this->setGroup(new Group);
    }

    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0, $date = null)
    {
        return $this->getTimetableDefinitionFromDatabase($this->getInternalDatabase(), $campus);
    }

    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        return $this->getDayNumberFromDatabase($this->getInternalDatabase(), $campus, $date);
    }


    /**
     * Returns the day name
     *
     * @param $dayNumber
     * @return String
     */
    public function getDayName($dayNumber)
    {
        return 'Day ' . $dayNumber;
    }

    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
    {
        return 0;
    }

    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        /* Setup skeleton timetable */
        if (!$definition = $this->getTimetableDefinition($externalUserId, $campus)) {
            return false;
        }

        $periodList = array();

        for ($i = 1; $i <= $definition['periodCount']; $i++) {
            $periodList[$i] = array(
                'periodNumber'   => $i,
                'periodName'     => "Period $i",
                'periodStart'    => '',
                'periodEnd'      => '',
                'periodSequence' => '1',
                'day'            => array_fill(1, $definition['dayCount'], array()),
            );
        }

        /* Fill timetable */
        if ($this->getGroup()->isStaff($userId)) {
            $statement = $this->getExternalDatabase()->prepare(
                "
                                SELECT l.day, l.period, startTime, endTime, l.classCode, l.staffCode, l.room, co.subjectName
                                FROM People p
                                RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
                                RIGHT JOIN Lessons l ON l.staffCode = s.staffCode
                                RIGHT JOIN Classes cl ON cl.classCode = l.classCode
                                RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
                                WHERE p.denbighID = :externalUserId
                            "
            );
        } else {
            $statement = $this->getExternalDatabase()->prepare(
                "
                                SELECT l.day, l.period, startTime, endTime, l.classCode, l.staffCode, l.room, co.subjectName
                                FROM People p
                                RIGHT JOIN Enrolments e ON e.studentID = p.denbighID
                                RIGHT JOIN Lessons l ON l.classCode = e.classCode
                                RIGHT JOIN Classes cl ON cl.classCode = l.classCode
                                RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
                                WHERE p.denbighID = :externalUserId
                            "
            );
        }

        $statement->bindParam(':externalUserId', $externalUserId, PDO::PARAM_INT);
        $statement->execute();

        $lessonList = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($lessonList)) {
            foreach ($lessonList as $lesson) {
                $period = $lesson['period'];
                $day = $lesson['day'];
                $periodList[$period]['periodStart'] = $lesson['startTime'] == '' ? '' : date(Cfg::get('date_time'), strtotime($lesson['startTime']));
                $periodList[$period]['periodEnd'] = $lesson['endTime'] == '' ? '' : date(Cfg::get('date_time'), strtotime($lesson['endTime']));

                if (isset($periodList[$period]['day'][$day])
                    && !isset($periodList[$period]['day'][$day][$lesson['ClassCode']])) {
                    $periodList[$period]['day'][$day][$lesson['ClassCode']] = array(
                        'description' => $lesson['subjectName'],
                        'code'        => $lesson['classCode'],
                        'room'        => $lesson['room']
                    );
                }

            }
        }

        return $periodList;
    }

    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        /* Setup skeleton timetable */
        if (!$definition = $this->getTimetableDefinition($externalUserId, $campus)) {
            return false;
        }

        $periodList = array();

        for ($i = 1; $i <= $definition['periodCount']; $i++) {
            $periodList[$i] = array(
                'periodNumber'   => $i,
                'periodName'     => "Period $i",
                'periodStart'    => '',
                'periodEnd'      => '',
                'periodSequence' => '1',
            );
        }

        /* Fill timetable */
        if ($this->getGroup()->isStaff($userId)) {
            $statement = $this->getExternalDatabase()->prepare(
                "
                                SELECT l.day, l.period, startTime, endTime, l.classCode, l.staffCode, l.room, co.subjectName
                                FROM People p
                                RIGHT JOIN Staff s ON s.peopleDenbighID = p.denbighID
                                RIGHT JOIN Lessons l ON l.staffCode = s.staffCode
                                RIGHT JOIN Classes cl ON cl.classCode = l.classCode
                                RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
                                WHERE p.denbighID = :externalUserId
                                    AND l.day = :dayNumber
                            "
            );
        } else {
            $statement = $this->getExternalDatabase()->prepare(
                "
                                SELECT l.day, l.period, startTime, endTime, l.classCode, l.staffCode, l.room, co.subjectName
                                FROM People p
                                RIGHT JOIN Enrolments e ON e.studentID = p.denbighID
                                RIGHT JOIN Lessons l ON l.classCode = e.classCode
                                RIGHT JOIN Classes cl ON cl.classCode = l.classCode
                                RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
                                WHERE p.denbighID = :externalUserId
                                    AND l.day = :dayNumber
                            "
            );
        }

        $statement->bindParam(':externalUserId', $externalUserId);
        $statement->bindParam(':dayNumber', $dayNumber, PDO::PARAM_INT);
        $statement->execute();

        $lessonList = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($lessonList)) {
            foreach ($lessonList as $lesson) {
                $period = $lesson['period'];
                $periodList[$period]['periodStart'] = $lesson['startTime'] == '' ? '' : date(Cfg::get('date_time'), strtotime($lesson['startTime']));
                $periodList[$period]['periodEnd'] = $lesson['endTime'] == '' ? '' : date(Cfg::get('date_time'), strtotime($lesson['endTime']));

                if (isset($periodList[$period])
                    && !isset($periodList[$period]['class'][$lesson['ClassCode']])
                ) {
                    $periodList[$period]['class'][$lesson['ClassCode']] = array_merge(
                        $periodList[$period],
                        array(
                            'description' => $lesson['subjectName'],
                            'code'        => $lesson['classCode'],
                            'room'        => $lesson['room']
                        )
                    );
                }

            }
        }

        return $periodList;
    }

    /**
     * Return a user's timetable for a specific date
     *
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     *
     * @param $externalUserId
     * @param $userId
     * @param Schoolbox\Campus $campus
     * @param Alaress_Date_Format $date
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, \Schoolbox\Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable($externalUserId, $userId, $campus, $this->getDayNumber($campus, $date->getUnixTimeStamp()));
    }

    /**
     * This method cannot be implemented for Denbigh, because its timetable
     * definition has no concept of the start and end time of periods.
     *
     * If it did, it would work around the following peculiarities:
     * * Timetable def is in the internal Schoolbox database
     * * Timetable def doesn't ignore campuses, but Edumate doesn't have them
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        return array();
//        // First: $this->getFullTimetable() and pinch its query
//        // Second: join to whatever is in External_*_User->getStaff
//        // Third: join to the timetable implementation if at all possible
//        $query = "
//            SELECT
//                co.subjectName AS Description,
//                l.day AS DayNumber,
//                l.period AS PeriodNumber,
//                '' AS TimetableGroup,
//                '' AS ClassCampus,
//                l.classCode AS ClassCode,
//                l.staffCode AS StaffID,
//                p.title AS StaffTitle,
//                p.preferred_name AS StaffPreferredName,
//                p.surname AS StaffSurname
//            FROM Lessons l
//            LEFT JOIN Staff AS s ON
//                l.staffCode = s.staffCode
//            LEFT JOIN People AS p ON
//                s.peopleDenbighId = p.denbighId
//            INNER JOIN Classes AS cl ON
//                l.classCode = cl.classCode
//            INNER JOIN Courses co ON
//                cl.courseCode = co.courseCode
//            WHERE l.room = :room
//            ORDER BY
//                l.day ASC,
//                l.period ASC
//        ";
//        $statement = $this->getExternalDatabase()->prepare($query);
//        $statement->execute(array('room' => $roomCode));
//
//        // Denbigh doesn't seem to have any campus data for its timetables:
//        // just link to the first campus.
//        $campusId = $this->getInternalDatabase()->query("
//            SELECT id
//            FROM luCampus
//            ORDER BY id ASC
//            LIMIT 1
//        ")->fetchColumn();
//
//        $campusTimetable = $this->getTimetableDefinition($campusId);
//        new Alaress_Probe($campusTimetable);
//        $result          = array();
//        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
//
//            // Match day and period against a start and end.
//            for ($i = 0; $i < $campusTimetable['periodCount']; $i++) {
//                $periodNumber   = $campusTimetable['number'][$i];
//                $periodSequence = $campusTimetable['sequence'][$i];
//
//                // (Ignore period sequence 2, everyone else does)
//                if ($periodNumber == $row['PeriodNumber'] && $periodSequence != 2) {
//                    $row['PeriodStart'] = $campusTimetable['start'][$i];
//                    $row['PeriodEnd']   = $campusTimetable['end'][$i];
//                    $row['PeriodName']  = $campusTimetable['desc'][$i];
//                    $result[] = $row;
//                    break;
//                }
//            }
//        }
//        return $result;
    }

    /**
     * @param Database $database
     * @return void
     */
    private function setInternalDatabase(Database $internalDatabase)
    {
        $this->internalDatabase = $internalDatabase;
    }

    /**
     * @return Database
     */
    private function getInternalDatabase()
    {
        return $this->internalDatabase;
    }

    /**
     * @param Database $database
     * @return void
     */
    private function setExternalDatabase(Database $externalDatabase)
    {
        $this->externalDatabase = $externalDatabase;
    }

    /**
     * @return Database
     */
    private function getExternalDatabase()
    {
        return $this->externalDatabase;
    }

    /**
     * @param Group $group
     * @return void
     */
    private function setGroup(Group $group)
    {
        $this->group = $group;
    }

    /**
     * @return Group
     */
    private function getGroup()
    {
        return $this->group;
    }
}
