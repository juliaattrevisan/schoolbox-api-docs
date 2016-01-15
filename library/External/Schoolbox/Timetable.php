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
    private $database;

    public function __construct()
    {
        $this->setdatabase(Database::getInstance());
    }

    public function getTimetableDefinition($externalUserId, \Schoolbox\Campus $campus, $day = 0)
    {
        $statement = $this->getDatabase()->prepare(
            "
                        SELECT tc.start, tc.reset, tc.finish, tc.offset,
                            ty.title, ty.cycle_length, ty.day_length, ty.regular
                        FROM timetable_campus tc
                        LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
                        WHERE tc.campus_id = :campusId
                            AND tc.start < NOW()
                            AND tc.finish > NOW()
                    "
        );

        $statement->execute(array(':campusId' => $campus->getId()));

        if ($definition = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* Adapt to spec */
            $definition['dayCount'] = $definition['cycle_length'];
            $definition['periodCount'] = $definition['day_length'];

            return $definition;
        } else {
            return false;
        }
    }

    public function getDayNumber(\Schoolbox\Campus $campus, $date = 0)
    {
        if (!$definition = $this->getTimetableDefinition(null, $campus)) {
            return false;
        }

        if (empty($date)) {
            $date = time();
        }

        // Is this a Saturday or Sunday?
        // I assume because we don't count non business days, then this is
        // outside the timetable.
        if (date('N', $date) >= 6) {
            return 0;
        }

        if (empty($definition['reset']) || $definition['reset'] == '0000-00-00') {
            $start = strtotime($definition['start']);
        } else {
            $start = strtotime($definition['reset']);
        }

        $days = ceil(($date - $start) / (60 * 60 * 24)) + 1;

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

        if (is_numeric($definition['offset'])) {
            $businessDays += $definition['offset'];
        }

        $currentDay = $businessDays % $definition['cycle_length'];

        // Last day of cycle? Show last day, instead of 0
        if ($currentDay == 0) {
            $currentDay = $definition['cycle_length'];
        }

        return $currentDay;
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
     * Gets a statement for fetching the periods for a given campus. Has
     * parameters :campusId (and :day if $single_day is true).
     *
     * @param bool $single_day True if fetching only a single day's timetable.
     *
     * @return PDOStatement
     */
    protected function getPeriodStatement($single_day = false)
    {
        $query = "
            SELECT tp.id, tp.title, tp.day, tp.sequence, tp.start, tp.finish, tp.usable
            FROM timetable_campus tc
            LEFT JOIN timetable_cycle ty ON ty.id = tc.cycle_id
            LEFT JOIN timetable_period tp ON tp.cycle_id = ty.id
            WHERE tc.campus_id = :campusId
            AND tc.start < NOW()
            AND tc.finish > NOW()
        ";
        if ($single_day) {
            $query .= ' AND tp.day = :day ';
        }
        return $this->getDatabase()->prepare($query);
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
    public function getPeriodNumber(\Schoolbox\Campus $campus, $now = 0)
    {
        // Surely this should take a day number????

        if (!$definition = $this->getTimetableDefinition(null, $campus)) {
            return false;
        }

        if (empty($definition['regular'])) {
            return false;
        }

        $periodStatement = $this->getPeriodStatement(false);
        $periodStatement->execute(array(':campusId' => $campus->getId(),));
        $current_time = date('H:i:s', $now ?: time());
        foreach ($periodStatement as $item) {
            if ($item['start'] <= $current_time && $item['finish'] >= $current_time) {
                return array(
                    'PeriodNumber'    => $item['sequence'],
                    'PeriodNumberSeq' => 1,
                );
            }
        }
        return false;
    }

    public function getFullTimetable($externalUserId, $userId, \Schoolbox\Campus $campus)
    {
        if (empty($externalUserId)) {
            return array();
        }
        /* Get current campus timetable */
        $periodStatement = $this->getPeriodStatement(false);
        $periodStatement->execute(
            array(
                ':campusId' => $campus->getId(),
            )
        );

        $periodList = $periodStatement->fetchAll(PDO::FETCH_ASSOC);

        /* Get user's classes */
        // Is this a teacher? If so only get classes for which I'm a teacher
        $classStatement = $this->getClassUserStatement();
        $classStatement->execute(
            array(
                ':userId' => $userId,
            )
        );

        $classList = $classStatement->fetchAll(PDO::FETCH_ASSOC);

        $timetableDefinition = $this->getTimetableDefinition($externalUserId, $campus);
        $dayCount            = $timetableDefinition['dayCount'];

        $periodListAdaption = array();

        foreach ($periodList as $period) {
            $periodSequence = $period['sequence'];

            if (!isset($periodListAdaption[$periodSequence])) {
                $periodListAdaption[$periodSequence] = array(
                    'periodNumber'   => $period['sequence'],
                    'periodName'     => $period['title'],
                    'periodStart'    => $period['start'],
                    'periodEnd'      => $period['finish'],
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
        $periodStatement = $this->getPeriodStatement(true);
        $periodStatement->execute(
            array(
                ':campusId' => $campus->getId(),
                ':day'      => $dayNumber
            )
        );

        $periodList = $periodStatement->fetchAll(PDO::FETCH_ASSOC);

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
            $periodAdaption = array(
                'periodNumber'   => $period['sequence'],
                'periodName'     => $period['title'],
                'periodStart'    => $period['start'],
                'periodEnd'      => $period['finish'],
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
