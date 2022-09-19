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
use RuntimeException;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;
use Laminas\Uri\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriPartException;

/**
 * Class Classes
 *
 * Import all the classes and their involvements for all the timetables in the xml
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer\Timetable
 */
class Classes implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var Batchxml
     */
    private Batchxml $batchxml;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private ClassPeriodMapper $classPeriodMapper;


    /**
     * @var Lookup
     */
    private Lookup $lookup;


    /**
     * TimetableClass constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Batchxml               $batchxml
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
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
     * Import data from xml into local schema
     *
     * @throws RuntimeException
     * @throws InvalidUriPartException
     * @throws InvalidArgumentException
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

        /* Get a list of all the timetables to process
         */
        $timetables = $this->batchxml->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No PublishedTimetables detected.');
            return;
        }

        /* Multiple cycles might exist
         * Apply $timetable structure to each as best we know
         */
        $cycles = $this->batchxml->fetchXml('/iSAMS/TimetableManager/Structure/Week');

        foreach ($cycles as $cycle) {
            /* Multiple cycles might exist for a given publishedTimetable
             * Each cycle should exist independently of other cycles
             *
             * This means we need to construct an $ext per cycle so that users
             * can assign them to their corresponding luCampus
             *
             * If you don't, you end up getting foreign key issues in the schema
             *
             * For an $ext, you just need to find something that's identifiable to humans
             * Try to find the first meaningful value out of ShortName, Name and Cycle Id
             * (not sure if ShortName is required in isams itself)
             */
            $ext = (new FirstMeaningfulValue())->find([
                trim((string)$cycle->ShortName),
                trim((string)$cycle->Name),
                trim((string)$cycle->xpath('@Id')[0]->Id)
            ]);

            /* Typically there's one published timetable at a time
             * so despite what this looks like, it should only happen once
             * (one publishedTimetable data is applied to all cycles)
             */
            foreach ($timetables as $timetable) {
                /* Find all the classes and their details as best we can
                 *
                 * Some parts will rely on data to have already been imported into local schema,
                 * such as period ids, user ids, etc. so we try to get those and assemble them into
                 * a convenient struct which we can then just ram home
                 */
                try {
                    $classes = new ScheduleIterator($this->batchxml, $timetable);
                } catch (\RuntimeException $e) {
                    /* This occurs when a PublishedTimetable exists, but there aren't any Schedules
                     * inside it, perhaps because there are no Sets. Nothing to process.
                     */
                    $this->log->warning(sprintf(
                        'No Schedules/Schedule nodes found for timetable %s; skipping',
                        $ext
                    ));

                    continue;
                }

                foreach ($classes as $class) {
                    /* Look up the period instance by (name, day)
                     *
                     * If the period doesn't correspond to something in our timetable definition,
                     * we can't process it anyway, so skip it
                     */
                    $timetablePeriod = $this->findPeriod($ext, $periods, (string)$class['period']['day'], $class['period']['name']);

                    $this->log->debug(sprintf(
                        'Looking for cycle_identifier %s, day %s, period %s',
                        $ext,
                        $class['period']['day'],
                        $class['period']['name']
                    ));

                    if (null === $timetablePeriod) {
                        $this->log->warning(sprintf(
                            'The period named %s on day %s does not exist in timetable structure for cycle_identifier %s',
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
                            $this->log->debug(sprintf(
                                'Tried to assign nonexistent schoolbox account %s to class %s %s; username might not exist in schoolbox user table',
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

            $this->log->debug(sprintf(
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

        /** @var ClassPeriod $timetableClassPeriod */
        $timetableClassPeriod = $this->classPeriodMapper->createModel()
            ->setClass($timetableClass)
            ->setPeriod($period)
            ->setRoom($class['period']['room']['name']);

        $timetableClassPeriod->setTeacher($class['teachers']['username']);

        if ('' !== $class['teachers']['username']) {
            $this->log->info(sprintf(
                'Creating class period relation for teacher %s %s (%s) in room %s on Day %s (sequence %s) %s-%s in campus with cycle_identifier %s',
                $class['teachers']['firstname'],
                $class['teachers']['surname'],
                $class['teachers']['username'],
                $class['period']['room']['name'],
                $class['period']['day'],
                $class['period']['sequence'],
                $class['period']['start'],
                $class['period']['end'],
                $ext
            ));
        } else {
            $this->log->warning(sprintf(
                'Creating class period relation with an unknown/unspecified teacher in room %s on Day %s (sequence %s) %s-%s in campus with cycle_identifier %s',
                $class['period']['room']['name'],
                $class['period']['day'],
                $class['period']['sequence'],
                $class['period']['start'],
                $class['period']['end'],
                $ext
            ));
        }

        $this->log->debug(sprintf(
            'Using timetableClass id %s, timetableClassPeriod id %s',
            $timetableClassPeriod->getClass()->getId(),
            $timetableClassPeriod->getPeriod()->getId()
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
            $this->log->debug(sprintf(
                'Reusing class involvement %s %s for %s (%s)',
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
            'Creating class involvement %s %s for %s (%s)',
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
