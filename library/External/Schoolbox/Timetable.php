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
use Schoolbox\External\Profile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\Mapper;
use Schoolbox\User;

/**
 * Class External_Schoolbox_Timetable
 */
class External_Schoolbox_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;


    /**
     * @var IConnector $internalDatabase
     */
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
        $timetableDef = [
            'desc'        => [],
            'start'       => [],
            'end'         => [],
            'number'      => [],
            'sequence'    => [],
            'periodCount' => 0,
            'dayCount'    => 0,
        ];

        $timetableDefinition = $this->getTimetableDefinitionFromDatabase($this->internalDatabase, $campus);

        if (!$timetableDefinition) {
            return $timetableDef;
        }

        if ($day == 0) {
            $day = 1;
        }

        $periodList = $this->getPeriodList($campus, $day);

        $periodCount = 0;

        foreach ($periodList as $period) {
            $timetableDef['desc'][]     = $period['title'];
            $timetableDef['start'][]    = date("g:ia", strtotime($period['start']));
            $timetableDef['end'][]      = date("g:ia", strtotime($period['finish']));
            $timetableDef['number'][]   = ++$periodCount;
            $timetableDef['sequence'][] = $period['sequence'];
        }

        $timetableDef['periodCount'] = $periodCount;
        $timetableDef['dayCount']    = $timetableDefinition['cycle_length'];

        return $timetableDef;
    }


    /**
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
            FROM          sis_timetable_cycle ty
            LEFT JOIN     sis_timetable_period tp ON tp.cycle_id = ty.id
            WHERE         ty.cycle_identifier = :campusId
        ';

        if ($day > 0) {
            $query .= 'AND tp.day = :day ';
        }

        $query .= 'ORDER BY tp.day, tp.sequence';

        if ($day > 0) {
            return $this->internalDatabase->executeQuery(
                $query,
                [
                    'campusId' => $campus->getExtTimetableCode(),
                    'day'      => $day,
                ]
            )->fetchAll();
        }

        return $this->internalDatabase->executeQuery(
            $query,
            [
                'campusId' => $campus->getExtTimetableCode(),
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
                FROM        sis_timetable_cycle ty
                LEFT JOIN   sis_timetable_period tp ON tp.cycle_id = ty.id
                WHERE       ty.cycle_identifier = :campusId
                AND         :now BETWEEN tp.start AND tp.finish
                AND         tp.day = :dayNumber
            ',
            [
                'campusId'  => $campus->getExtTimetableCode(),
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
     * @param Campus     $campus
     * @param int|string $date
     *
     * @return bool|int
     * @throws InvalidArgumentException
     * @throws \Alaress\Database\Exception
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
        if (!$timetableDefinition) {
            return [];
        }
        $dayCount = $timetableDefinition['cycle_length'];

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

            if (isset($classList[$period['id']])) {
                foreach ($classList[$period['id']] as $class) {
                    $periodListAdaption[$periodSequence]['day'][$dayNumber][$class['code']] = [
                        'description'    => $class['name'],
                        'code'           => $class['code'],
                        'campus'         => $campus->getExtTimetableCode(),
                        'room'           => $class['room'],
                        'staffID'        => $class['synergy_id'],
                        'staffTitle'     => $class['title'],
                        'staffFirstname' => $class['firstname'],
                        'staffSurname'   => $class['lastname'],
                    ];
                }
            }
        }

        return $periodListAdaption;
    }


    /**
     * @param string $externalId
     *
     * @return array
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws \Alaress\Database\Exception
     */
    protected function getClassUsers(string $externalId): array
    {
        /* Find out if $externalId is a staff or a student
          * Parents never have a timetable
          *
          * There are more students than staff, so check if $externalId is a student first
          */
        try {
            Profile\Student\Mapper::getInstance()->findByExternalId($externalId);

            return $this->getClassUsersForStudent($externalId);
        } catch (Mapper\Exception\NotFound $e) {
            try {
                Profile\Staff\Mapper::getInstance()->findByExternalId($externalId);

                return $this->getClassUsersForStaff($externalId);
            } catch (Mapper\Exception\NotFound $e) {
                /* Neither student nor staff record found
                 */
                return [];
            }
        }
    }


    /**
     * Student users' timetable data
     *
     * Students are always involved, and are never teaching a class of their own
     *
     * @param string $externalId
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function getClassUsersForStudent(string $externalId): array
    {
        $query = '
            -- Regular class involvements
            SELECT
                c.*,
                cp.period_id AS periodId,
                cp.room,
                u.synergy_id,
                u.title,
                u.firstname,
                u.lastname
            FROM        sis_class c
            INNER JOIN  sis_class_user cu ON c.id = cu.class_id AND cu.external_id = :userId
            INNER JOIN  sis_class_period cp ON cp.class_id = c.id
            LEFT JOIN   user u ON cp.teacher_external_id = u.synergy_id
        ';

        $statement = $this->internalDatabase->prepareQuery($query);

        $statement->execute([
            'userId'  => $externalId,
        ]);

        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $index = $row['periodId'];

            if (!isset($stack[$index])) {
                $stack[$index] = [];
            }

            $stack[$index][] = $row;
        }

        return $stack;
    }


    /**
     * Staff users' timetable data
     *
     * When we import data we pile everyone involved in a class into sis_class_user
     * Due to unique class constraints sometimes teachers end up involved in other teachers'
     * classes when what actually happens is they share the teaching responsibilities on
     * different (days, periods).
     *
     * In TASS we can't distinguish those circumstances trivially due to Additional Subjects
     * prepopulating class involvements in sis_class_user before an official teacher in a
     * (day, period) is assigned. This is because imports match involvements based on a class_id
     * since Additional Subjects doesn't tell you a specific period in which a person is involved
     *
     * @param string $externalId
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function getClassUsersForStaff(string $externalId): array
    {
        $query = "
            -- Where you are a class teacher
            (SELECT
                c.*,
                cp.period_id AS periodId,
                cp.room,
                u.synergy_id,
                u.title,
                u.firstname,
                u.lastname
            FROM        sis_class c
            INNER JOIN  sis_class_period cp ON c.id = cp.class_id AND cp.teacher_external_id = :externalId
            LEFT JOIN   user u ON cp.teacher_external_id = u.synergy_id)

            UNION

            -- Regular class involvements where the teacher_external_id is a meaningless value
            -- (null or empty string) but you are still involved in it
            (SELECT
                c.*,
                cp.period_id AS periodId,
                cp.room,
                NULL AS synergy_id,
                NULL AS title,
                NULL AS firstname,
                NULL AS lastname
            FROM        sis_class c
            INNER JOIN  sis_class_user cu ON c.id = cu.class_id AND cu.external_id = :externalId
            INNER JOIN  sis_class_period cp ON cp.class_id = c.id
            WHERE LENGTH(COALESCE(cp.teacher_external_id, '')) = 0)
        ";

        $statement = $this->internalDatabase->prepareQuery($query);

        $statement->execute([
            'externalId'  => $externalId,
        ]);

        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $index = $row['periodId'];

            if (!isset($stack[$index])) {
                $stack[$index] = [];
            }

            $stack[$index][] = $row;
        }

        return $stack;
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
    public function getDayTimetable(string $externalUserId, $userId, Campus $campus, $dayNumber, ?Alaress_Date_Format $date = null): array
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

            if (isset($classList[$period['id']])) {
                foreach ($classList[$period['id']] as $class) {
                    $periodAdaption['class'][$class['code']] = [
                        'description'    => $class['name'],
                        'code'           => $class['code'],
                        'campus'         => $campus->getExtTimetableCode(),
                        'room'           => $class['room'],
                        'staffID'        => $class['synergy_id'],
                        'staffTitle'     => $class['title'],
                        'staffFirstname' => $class['firstname'],
                        'staffLastname'  => $class['lastname'],
                    ];
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
                            sis_class.name AS Description,
                            sis_timetable_period.day AS DayNumber,
                            sis_timetable_period.sequence AS PeriodNumber,
                            luCampus.ext_timetable_code AS TimetableGroup,
                            luCampus.id AS TimetableCampus,
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
                INNER JOIN  sis_timetable_cycle ON sis_timetable_period.cycle_id = sis_timetable_cycle.id
                INNER JOIN  luCampus ON sis_timetable_cycle.cycle_identifier = luCampus.ext_timetable_code
                INNER JOIN  sis_class ON sis_class_period.class_id = sis_class.id
                WHERE       sis_timetable_cycle.start <= DATE(NOW()) AND
                            sis_timetable_cycle.finish >= DATE(NOW()) AND
                            sis_class_period.room IN ({$predicate["placeholders"]}) AND
                            luCampus.deleted_at IS NULL
                GROUP BY    RoomCode, TimetableGroup, DayNumber, PeriodNumber, PeriodStart, PeriodEnd, ClassCode
                ORDER BY    sis_timetable_period.day ASC,
                            sis_timetable_period.sequence ASC
            ",
            $predicate['bindings']
        );

        return $statement->fetchAll();
    }


    /**
     * @inheritDoc
     */
    public function findCycleLookup(): array
    {
        $cycles = TimetableCycleMapper::getInstance()->findAll();

        $luCycles = [];

        foreach ($cycles as $cycle) {
            if ('' !== (string)$cycle->getCycleIdentifier()) {
                $luCycles[] = [
                    'key'   => $cycle->getCycleIdentifier(),
                    'value' => $cycle->getTitle() . ' (' . $cycle->getCycleIdentifier() . ')'
                ];
            }
        }

        return $luCycles;
    }
}
