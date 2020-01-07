<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable;

use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Classes
 *
 * Import all the classes and their involvements for all the timetables in the xml
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Timetable
 */
class Classes implements IStrategy
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
     *
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

        /* TODO: synergy_id happens to match externalId? Maybe this array key is wrong
         * TODO: Found while building kamar
         */
        foreach ($userStatement as $row) {
            $users[$row['synergy_id']] = $row['id'];
        }

        return $users;
    }


    /**
     * Get a list of (day, period) tuples
     *
     * @return array
     * @throws Exception
     */
    private function initPeriodLookup(): array
    {
        $periodStatement = $this->timetablePeriodMapper->getDatabase()->query('
            SELECT
                luCampus.ext_timetable_code,
                period.day, period.id, period.title
            FROM luCampus
            LEFT JOIN sis_timetable_campus campus ON luCampus.id = campus.campus_id
            LEFT JOIN sis_timetable_cycle cycle ON campus.cycle_id = cycle.id
            LEFT JOIN sis_timetable_period period ON cycle.id = period.cycle_id

        ');

        $periods = [];

        foreach ($periodStatement as $row) {
            $ext = $row['ext_timetable_code'];
            $day = $row['day'];
            $periodName = $row['title'];

            /* Minimum period
             */
            $period = $this->timetablePeriodMapper->createModel()
                ->setId($row['id']);

            $periods['ext'][$ext]['day'][$day]['period'][$periodName] = $period;
        }

        return $periods;
    }


    /**
     * Find the period instance corresponding to $day, $periodName
     *
     * The timetable data in the feed may try to reference periods
     * that don't exist in Timetable Structure for some reason
     *
     * @param string $ext
     * @param array  $periods
     * @param string $day
     * @param string $periodName
     * @return TimetablePeriod|null
     */
    private function findPeriod(string $ext, array $periods, string $day, string $periodName): ?TimetablePeriod
    {
        if (isset($periods['ext'][$ext]['day'][$day]['period'][$periodName])) {
            return $periods['ext'][$ext]['day'][$day]['period'][$periodName];
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

        /* Get a list of all the timetables to process
         */
        $timetables = $this->batchxml->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No PublishedTimetables detected.');
            return;
        }

        foreach ($timetables as $timetable) {
            $ext = trim((string)$timetable->xpath('@Id')[0]->Id);

            $currentTimetable = $this->batchxml->fetchXml("/iSAMS/TimetableManager/PublishedTimetables/Timetable[@Id='{$ext}']")[0];

            /* Find all the classes and their details as best we can
             *
             * Some parts will rely on data to have already been imported into local schema,
             * such as period ids, user ids, etc. so we try to get those and assemble them into
             * a convenient struct which we can then just ram home
             */
            $classes = new ScheduleIterator($this->batchxml, $currentTimetable);

            foreach ($classes as $class) {

                /* Look up the period instance by (name, day)
                 *
                 * If the period doesn't correspond to something in our timetable definition,
                 * we can't process it anyway, so skip it
                 */
                $timetablePeriod = $this->findPeriod($ext, $periods, $class['period']['day'], $class['period']['name']);

                $this->log->info(sprintf(
                    'Looking for ext_timetable_code %s, day %s, period %s',
                    $ext,
                    $class['period']['day'],
                    $class['period']['name']
                ));

                if (null === $timetablePeriod) {
                    $this->log->warning(sprintf(
                        'The period named %s on day %s does not exist in timetable structure for ext_timetable_code %s',
                        $class['period']['name'],
                        $class['period']['day'],
                        $ext
                    ));

                    continue;
                }

                /* Assemble the pieces
                 */
                $timetableClass = $this->createTimetableClass($class);
                $this->createTimetableClassPeriod($users, $class, $ext, $timetablePeriod, $timetableClass);

                /* Process students
                 */
                foreach ($class['students'] as $student) {

                    /* Throw warning, but assign them anyway
                     */
                    if (!isset($users[$student['username']])) {
                        $this->log->warning(sprintf(
                            'Tried to assign nonexistent schoolbox account %s to class %s %s',
                            $student['username'],
                            $timetableClass->getName(),
                            $timetableClass->getCode()
                        ));
                    }

                    $this->createTimetableClassInvolvement($student, $timetableClass);
                }
            }
        }
    }


    /**
     * Create a Timetable Class
     *
     * @param array $class
     * @return ClassEntry
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClass(array $class): ClassEntry
    {
        /** @var ClassEntry $timetableClass */
        $timetableClass = $this->classEntryMapper->createModel()
            ->setCode($class['class']['code'])
            ->setName($class['class']['name'])
            ->setDescription($class['class']['name']);

        $this->log->info(sprintf(
            'Creating new class %s %s',
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
     * @param array           $users
     * @param array           $class
     * @param string          $ext
     * @param TimetablePeriod $period
     * @param ClassEntry      $timetableClass
     * @return ClassPeriod
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClassPeriod(array $users, array $class, string $ext, TimetablePeriod $period, ClassEntry $timetableClass): ClassPeriod
    {
        /* create record for class_period - should have all the data from $class iterator output
         * so you can look up individual pieces of the puzzle
         *
         * - room comes from $class['period']['room']['name']
         * - teacherExternalIdId is the staff's external_id
         * - period_id is a schoolbox sis_timetable_period via lookup of Day and Period Name
         * - class_id comes from $timetableClass->getId()
         */

        /** @var ClassPeriod $timetableClassPeriod */
        $timetableClassPeriod = $this->classPeriodMapper->createModel()
            ->setClass($timetableClass)
            ->setPeriod($period)
            ->setRoom($class['period']['room']['name']);

        /* Throw warning, but assign them anyway
         */
        if (!isset($users[$class['teachers']['username']])) {
            $this->log->warning(sprintf(
                'Tried to assign nonexistent schoolbox account %s as teacher to class %s %s',
                $class['teachers']['username'],
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));
        }

        $timetableClassPeriod->setTeacher($class['teachers']['username']);

        $this->log->info(sprintf(
            'Creating class period relation for teacher %s %s in room %s',
            $class['teachers']['firstname'],
            $class['teachers']['surname'],
            $class['period']['room']['name']
        ));

        $this->log->info(sprintf(
            'This occurs on Day %s (sequence %s) %s-%s in campus with ext_timetable_code %s',
            $class['period']['day'],
            $class['period']['sequence'],
            $class['period']['start'],
            $class['period']['end'],
            $ext
        ));

        if ($this->perform) {
            $this->classPeriodMapper->save($timetableClassPeriod);
        }

        return $timetableClassPeriod;
    }


    /**
     * Create a Timetable Class Involvement
     *
     * @param array      $student
     * @param ClassEntry $timetableClass
     * @return ClassInvolvement
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClassInvolvement(array $student, ClassEntry $timetableClass): ClassInvolvement
    {

        /** @var ClassInvolvement $timetableClassInvolvement */
        $timetableClassInvolvement = $this->classInvolvementMapper->createModel()
            ->setExternalId($student['username'])
            ->setClass($timetableClass);

        $this->log->info(sprintf(
            'Creating class involvement %s %s for student %s (user %s)',
            $timetableClass->getName(),
            $timetableClass->getCode(),
            $student['firstname'] . ' ' . $student['surname'],
            $timetableClassInvolvement->getExternalId()
        ));

        if ($this->perform) {
            $this->classInvolvementMapper->save($timetableClassInvolvement);
        }

        return $timetableClassInvolvement;
    }
}
