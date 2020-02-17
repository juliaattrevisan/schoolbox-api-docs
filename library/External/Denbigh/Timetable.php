<?php
/**
 * @author    Anyone Foolish Enough To Claim It <everyone@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Campus as Campus;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/** @noinspection PhpUndefinedClassInspection */
class External_Denbigh_Timetable implements External_TimetableInterface
{
    use External_Schoolbox_TimetableDefinitionTrait;

    /** @var IConnector $internalDatabase */
    private $internalDatabase;

    /** @var IConnector $externalDatabase */
    private $externalDatabase;

    /** @var IConfigLoader $config */
    private $config;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /** @var array|bool[] $userCache */
    private $userCache = [];

    /** @var array|bool[] $userStaffCache */
    private $userStaffCache = [];

    /**
     * External_Denbigh_Class constructor.
     */
    public function __construct()
    {
        $this->userMapper = UserMapper::getInstance();
        $this->config     = ConfigLoader::getInstance();

        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();

        /** @noinspection PhpDeprecationInspection */
        $this->externalDatabase = DatabaseFactory::getInstanceExternal();
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
     */
    public function getPeriodNumber(Campus $campus, $now = 0, \Schoolbox\User $user = null): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getFullTimetable($externalUserId, $userId, Campus $campus): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $periodList = $this->retrieveBasicPeriodList($externalUserId, $campus);
        if (empty($periodList)) {
            return [];
        }

        if ($this->isStaff($userId)) {
            /** @noinspection SqlDialectInspection */
            $queryTimetable = $this->externalDatabase->prepareQuery(
                "
                    SELECT      l.day,
                                l.period,
                                startTime,
                                endTime,
                                l.classCode,
                                l.staffCode,
                                l.room,
                                co.subjectName
                    FROM        People p
                    RIGHT JOIN  Staff s ON s.peopleDenbighID = p.denbighID
                    RIGHT JOIN  Lessons l ON l.staffCode = s.staffCode OR l.staffCodeSecond = s.staffCode
                    RIGHT JOIN  Classes cl ON cl.classCode = l.classCode
                    RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                    WHERE       p.denbighID = :externalUserId
                "
            );
        } else {
            /** @noinspection SqlDialectInspection */
            $queryTimetable = $this->externalDatabase->prepareQuery(
                "
                    SELECT      l.day,
                                l.period,
                                startTime,
                                endTime,
                                l.classCode,
                                l.staffCode,
                                l.room,
                                co.subjectName
                    FROM        People p
                    RIGHT JOIN  Enrolments e ON e.studentID = p.denbighID
                    RIGHT JOIN  Lessons l ON l.classCode = e.classCode
                    RIGHT JOIN  Classes cl ON cl.classCode = l.classCode
                    RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                    WHERE       p.denbighID = :externalUserId
                "
            );
        }

        $lessonIterator = $queryTimetable->getIterator([
            'externalUserId' => $externalUserId,
        ]);

        foreach ($lessonIterator as $lesson) {
            $period = $lesson['period'];
            $day    = $lesson['day'];

            $periodList[$period]['periodStart'] =
                $lesson['startTime'] == ''
                    ? ''
                    : date($this->config->get('date_time'), strtotime($lesson['startTime']));

            $periodList[$period]['periodEnd'] =
                $lesson['endTime'] == ''
                    ? ''
                    : date($this->config->get('date_time'), strtotime($lesson['endTime']));

            if (isset($periodList[$period]['day'][$day]) &&
                !isset($periodList[$period]['day'][$day][$lesson['ClassCode']])) {
                $periodList[$period]['day'][$day][$lesson['ClassCode']] = [
                    'description' => $lesson['subjectName'],
                    'code'        => $lesson['classCode'],
                    'room'        => $lesson['room'],
                ];
            }
        }

        return $periodList;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimetableDefinition(?string $externalUserId, Campus $campus, $day = 0, $date = null): array
    {
        return $this->getTimetableDefinitionFromDatabase($this->internalDatabase, $campus);
    }

    /**
     * Check if specified user ID belongs to a staff member, cache result for repeat calls
     *
     * @param int $userId Schoolbox User ID
     *
     * @return bool
     */
    private function isStaff($userId)
    {
        try {
            if (!isset($this->userCache[$userId])) {
                $this->userStaffCache[$userId] = $this->userMapper->findById($userId)->getRole()->isStaff();
            }
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (MapperException $e) {
            $this->userStaffCache[$userId] = false;
        }

        return $this->userStaffCache[$userId];
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
        if (empty($externalUserId)) {
            return [];
        }

        $periodList = $this->retrieveBasicPeriodList($externalUserId, $campus);
        if (empty($periodList)) {
            return [];
        }

        if ($this->isStaff($userId)) {
            /** @noinspection SqlDialectInspection */
            $queryTimetable = $this->externalDatabase->prepareQuery(
                "
                    SELECT      l.day,
                                l.period,
                                startTime,
                                endTime,
                                l.classCode,
                                l.staffCode,
                                l.room,
                                co.subjectName
                    FROM        People p
                    RIGHT JOIN  Staff s ON s.peopleDenbighID = p.denbighID
                    RIGHT JOIN  Lessons l ON l.staffCode = s.staffCode OR l.staffCodeSecond = s.staffCode
                    RIGHT JOIN  Classes cl ON cl.classCode = l.classCode
                    RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                    WHERE       p.denbighID = :externalUserId AND
                                l.day = :dayNumber
                "
            );
        } else {
            /** @noinspection SqlDialectInspection */
            $queryTimetable = $this->externalDatabase->prepareQuery(
                "
                    SELECT      l.day,
                                l.period,
                                startTime,
                                endTime,
                                l.classCode,
                                l.staffCode,
                                l.room,
                                co.subjectName
                    FROM        People p
                    RIGHT JOIN  Enrolments e ON e.studentID = p.denbighID
                    RIGHT JOIN  Lessons l ON l.classCode = e.classCode
                    RIGHT JOIN  Classes cl ON cl.classCode = l.classCode
                    RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                    WHERE       p.denbighID = :externalUserId AND
                                l.day = :dayNumber
                "
            );
        }

        $lessonIterator = $queryTimetable->getIterator([
            'externalUserId' => $externalUserId,
            'dayNumber'      => $dayNumber,
        ]);

        foreach ($lessonIterator as $lesson) {
            $period = $lesson['period'];

            $periodList[$period]['periodStart'] =
                $lesson['startTime'] == ''
                    ? ''
                    : date($this->config->get('date_time'), strtotime($lesson['startTime']));

            $periodList[$period]['periodEnd'] =
                $lesson['endTime'] == ''
                    ? ''
                    : date($this->config->get('date_time'), strtotime($lesson['endTime']));

            if (isset($periodList[$period]) && !isset($periodList[$period]['class'][$lesson['ClassCode']])
            ) {
                $periodList[$period]['class'][$lesson['ClassCode']] = array_merge(
                    $periodList[$period],
                    [
                        'description' => $lesson['subjectName'],
                        'code'        => $lesson['classCode'],
                        'room'        => $lesson['room'],
                    ]
                );
            }
        }

        return $periodList;
    }

    /**
     * {@inheritDoc}
     * @param Campus     $campus
     * @param int|string $date
     *
     * @return bool|int
     */
    public function getDayNumber(Campus $campus, $date = 0)
    {
        return $this->getDayNumberFromDatabase($this->internalDatabase, $campus, $date);
    }

    /**
     * {@inheritDoc}
     */
    public function getRoomTimetable(array $roomCodes): array
    {
        if (empty($roomCodes)) {
            return [];
        }

        /** @noinspection SqlDialectInspection */
        $queryCampus = $this->internalDatabase->executeQuery(
            "
              SELECT    ext_timetable_code
              FROM      luCampus
              WHERE deleted_at IS NULL
              LIMIT     1
            "
        );

        if ($queryCampus->rowCount() == 0) {
            return [];
        }

        $timetableGroup = $queryCampus->fetchColumn();

        $predicate = PredicateBuilder::getArrayBindings($roomCodes, 'roomCode');

        /** @noinspection SqlDialectInspection */
        return $this->externalDatabase->executeQuery(
            "
                SELECT      l.day AS DayNumber,
                            l.period AS PeriodNumber,
                            startTime AS PeriodStart,
                            endTime AS PeriodEnd,
                            l.classCode AS ClassCode,
                            l.staffCode AS StaffID,
                            l.room AS RoomCode,
                            co.subjectName AS Description,
                            :timetableGroup AS TimetableGroup
                FROM        Lessons l
                RIGHT JOIN  Classes cl ON cl.classCode = l.classCode
                RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                WHERE       l.room IN ({$predicate["placeholders"]})
            ",
            array_merge(
                $predicate["bindings"],
                ['timetableGroup' => $timetableGroup]
            )
        )->fetchAll();
    }


    /**
     * Retrieve a basic list of periods, without any classes added.
     *
     * @param string|null $externalUserId
     * @param Campus      $campus
     * @return array
     */
    private function retrieveBasicPeriodList(?string $externalUserId, Campus $campus): array
    {
        $periodList = [];
        if (!$definition = $this->getTimetableDefinition($externalUserId, $campus)) {
            return $periodList;
        }

        // Need to get the actual period IDs from the list of lessons,
        // not just rely on the timetable definition.
        $periods = $this->externalDatabase->executeQuery(
            'SELECT DISTINCT period FROM Lessons l ORDER BY period'
        )->fetchAll();

        foreach ($periods as $row) {
            $period = $row['period'];
            $periodList[$period] = [
                'periodNumber'   => $period,
                'periodName'     => "Period $period",
                'periodStart'    => '',
                'periodEnd'      => '',
                'periodSequence' => '1',
                'day'            => array_fill(1, $definition['dayCount'], []),
                'class'          => [],
            ];
        }
        return $periodList;
    }
}
