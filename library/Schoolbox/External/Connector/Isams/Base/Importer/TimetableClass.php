<?php
/**
 * TimetableClass
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

class TimetableClass
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * TimetableClass constructor.
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Batchxml               $batchxml
     * @param TimetablePeriodMapper  $timetablePeriodMapper
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param UserMapper             $userMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        UserMapper $userMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Get a list of (user_id, synergy_id) tuples
     *
     * @return array
     */
    private function initUserLookup(): array
    {
        $userStatement = $this->userMapper->getDatabase()->query("
            SELECT u.`id`, synergy_id
            FROM {$this->userMapper->getTable()} u
            INNER JOIN `groups` g ON u.`group_id` = g.id
            WHERE synergy_id IS NOT NULL
        ");

        $users = [];

        foreach ($userStatement as $row) {
            $users[$row['synergy_id']] = $row['id'];
        }

        return $users;
    }


    /**
     * Get a list of (day, period) tuples
     *
     * @return array
     */
    private function initPeriodLookup(): array
    {
        $periods = $this->timetablePeriodMapper->findAll();

        $stack = [];

        foreach ($periods as $period) {
            $stack['day'][$period->getDay()]['period'][$period->getTitle()] = $period;
        }

        return $stack;
    }


    /**
     * Find the period instance corresponding to $day, $periodName
     *
     * The timetable data in the feed may try to reference periods
     * that don't exist in Timetable Structure for some reason
     *
     * @param array  $periods
     * @param string $day
     * @param string $periodName
     * @return TimetablePeriod|null
     */
    private function findPeriod(array $periods, string $day, string $periodName): ?TimetablePeriod
    {
        if (isset($periods['day'][$day]['period'][$periodName])) {
            return $periods['day'][$day]['period'][$periodName];
        }

        return null;
    }


    /**
     * Import data from xml into local schema
     *
     * @throws \RuntimeException
     * @throws InvalidUriPartException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->classEntryMapper->getDatabase()->query("DELETE FROM {$this->classEntryMapper->getTable()}");
            $this->classInvolvementMapper->getDatabase()->query("DELETE FROM {$this->classInvolvementMapper->getTable()}");
            $this->classPeriodMapper->getDatabase()->query("DELETE FROM {$this->classPeriodMapper->getTable()}");
        }

        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         */
        $users = $this->initUserLookup();

        /* Cache a flat key-value array of (day, period)
         */
        $periods = $this->initPeriodLookup();

        /* Find all the classes and their details as best we can
         *
         * Some parts will rely on data to have already been imported into local schema,
         * such as period ids, user ids, etc. so we try to get those and assemble them into
         * a convenient struct which we can then just ram home
         *
         * TODO: There could be multiple PublishedTimetables/Timetable instances so we need an Iterator Iterator
         */
        $currentTimetable = $this->batchxml->fetchXml("/iSAMS/TimetableManager/PublishedTimetables/Timetable[@Id='14']")[0];

        $classes = new TimetableScheduleIterator($this->batchxml, $currentTimetable);

        foreach ($classes as $class) {

            /* Look up the period instance by (name, day)
             *
             * If the period doesn't correspond to something in our timetable definition,
             * we can't process it anyway, so skip it
             */
            $timetablePeriod = $this->findPeriod($periods, $class['period']['day'], $class['period']['name']);

            if (null === $timetablePeriod) {
                $this->log->warning(sprintf(
                    'The period named %s on day %s does not exist in timetable structure',
                    $class['period']['name'],
                    $class['period']['day']
                ));

                continue;
            }

            /* Assemble the pieces
             */
            $timetableClass = $this->createTimetableClass($class);
            $this->createTimetableClassPeriod($users, $class, $timetableClass);

            /* Process students
             */
            foreach ($class['students'] as $student) {

                /* If we have no record of this student id existing then we
                 * we can't assign them to any classes - they don't have a
                 * schoolbox user account
                 */
                if (!isset($users[$student['username']])) {
                    $this->log->warning(sprintf(
                        'Tried to assign nonexistent schoolbox account %s to class %s %s; skipping',
                        $student['username'],
                        $timetableClass->getName(),
                        $timetableClass->getCode()
                    ));

                    continue;
                }

                $this->createTimetableClassInvolvement($users, $student, $timetableClass);
            }
        }

        if (!$this->perform) {
            $this->log->info('Planning finished.');
        }
    }


    /**
     * Create a Timetable Class
     *
     * @param array $class
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createTimetableClass(array $class): ClassEntry
    {
        /** @var ClassEntry $timetableClass */
        $timetableClass = $this->classEntryMapper->createModel()
            ->setCode($class['class']['code'])
            ->setName($class['class']['name'])
            ->setDescription($class['class']['name']);

        $this->log->info(sprintf(
            'Creating class %s %s',
            $timetableClass->getName(),
            $timetableClass->getCode()
        ));

        if ($this->perform) {
            $this->classEntryMapper->save($timetableClass);
        }

        return $timetableClass;
    }


    /**
     * Create a Timetable Class Period
     *
     * @param array      $users
     * @param array      $class
     * @param ClassEntry $timetableClass
     * @return ClassPeriod
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createTimetableClassPeriod(array $users, array $class, ClassEntry $timetableClass): ClassPeriod
    {
        /* create record for class_period - should have all the data from $class iterator output
         * so you can look up individual pieces of the puzzle
         *
         * - room comes from $class['period']['room']['name']
         * - teacherId is a schoolbox user id via a lookup on their external_id
         * - period_id is a schoolbox timetable_period via lookup of Day and Period Name
         * - class_id comes from $timetableClass->getId()
         */

        /* If we have no record of this teacher id existing then we
         * we can't assign them to any classes - they don't have a
         * schoolbox user account
         */
        if (!isset($users[$class['teachers']['username']])) {
            $this->log->warning(sprintf(
                'Tried to assign nonexistent schoolbox account %s as teacher to class %s %s; skipping',
                $class['teachers']['username'],
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));
        }

        /* Minimum user
         */
        $teacher = $this->userMapper->createModel()
            ->setId($users[$class['teachers']['username']]);

        /** @var ClassPeriod $timetableClassPeriod */
        $timetableClassPeriod = $this->classPeriodMapper->createModel()
            ->setClass($timetableClass)
            ->setTeacher($teacher)
            ->setRoom($class['period']['room']['name']);

        $this->log->info(sprintf(
            'Creating class period relation for teacher %s %s in room %s, Day %s (sequence %s) %s-%s',
            $class['teachers']['firstname'],
            $class['teachers']['surname'],
            $class['period']['room']['name'],
            $class['period']['day'],
            $class['period']['sequence'],
            $class['period']['start'],
            $class['period']['end']
        ));

        if ($this->perform) {
            $this->classPeriodMapper->save($timetableClassPeriod);
        }

        return $timetableClassPeriod;
    }


    /**
     * Create a Timetable Class Involvement
     *
     * @param array      $users
     * @param array      $student
     * @param ClassEntry $timetableClass
     * @return ClassInvolvement
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createTimetableClassInvolvement(array $users, array $student, ClassEntry $timetableClass): ClassInvolvement
    {
        /* Minimum user
         */
        $user = $this->userMapper->createModel()
            ->setId($users[$student['username']]);

        /** @var ClassInvolvement $timetableClassInvolvement */
        $timetableClassInvolvement = $this->classInvolvementMapper->createModel()
            ->setUser($user)
            ->setClass($timetableClass);

        $this->log->info(sprintf(
            'Creating class involvement %s %s for student %s (user %s)',
            $timetableClass->getName(),
            $timetableClass->getCode(),
            $student['firstname'] . ' ' . $student['surname'],
            $timetableClassInvolvement->getUser()->getId()
        ));

        if ($this->perform) {
            $this->classInvolvementMapper->save($timetableClassInvolvement);
        }

        return $timetableClassInvolvement;
    }
}
