<?php

/************************************************************
 *                                                           *
 *        Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to Denbigh timetable info    *
 *                                                           *
 ************************************************************/
class External_Schoolbox_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;

    private $database;

    public function __construct()
    {
        $this->setdatabase(Database::getInstance());
    }

    /**
     * This function returns a list of periods on for the day
     *
     * @param string $externalUserId
     * @param \Schoolbox\Campus $campus
     * @param int $day
     * @param null $date
     * @return array
     */
    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0, $date = null)
    {

        $timetableDef = array();

        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->getDatabase(), $campus);

        /* Must get at least one day */
        if($day == 0) {
            $day = 1;
        }

        $periodList = $this->getPeriodList($campus, $day);


        $periodCount = 0;
        foreach($periodList as $period) {
            $timetableDef['desc'][] = $period['title'];
            $timetableDef['start'][] = $period['start'];
            $timetableDef['finish'][] = $period['finish'];
            $timetableDef['number'][]   = $period[$periodCount+1];
            $timetableDef['sequence'][] = $period['sequence'];
        }

        $timetableDef['periodCount'] = $periodCount;
        $timetableDef['dayCount']    = $timetableDefinition['cycle_length'];

        return $timetableDef;
    }

    /**
     * @param \Schoolbox\Campus $campus
     * @param int $day
     * @return array
     */
    public function getPeriodList(\Schoolbox\Campus $campus, $day) {
        $query = "
            SELECT tp.id, tp.title, tp.day, tp.sequence, tp.start, tp.finish, tp.usable
            FROM timetable_campus tc
            LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
            LEFT JOIN timetable_period tp ON tp.cycle_id = ty.id
            WHERE tc.campus_id = :campusId
            ";

        if($day > 0) {
            $query .= " AND tp.day = :day ";
        }

        $query .= "ORDER BY tp.day, tp.sequence";

        $periodStatement = $this->getDatabase()->prepare($query);


        /* Get current campus timetable */
        if($day > 0) {
            $periodStatement->execute(
                array(
                    ':campusId' => $campus->getId(),
                    ':day'      => $day
                )
            );
        } else {
            $periodStatement->execute(
                array(
                    ':campusId' => $campus->getId(),
                )
            );
        }

        $periodList = $periodStatement->fetchAll(PDO::FETCH_ASSOC);

        return $periodList;
    }

    /**
     * @param \Schoolbox\Campus $campus
     * @param int $date
     * @return bool|int
     */
    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        return $this->getDayNumberFromDatabase($this->getDatabase(), $campus, $date);
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

    /**
     * Gets a statement for fetching a user's classes.
     *
     * @return PDOStatement
     */
    protected function getClassUserStatement()
    {
        // Get classes for which this user is involved, or is a teacher.
        $query = "
            SELECT
                c.name, c.code, c.description,
                cp.period_id, cp.room, cp.teacher_id,
                u.synergy_id, u.title, u.firstname, u.lastname
            FROM class c
            LEFT JOIN class_user   cl ON c.id = cl.class_id
            LEFT JOIN class_period cp ON c.id = cp.class_id
            LEFT JOIN user          u ON cp.teacher_id = u.id
            WHERE cp.teacher_id = :userId OR cl.user_id = :userId
        ";
        return $this->getDatabase()->prepare($query);
    }

    /**
     * Hilariously this method doesn't return a number at all!
     *
     * @param \Schoolbox\Campus $campus
     * @param int $now
     * @return array
     */
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0, $user = null)
    {
        $dayNumber = $this->getDayNumber($campus, $now);

        $query = "
            SELECT tp.id, tp.title, tp.day, tp.sequence, tp.start, tp.finish, tp.usable
            FROM timetable_campus tc
            LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
            LEFT JOIN timetable_period tp ON tp.cycle_id = ty.id
            WHERE tc.campus_id = :campusId
            AND tc.start < UNIX_TIMESTAMP(:now)
            AND tc.finish > UNIX_TIMESTAMP(:now)
            AND tp.day = :dayNumber
        ";

        $periodStatement = $this->getDatabase()->prepare($query);

        $periodStatement->execute(array(':campusId' => $campus->getId(), ':now' => $now, ':dayNumber' => $dayNumber));

        $item = $periodStatement->fetch(PDO::FETCH_ASSOC);

        if(is_array($item)) {
            return array(
                'PeriodNumber'    => $item['sequence'],
                'PeriodNumberSeq' => 1,
            );
        }
        return false;
    }

    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }

        $periodList = $this->getPeriodList($campus, 0);

        /* Get user's classes */
        // Is this a teacher? If so only get classes for which I'm a teacher
        $classStatement = $this->getClassUserStatement();
        $classStatement->execute(
            array(
                ':userId' => $userId,
            )
        );

        $classList = $classStatement->fetchAll(PDO::FETCH_ASSOC);


        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->getDatabase(), $campus);
        $dayCount            = $timetableDefinition['dayCount'];

        $periodListAdaption = array();


        foreach ($periodList as $period) {
            $periodSequence = $period['sequence'];

            $startTime = $period['start'] ? new Alaress_Date_Format($period['start'], Alaress_Date_Format::DATABASE_TIME_FORMAT) : new Alaress_Date_NullDate();
            $endTime = $period['finish'] ? new Alaress_Date_Format($period['finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT) : new Alaress_Date_NullDate();

            if (!isset($periodListAdaption[$periodSequence])) {
                $periodListAdaption[$periodSequence] = array(
                    'periodNumber'   => $period['sequence'],
                    'periodName'     => $period['title'],
                    'periodStart'    => $startTime->getTimeOnly(),
                    'periodEnd'      => $endTime->getTimeOnly(),
                    'periodSequence' => 1,
                    'day'            => array_fill(1, $dayCount, array()),
                );
            }

            $dayNumber = $period['day'];

            foreach ($classList as $i => $class) {
                if ($class['period_id'] == $period['id']) {
                    $periodListAdaption[$periodSequence]['day'][$dayNumber][$class['code']] = [
                        'description'    => $class['name'],
                        'code'           => $class['code'],
                        'room'           => $class['room'],
                        'staffCode'      => $class['synergy_id'],
                        'staffTitle'     => $class['title'],
                        'staffFirstname' => $class['firstname'],
                        'staffSurname'   => $class['lastname'],
                    ];
                    unset($classList[$i]);
                }
            }
        }

        return $periodListAdaption;
    }

    public function getDayTimetable($externalUserId, $userId, \Schoolbox\Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return array();
        }
        /* Get current campus timetable */
        $periodList = $this->getPeriodList($campus, $dayNumber);

        /* Get user's classes */
        $classStatement = $this->getClassUserStatement();
        $classStatement->execute(
            array(
                ':userId' => $userId,
            )
        );

        $classList = $classStatement->fetchAll(PDO::FETCH_ASSOC);

        $periodListAdaption = array();
        foreach ($periodList as $period) {

            $startTime = $period['start'] ? new Alaress_Date_Format($period['start'], Alaress_Date_Format::DATABASE_TIME_FORMAT) : new Alaress_Date_NullDate();
            $endTime = $period['finish'] ? new Alaress_Date_Format($period['finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT) : new Alaress_Date_NullDate();

            $periodAdaption = array(
                'periodNumber'   => $period['sequence'],
                'periodName'     => $period['title'],
                'periodStart'    => $startTime->getTimeOnly(),
                'periodEnd'      => $endTime->getTimeOnly(),
                'periodSequence' => 1,
                'class'          => [],
            );

            foreach ($classList as $i => $class) {
                if ($class['period_id'] == $period['id']) {
                    $periodAdaption['class'][$class['code']] = [
                        'description'    => $class['name'],
                        'code'           => $class['code'],
                        'room'           => $class['room'],
                        'staffCode'      => $class['synergy_id'],
                        'staffTitle'     => $class['title'],
                        'staffFirstname' => $class['firstname'],
                        'staffLastname'  => $class['lastname'],
                    ];
                    unset($classList[$i]);
                }
            }

            $periodListAdaption[] = $periodAdaption;
        }

        return $periodListAdaption;
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
     * Gets the timetable for the given room.
     *
     * @param  array|string $roomCodes
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        // Array? Get each room code recursively
        if (is_array($roomCodes)) {
            $result = [];
            foreach ($roomCodes as $roomCode) {
                $result = array_merge($result, $this->getRoomTimetable($roomCode));
            }
            return $result;
        }
        $query = "
            SELECT
                class_period.room AS RoomCode,
                class.description AS Description,
                timetable_period.day AS DayNumber,
                timetable_period.sequence AS PeriodNumber,
                luCampus.ext_timetable_code AS TimetableGroup,
                timetable_campus.campus_id AS TimetableCampus,
                class.code AS ClassCode,
                user.synergy_id AS StaffID,
                user.title AS StaffTitle,
                user.firstname AS StaffPreferredName,
                user.lastname AS StaffSurname,
                timetable_period.start AS PeriodStart,
                timetable_period.finish AS PeriodEnd,
                timetable_period.title AS PeriodName
            FROM class_period
            LEFT JOIN user ON
                class_period.teacher_id = user.id
            INNER JOIN timetable_period ON
                class_period.period_id = timetable_period.id
            INNER JOIN timetable_campus ON
                timetable_period.cycle_id = timetable_campus.cycle_id
            INNER JOIN luCampus ON
                timetable_campus.campus_id = luCampus.id
            INNER JOIN class ON
                class_period.class_id = class.id
            WHERE timetable_campus.start < NOW()
            AND   timetable_campus.finish > NOW()
            AND   class_period.room = :room
            ORDER BY
                timetable_period.day ASC,
                timetable_period.sequence ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array('room' => $roomCodes,));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param Database $database
     * @return void
     */
    private function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @return Database
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
