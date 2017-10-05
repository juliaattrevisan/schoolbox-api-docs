<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Campus;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\User;

class External_Schoolbox_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;

    /** @var IConnector $internalDatabase */
    private $internalDatabase;

    /** @var ConfigMapper $configMapper */
    private $configMapper;

    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        $this->configMapper = ConfigMapper::getInstance();

        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }

    /**
     * @inheritdoc
     *
     * @param string $externalUserId
     * @param Campus $campus
     * @param int    $day
     * @param null   $date
     *
     * @return array
     */
    public function getTimetableDefinition($externalUserId, Campus $campus, $day = 0, $date = null)
    {
        $timetableDef = [];

        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->internalDatabase, $campus);

        /* Must get at least one day */
        if ($day == 0) {
            $day = 1;
        }

        $periodList = $this->getPeriodList($campus, $day);

        $periodCount = 0;

        foreach ($periodList as $period) {
            $timetableDef['desc'][]     = $period['title'];
            $timetableDef['start'][]    = $period['start'];
            $timetableDef['end'][]      = $period['finish'];
            $timetableDef['number'][]   = $period[$periodCount + 1];
            $timetableDef['sequence'][] = $period['sequence'];
        }

        $timetableDef['periodCount'] = $periodCount;
        $timetableDef['dayCount']    = $timetableDefinition['cycle_length'];

        return $timetableDef;
    }

    /**
     * @inheritdoc
     *
     * @param Campus $campus
     * @param int    $day
     *
     * @return array
     */
    public function getPeriodList(Campus $campus, $day)
    {
        $query = "
            SELECT        tp.id, 
                          tp.title, 
                          tp.day, 
                          tp.sequence, 
                          tp.start, 
                          tp.finish, 
                          tp.usable
            FROM          timetable_campus tc
            LEFT JOIN     timetable_cycle ty ON ty.id = tc.cycle_id
            LEFT JOIN     timetable_period tp ON tp.cycle_id = ty.id
            WHERE         tc.campus_id = :campusId AND 
                          tc.start <= DATE(NOW()) AND 
                          tc.finish >= DATE(NOW())
        ";

        if ($day > 0) {
            $query .= "AND tp.day = :day ";
        }

        $query .= "ORDER BY tp.day, tp.sequence";

        $periodStatement = $this->internalDatabase->prepareQuery($query);

        if ($day > 0) {
            $periodStatement->execute([
                ':campusId' => $campus->getId(),
                ':day'      => $day,
            ]);
        } else {
            $periodStatement->execute([
                ':campusId' => $campus->getId(),
            ]);
        }

        $periodList = $periodStatement->fetchAll();

        return $periodList;
    }

    /**
     * @inheritdoc
     *
     * @param Campus $campus
     * @param int    $date
     *
     * @return bool|int
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return $this->getDayNumberFromDatabase($this->internalDatabase, $campus, $date);
    }

    /**
     * @inheritdoc
     *
     * @param int $dayNumber
     *
     * @return string
     */
    public function getDayName($dayNumber)
    {
        return 'Day ' . $dayNumber;
    }

    /**
     * Gets a statement for fetching a user's classes.
     *
     * @return IQuery
     */
    protected function getClassUserStatement()
    {
        // Get classes for which this user is involved, or is a teacher.
        $query = "

            SELECT      c.name, 
                        c.code, 
                        c.description,
                        cp.period_id, 
                        cp.room, 
                        cp.teacher_id,
                        u.synergy_id, 
                        u.title, 
                        u.firstname, 
                        u.lastname
            FROM        class c
            LEFT JOIN   class_user cl ON c.id = cl.class_id
            LEFT JOIN   class_period cp ON c.id = cp.class_id
            LEFT JOIN   user u ON cp.teacher_id = u.id
            WHERE       cp.teacher_id = :userId OR 
                        cl.user_id = :userId
        ";

        return $this->internalDatabase->prepareQuery($query);
    }

    /**
     * @inheritdoc
     *
     * @param Campus    $campus
     * @param int       $now
     * @param null|User $user
     *
     * @return array
     */
    public function getPeriodNumber(Campus $campus, $now = 0, $user = null)
    {
        $dayNumber = $this->getDayNumber($campus, $now);
        $now       = $now ?: time();

        $periodStatement = $this->internalDatabase->executeQuery(
            "
                SELECT      tp.id, 
                            tp.title, 
                            tp.day, 
                            tp.sequence, 
                            tp.start, 
                            tp.finish, 
                            tp.usable
                FROM        timetable_campus tc
                LEFT JOIN   timetable_cycle ty ON ty.id = tc.cycle_id
                LEFT JOIN   timetable_period tp ON tp.cycle_id = ty.id
                WHERE       tc.campus_id = :campusId
                AND         tc.start <= DATE(NOW())
                AND         tc.finish >= DATE(NOW())
                AND         CAST(FROM_UNIXTIME(:now, '%h:%i:%s') AS TIME) BETWEEN tp.start AND tp.finish
                AND         tp.day = :dayNumber
            ",
            [
                ':campusId'  => $campus->getId(),
                ':now'       => $now,
                ':dayNumber' => $dayNumber,
            ]
        );

        $item = $periodStatement->fetch();

        if (is_array($item)) {
            return [
                'PeriodNumber'    => $item['sequence'],
                'PeriodNumberSeq' => 1,
            ];
        }

        return [];
    }

    /**
     * @inheritdoc
     *
     * @param int    $externalUserId
     * @param int    $userId
     * @param Campus $campus
     *
     * @return array
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus)
    {
        if (empty($externalUserId)) {
            return [];
        }

        $periodList = $this->getPeriodList($campus, 0);

        $classStatement = $this->getClassUserStatement();
        $classStatement->execute([':userId' => $userId]);

        $classList = $classStatement->fetchAll();

        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->internalDatabase, $campus);
        $dayCount            = $timetableDefinition['dayCount'];

        $periodListAdaption = [];

        foreach ($periodList as $period) {
            $periodSequence = $period['sequence'];

            $startTime =
                $period['start']
                    ? new Alaress_Date_Format($period['start'], Alaress_Date_Format::DATABASE_TIME_FORMAT)
                    : new Alaress_Date_NullDate();

            $endTime =
                $period['finish']
                    ? new Alaress_Date_Format($period['finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT)
                    : new Alaress_Date_NullDate();

            if (!isset($periodListAdaption[$periodSequence])) {
                $periodListAdaption[$periodSequence] = [
                    'periodNumber'   => $period['sequence'],
                    'periodName'     => $period['title'],
                    'periodStart'    => $startTime->getTimeOnly(),
                    'periodEnd'      => $endTime->getTimeOnly(),
                    'periodSequence' => 1,
                    'day'            => array_fill(1, $dayCount, []),
                ];
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

    /**
     * @inheritdoc
     *
     * @param int    $externalUserId
     * @param int    $userId
     * @param Campus $campus
     * @param int    $dayNumber
     *
     * @return array
     */
    public function getDayTimetable($externalUserId, $userId, Campus $campus, $dayNumber)
    {
        if (empty($externalUserId)) {
            return [];
        }

        $periodList = $this->getPeriodList($campus, $dayNumber);

        $classStatement = $this->getClassUserStatement();
        $classStatement->execute([':userId' => $userId]);

        $classList = $classStatement->fetchAll();

        $periodListAdaption = [];

        foreach ($periodList as $period) {
            $startTime =
                $period['start']
                    ? new Alaress_Date_Format($period['start'], Alaress_Date_Format::DATABASE_TIME_FORMAT)
                    : new Alaress_Date_NullDate();

            $endTime =
                $period['finish']
                    ? new Alaress_Date_Format($period['finish'], Alaress_Date_Format::DATABASE_TIME_FORMAT)
                    : new Alaress_Date_NullDate();

            $periodAdaption = [
                'periodNumber'   => $period['sequence'],
                'periodName'     => $period['title'],
                'periodStart'    => $startTime->getTimeOnly(),
                'periodEnd'      => $endTime->getTimeOnly(),
                'periodSequence' => 1,
                'class'          => [],
            ];

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
     * Depending on the role for $userId (staff or student) this summons the respective
     * method to spawn associated timetable
     *
     * @param int                 $externalUserId
     * @param int                 $userId
     * @param Campus              $campus
     * @param Alaress_Date_Format $date
     *
     * @return mixed
     */
    public function getTimetableForDate($externalUserId, $userId, Campus $campus, Alaress_Date_Format $date)
    {
        return $this->getDayTimetable(
            $externalUserId,
            $userId,
            $campus,
            $this->getDayNumber(
                $campus,
                $date->getUnixTimeStamp()
            )
        );
    }

    /**
     * Gets the timetable for the given room.
     *
     * @param  array|string $roomCodes
     *
     * @return array
     */
    public function getRoomTimetable($roomCodes)
    {
        if (is_array($roomCodes)) {
            $result = [];
            foreach ($roomCodes as $roomCode) {
                $result = array_merge($result, $this->getRoomTimetable($roomCode));
            }

            return $result;
        }

        $statement = $this->internalDatabase->executeQuery(
            "
                SELECT      class_period.room AS RoomCode,
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
                FROM        class_period
                LEFT JOIN   user ON class_period.teacher_id = user.id AND user.deleted_at IS NULL
                INNER JOIN  timetable_period ON class_period.period_id = timetable_period.id
                INNER JOIN  timetable_campus ON timetable_period.cycle_id = timetable_campus.cycle_id
                INNER JOIN  luCampus ON timetable_campus.campus_id = luCampus.id
                INNER JOIN  class ON class_period.class_id = class.id
                WHERE       timetable_campus.start <= DATE(NOW()) AND
                            timetable_campus.finish >= DATE(NOW()) AND
                            class_period.room = :room
                ORDER BY    timetable_period.day ASC,
                            timetable_period.sequence ASC
            ",
            ['room' => $roomCodes]
        );

        return $statement->fetchAll();
    }
}
