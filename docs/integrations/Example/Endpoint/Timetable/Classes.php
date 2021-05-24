<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint\Timetable;

use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
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
 * Import all the classes and their involvements
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint\Timetable
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
     * @var Lookup
     */
    private $lookup;


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
     * TimetableClass constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * Import data from xml into local schema
     *
     * @throws RuntimeException
     * @throws Exception
     */
    public function import(): void
    {

        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         *
         * Cache a flat key-value array of (day, period)
         */
        $this->lookup->init();

        $users = $this->lookup->getUsers();
        $periods = $this->lookup->getPeriods();

        /* TODO Get a list of all the timetables to process
         */
        $timetables = [];

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No timetables detected.');
            return;
        }

        foreach ($timetables as $timetable) {
            /* TODO External Timetable Code (Cycle Identifier)
             */
            $ext = '';

            /* Find all the classes and their details as best we can
             *
             * Some parts will rely on data to have already been imported into local schema,
             * such as period ids, user ids, etc. so we try to get those and assemble them into
             * a convenient struct which we can then just ram home
             */
            /* TODO Create an Iterator which collates subject data
             */
            $classes = [
                /*
                    [
                        'period' => [
                            'day' => '',
                            'name' => '',
                            'sequence' => '',
                            'start' => '',
                            'end' => ''
                        ],
                        'class' => [
                            'code' => '',
                            'name' => ''
                        ],
                        'room' => [
                            'name' => ''
                        ],
                        'teachers' => [
                            [
                                'externalId' => '',
                                'firstname' => '',
                                'surname' => '',
                            ]
                        ],
                        'student' => [
                            [
                                'externalId' => '',
                                'firstname' => '',
                                'surname' => '',
                            ]
                        ]
                    ]
                */
            ];

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
                    if (!isset($users[$student['externalId']])) {
                        $this->log->warning(sprintf(
                            'Tried to assign nonexistent schoolbox profile %s to class %s %s; externalId might not exist in schoolbox user profile table',
                            $student['externalId'],
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
        return $periods['ext'][$ext]['day'][$day]['period'][$periodName] ?? null;
    }


    /**
     * Create a Timetable Class
     *
     * @param array $subject
     * @return ClassEntry
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClass(array $subject): ClassEntry
    {
        /* See if one exists already; if so, reuse it
         */
        try {
            $timetableClass = $this->classEntryMapper->findOneByColumn('code', $subject['class']['code']);

            $this->log->info(sprintf(
                'Reusing previously-saved class %s %s with id %s',
                $timetableClass->getName(),
                $timetableClass->getCode(),
                $timetableClass->getId()
            ));
        } catch (Exception $e) {
            /** @var ClassEntry $timetableClass */
            $timetableClass = $this->classEntryMapper->createModel()
                ->setCode($subject['class']['code'])
                ->setName($subject['class']['name'])
                ->setDescription($subject['class']['name']);

            $this->log->info(sprintf(
                'Creating new class %s %s',
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));
        }

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

        /* See if one exists already; if so, reuse it
         */
        try {
            $timetableClassPeriod = $this->classPeriodMapper->findOneByColumnList([
                'class_id' => $timetableClass->getId(),
                'period_id' => $period->getId()
            ]);

            $this->log->info(sprintf(
                'Reusing previously saved class period %s using period_id %s, with id %s',
                $timetableClass->getName(),
                $period->getId(),
                $timetableClassPeriod->getId()
            ));
        } catch (Exception $e) {
            $timetableClassPeriod = $this->classPeriodMapper->createModel()
                ->setClass($timetableClass)
                ->setPeriod($period)
                ->setRoom($class['room']['name']);

            $this->log->info(sprintf(
                'Creating new class period %s %s',
                $timetableClass->getName(),
                $period->getId()
            ));
        }

        /* Throw warning, but assign them anyway
         */
        if (!isset($users[$class['teachers']['externalId']])) {
            $this->log->warning(sprintf(
                'Tried to assign nonexistent schoolbox profile %s as teacher to class %s %s; externalId might not exist in schoolbox user profile table',
                $class['teachers']['externalId'],
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));
        }

        $timetableClassPeriod->setTeacher($class['teachers']['externalId']);

        $this->log->info(sprintf(
            'Creating class period relation for teacher %s %s in room %s',
            $class['teachers']['firstname'],
            $class['teachers']['surname'],
            $class['room']['name']
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
     * @param array      $person
     * @param ClassEntry $timetableClass
     * @return ClassInvolvement
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClassInvolvement(array $person, ClassEntry $timetableClass): ClassInvolvement
    {
        $involvement = $this->classInvolvementMapper->findByColumnList([
            'external_id' => $person['externalId'],
            'class_id' => $timetableClass->getId()
        ]);

        /* Relation already exists; nothing to change
         */
        if ($involvement->count()) {
            $this->log->info(sprintf(
                'Reusing class involvement %s %s for %s (user %s)',
                $timetableClass->getName(),
                $timetableClass->getCode(),
                "{$person['firstname']} {$person['surname']}",
                $person['externalId']
            ));

            return $involvement->first();
        }

        /* Create new relation
         */
        $timetableClassInvolvement = $this->classInvolvementMapper->createModel()
                                                                  ->setExternalId($person['externalId'])
                                                                  ->setClass($timetableClass);

        $this->log->info(sprintf(
            'Creating class involvement %s %s for %s (user %s)',
            $timetableClass->getName(),
            $timetableClass->getCode(),
            "{$person['firstname']} {$person['surname']}",
            $timetableClassInvolvement->getExternalId()
        ));

        if ($this->perform) {
            $this->classInvolvementMapper->save($timetableClassInvolvement);
        }

        return $timetableClassInvolvement;
    }
}
