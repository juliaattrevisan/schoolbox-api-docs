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
        if (empty($roomCodes)) {
            return [];
        }

        // Get the first timetable group, that's the best I can do
        // (because Denbigh lessons don't have a campus)
        $campusQuery = $this->getInternalDatabase()->query('SELECT ext_timetable_code FROM luCampus LIMIT 1');
        if ($campusQuery->rowCount() == 0) {
            return [];
        }
        $timetableGroup = $campusQuery->fetchColumn();

        $query = "SELECT l.day AS DayNumber, 
                        l.period AS PeriodNumber, 
                        startTime AS PeriodStart, 
                        endTime AS PeriodEnd, 
                        l.classCode AS ClassCode, 
                        l.staffCode AS StaffID, 
                        l.room AS RoomCode, 
                        co.subjectName AS Description,
                        :timetableGroup AS TimetableGroup
                        FROM Lessons l 
                        RIGHT JOIN Classes cl ON cl.classCode = l.classCode
                        RIGHT JOIN Courses co ON co.courseCode = cl.courseCode
                        WHERE l.room IN (:rooms)";

        $query = strtr(
            $query,
            array(
                ':timetableGroup' => $this->externalDatabase->quote($timetableGroup),
                ':rooms'          => implode(',', array_map([$this->externalDatabase, 'quote'], $roomCodes)),
            )
        );

        $statement = $this->getExternalDatabase()->prepare($query);
        $statement->execute();


        return $statement->fetchAll(PDO::FETCH_ASSOC);
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
