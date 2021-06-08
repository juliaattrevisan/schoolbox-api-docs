<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Classes
 *
 * Import all the classes and their involvements
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class Classes implements IStrategy
{

    /**
     * These consts help identify which User Lookup you used,
     * to prevent double-up of involvement records
     */
    private const TYPE_STAFF = 'staff';
    private const TYPE_STUDENT = 'student';


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
     * @var Registry
     */
    private Registry $timetableRegistry;


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
     * @param Lookup                 $lookup
     * @param Registry               $timetableRegistry
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Lookup $lookup,
        Registry $timetableRegistry,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lookup = $lookup;
        $this->timetableRegistry = $timetableRegistry;
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
     * Import data from api endpoints into local schema
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
        $periods = $this->lookup->getPeriods();

        $this->importTimetable($this->lookup->getStaff(), self::TYPE_STAFF, $periods);
        $this->importTimetable($this->lookup->getStudents(), self::TYPE_STUDENT, $periods);
    }


    /**
     * Import timetables that belong to $users array
     *
     * @param array  $users
     * @param string $type
     * @param array  $periods
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importTimetable(array $users, string $type, array $periods): void
    {

        /* Get a list of all the timetables to process
         * These are indexed by (externalId => timetable) tuple
         */
        $timetables = $this->timetableRegistry->recallAll();

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No timetables detected.');
            return;
        }

        foreach ($timetables as $externalId => $timetable) {
            /* External Timetable Code (Cycle Identifier)
             */
            $ext = (string)$timetable['TT_ID'];

            /* Find all the classes and their details as best we can
             *
             * Some parts will rely on data to have already been imported into local schema,
             * such as period ids, user ids, etc. so we try to get those and assemble them into
             * a convenient struct which we can then just ram home
             */

            /* For each Day in $timetable
             */
            foreach ($timetable['DAYS'] as $dayIndex => $day) {
                /* For each Period in Day
                 */
                foreach ($day['PERIODS'] as $periodIndex => $period) {
                    /* Take the 0th element of the Period's Subject's node
                     * (Presume a student can only be in one class on a given (day, period)
                     */
                    $subject = $period['SUBJECTS'][0];

                    /* TODO Shalom is the first usage of having multiple subjects in a day/period tuple
                     *  We might have to do this instead:
                     *  foreach($period['SUBJECTS'] as $subject) { ... }
                     */

                    /* If the FROM_DATE is not null and the TO_DATE is not null,
                     * check that the current time is between these two dates
                     * If we're outside these two dates, skip this subject
                     *
                     * FROM_DATE and TO_DATE might not even exist in the API payload
                     * for some mysterious reason, because TASS reasons I suppose
                     */
                    $fromDate = $subject['FROM_DATE'] ?? '';
                    $toDate = $subject['TO_DATE'] ?? '';

                    $isActive = (new ValidActiveEnrolment())->isSatisfiedBy($fromDate, $toDate);

                    if (!$isActive) {
                        $this->log->info(sprintf(
                            'Skipping class %s - we are outside the bounds of from_date %s and to_date %s',
                            $subject['SUB_CODE'],
                            $subject['FROM_DATE'],
                            $subject['TO_DATE']
                        ));

                        continue;
                    }

                    /* Tass doesn't use the date part,
                     * Tass includes a microseconds part (sigh)
                     * So we have to ditch these bits and swipe what we need
                     */
                    $start = substr($period['PRD_START'], 11, 8);
                    $finish = substr($period['PRD_END'], 11, 8);

                    /* Construct a data struct that represents a more sane version of $subject
                     */
                    $class = [
                        'period' => [
                            'day' => (string)$dayIndex,
                            'name' => $period['PRD_DESC'],
                            'sequence' => $periodIndex,
                            'start' => $start,
                            'end' => $finish
                        ],
                        'class' => [

                            /* This format mirrors the implementation in External/Tass/Timetable.php:239
                             */
                            'code' => (new Codec\Code())->encode(
                                '' !== (string)$subject['YEAR_GRP'] ? (string)$subject['YEAR_GRP'] : (string)$subject['SUBTAB_YEAR_GRP'],
                                (string)$subject['SUB_CODE'],
                                (string)$subject['CLASS']
                            ),
                            'name' => $subject['SUB_DESC'],
                        ],
                        'room' => [
                            'name' => $subject['ROOM_CODE']
                        ],

                        /* Assumption: Only one teacher per subject
                         * Can there be more than one teacher, maybe comma-separated?
                         */
                        'teachers' => [
                        ],

                        'students' => [
                        ],
                    ];

                    /* If this $externalId existed in the Staff lookup, put them in the teachers set
                     * Or, if this $externalId existed in the Student lookup, put them in the students set
                     */
                    if (isset($users[$externalId])) {
                        if (self::TYPE_STAFF === $type) {
                            $class['teachers'] = [
                                'externalId' => $subject['TCH_CODE'],
                                'firstname' => $users[$externalId]['firstname'] ?? 'Unknown',
                                'surname' => $users[$externalId]['lastname'] ?? "Unknown ({$externalId})",
                            ];

                            $this->log->debug(sprintf(
                                '%s %s (user %s) is the teacher of subject %s',
                                $class['teachers']['firstname'],
                                $class['teachers']['surname'],
                                $class['teachers']['externalId'],
                                $class['class']['code']
                            ));
                        } elseif (self::TYPE_STUDENT === $type) {
                            $class['students'][] = [
                                'externalId' => (string)$externalId,
                                'firstname' => $users[$externalId]['firstname'] ?? 'Unknown',
                                'surname' => $users[$externalId]['lastname'] ?? "Unknown ({$externalId})",
                            ];
                        }

                        /* noop for Parents (they don't have timetables)
                         */
                    }

                    /* Look up the period instance by (name, day)
                     *
                     * If the period doesn't correspond to something in our timetable definition,
                     * we can't process it anyway, so skip it
                     */
                    $timetablePeriod = $this->findPeriod($ext, $periods, $class['period']['day'], (string)$class['period']['name']);

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

        /* If this class had teachers, process them
         */
        if (\count($class['teachers'])) {
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
        }

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
