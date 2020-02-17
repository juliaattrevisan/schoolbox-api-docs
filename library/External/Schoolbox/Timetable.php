<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Campus;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\User;

/**
 * Class External_Schoolbox_Timetable
 */
class External_Schoolbox_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;


    /** @var IConnector $internalDatabase */
    private $internalDatabase;


    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }


    /**
     * {@inheritDoc}
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        $timetableDef = [];

        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->internalDatabase, $campus);

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
     * {@inheritDoc}
     * @param Campus $campus
     * @param int    $day
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getPeriodList(Campus $campus, $day): array
    {
        $query = '
            SELECT        tp.id,
                          tp.title,
                          tp.day,
                          tp.sequence,
                          tp.start,
                          tp.finish,
                          tp.usable
            FROM          sis_timetable_campus tc
            LEFT JOIN     sis_timetable_cycle ty ON ty.id = tc.cycle_id
            LEFT JOIN     sis_timetable_period tp ON tp.cycle_id = ty.id
            WHERE         tc.campus_id = :campusId AND
                          tc.start <= DATE(NOW()) AND
                          tc.finish >= DATE(NOW())
        ';

        if ($day > 0) {
            $query .= 'AND tp.day = :day ';
        }

        $query .= 'ORDER BY tp.day, tp.sequence';

        if ($day > 0) {
            return $this->internalDatabase->executeQuery(
                $query,
                [
                    'campusId' => $campus->getId(),
                    'day'      => $day,
                ]
            )->fetchAll();
        }

        return $this->internalDatabase->executeQuery(
            $query,
            [
                'campusId' => $campus->getId(),
            ]
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     * @param int $dayNumber
     *
     * @return string
     */
    public function getDayName($dayNumber): string
    {
        return "Day {$dayNumber}";
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getPeriodNumber(Campus $campus, $now = 0, User $user = null): array
    {
        $dayNumber = $this->getDayNumber($campus, $now);
        $time      = $now
            ? Alaress_Date_Format::newFromTimestamp($now)
            : Alaress_Date_Format::now();

        $periodStatement = $this->internalDatabase->executeQuery(
            '
                SELECT      tp.id,
                            tp.title,
                            tp.day,
                            tp.sequence,
                            tp.start,
                            tp.finish,
                            tp.usable
                FROM        sis_timetable_campus tc
                LEFT JOIN   sis_timetable_cycle ty ON ty.id = tc.cycle_id
                LEFT JOIN   sis_timetable_period tp ON tp.cycle_id = ty.id
                WHERE       tc.campus_id = :campusId
                AND         tc.start <= DATE(NOW())
                AND         tc.finish >= DATE(NOW())
                AND         :now BETWEEN tp.start AND tp.finish
                AND         tp.day = :dayNumber
            ',
            [
                'campusId'  => $campus->getId(),
                'now'       => $time->getUsingFormat(Alaress_Date_Format::DATABASE_TIME_FORMAT),
                'dayNumber' => $dayNumber,
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
     * {@inheritDoc}
     * @param Campus $campus
     * @param int|string    $date
     *
     * @return bool|int
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return $this->getDayNumberFromDatabase(
            $this->internalDatabase,
            $campus,
            $date
        );
    }


    /**
     * {@inheritDoc}
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $periodList = $this->getPeriodList($campus, 0);
        $classList  = $this->getClassUsers($externalUserId);

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
     * {@inheritDoc}
     * @param string $externalId
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    protected function getClassUsers(string $externalId): array
    {
        // Get classes for which this user is involved, or is a teacher.
        $query = '
            SELECT      c.name,
                        c.code,
                        c.description,
                        cp.period_id,
                        cp.room,
                        cp.teacher_external_id,
                        u.synergy_id,
                        u.title,
                        u.firstname,
                        u.lastname
            FROM        sis_class c
            LEFT JOIN   sis_class_user cl ON c.id = cl.class_id
            LEFT JOIN   sis_class_period cp ON c.id = cp.class_id
            LEFT JOIN   user u ON cp.teacher_external_id = u.synergy_id
            WHERE       cp.teacher_external_id = :userId OR
                        cl.external_id = :userId2
        ';

        return $this->internalDatabase->executeQuery($query, [
            'userId'  => $externalId,
            'userId2' => $externalId,
        ])->fetchAll();
    }


    /**
     * {@inheritDoc}
     */
    public function getTimetableForDate(string $externalUserId, $userId, Campus $campus, Alaress_Date_Format $date): array
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
     * {@inheritDoc}
     */
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber): array
    {
        if (empty($externalUserId) || empty($dayNumber)) {
            return [];
        }

        $periodList = $this->getPeriodList($campus, $dayNumber);
        $classList  = $this->getClassUsers($externalUserId);

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
     * {@inheritDoc}
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        if (empty($roomCodes)) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($roomCodes, 'roomCode');

        $statement = $this->internalDatabase->executeQuery(
            "
                SELECT      sis_class_period.room AS RoomCode,
                            sis_class.description AS Description,
                            sis_timetable_period.day AS DayNumber,
                            sis_timetable_period.sequence AS PeriodNumber,
                            luCampus.ext_timetable_code AS TimetableGroup,
                            sis_timetable_campus.campus_id AS TimetableCampus,
                            sis_class.code AS ClassCode,
                            user.synergy_id AS StaffID,
                            user.title AS StaffTitle,
                            user.firstname AS StaffPreferredName,
                            user.lastname AS StaffSurname,
                            sis_timetable_period.start AS PeriodStart,
                            sis_timetable_period.finish AS PeriodEnd,
                            sis_timetable_period.title AS PeriodName
                FROM        sis_class_period
                LEFT JOIN   user ON sis_class_period.teacher_external_id = user.synergy_id AND user.deleted_at IS NULL
                INNER JOIN  sis_timetable_period ON sis_class_period.period_id = sis_timetable_period.id
                INNER JOIN  sis_timetable_campus ON sis_timetable_period.cycle_id = sis_timetable_campus.cycle_id
                INNER JOIN  luCampus ON sis_timetable_campus.campus_id = luCampus.id
                INNER JOIN  sis_class ON sis_class_period.class_id = sis_class.id
                WHERE       sis_timetable_campus.start <= DATE(NOW()) AND
                            sis_timetable_campus.finish >= DATE(NOW()) AND
                            sis_class_period.room IN ({$predicate["placeholders"]}) AND
                            luCampus.deleted_at IS NULL
                ORDER BY    sis_timetable_period.day ASC,
                            sis_timetable_period.sequence ASC
            ",
            $predicate['bindings']
        );

        return $statement->fetchAll();
    }
}
