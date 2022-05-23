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
                    /* For each Subject in a Period
                     */
                    foreach ($period['SUBJECTS'] as $subject) {
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

                        /* This is the current teacher of the subject
                         * Multiple teachers are coming through as comma-separated
                         *
                         * Apparently TCH_CODE might not be a string at all,
                         * so force it to be a string instead of relying on tass
                         */
                        $this->log->debug(sprintf(
                            'Inspecting teacher code %s for multiple teachers',
                            $subject['TCH_CODE']
                        ));

                        $allTeacherCodes = explode(',', (string)$subject['TCH_CODE']);

                        $teacherStack = [];

                        foreach ($allTeacherCodes as $currentTeacherCode) {
                            $teacherStack[] = [
                                'externalId' => $currentTeacherCode,
                                'firstname' => $users[$currentTeacherCode]['firstname'] ?? 'Unknown',
                                'surname' => $users[$currentTeacherCode]['lastname'] ?? "Unknown ({$currentTeacherCode})",
                            ];
                        }

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

                            /* Always assign $subject['TCH_CODE'] to be the teacher of a class
                             *
                             * But what happens when staff are in classes taught by other staff...?
                             * Is that even possible?
                             */
                            'teachers' => $teacherStack,

                            'students' => [
                            ],
                        ];

                        /* If the current person is a student, put them in the students list
                         */
                        if (self::TYPE_STUDENT === $type) {
                            $class['students'][] = [
                                'externalId' => (string)$externalId,
                                'firstname' => $users[$externalId]['firstname'] ?? 'Unknown',
                                'surname' => $users[$externalId]['lastname'] ?? "Unknown ({$externalId})",
                            ];
                        }

                        /* noop for Parents (they don't have timetables)
                         */

                        /* Look up the period instance by (name, day)
                         *
                         * If the period doesn't correspond to something in our timetable definition,
                         * we can't process it anyway, so skip it
                         */
                        $timetablePeriod = $this->findPeriod($ext, $periods, $class['period']['day'], (string)$class['period']['name']);

                        $this->log->debug(sprintf(
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

                        /* Teacher involvement is assigned here, via multiple ClassEntry records
                         */
                        $this->createTimetableClassPeriod($class, $ext, $timetablePeriod, $timetableClass);

                        /* Process students
                         */
                        foreach ($class['students'] as $student) {
                            /* Throw warning, but assign them anyway
                             *
                             * $class['students'] can contain staff external ids when you have multiple
                             * teachers involved in a class so you need to check both lookups before
                             * declaring an externalId potentially missing
                             */
                            if (!isset($this->lookup->getStaff()[$student['externalId']]) && !isset($this->lookup->getStudents()[$student['externalId']])) {
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
                'Reusing previously-saved class %s (%s) with id %s',
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
                'Creating new class %s (%s)',
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));

            /* Only save this model if you're creating it anew
             */
            if ($this->perform) {
                $this->classEntryMapper->save($timetableClass);
            }
        }

        return $timetableClass;
    }


    /**
     * Create a Timetable Class Period
     *
     * @param array           $class
     * @param string          $ext
     * @param TimetablePeriod $period
     * @param ClassEntry      $timetableClass
     * @return void
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClassPeriod(array $class, string $ext, TimetablePeriod $period, ClassEntry $timetableClass): void
    {
        /* create record for class_period - should have all the data from $class iterator output
         * so you can look up individual pieces of the puzzle
         *
         * - room comes from $class['period']['room']['name']
         * - teacherExternalIdId is the staff's external_id
         * - period_id is a schoolbox sis_timetable_period via lookup of Day and Period Name
         * - class_id comes from $timetableClass->getId()
         */

        /* Process multiple teachers into multiple timetableClassPeriod instances
         */
        if (\count($class['teachers'])) {
            foreach ($class['teachers'] as $teacher) {
                /* See if a ClassPeriod exists already for the given teacher; if so, reuse it
                 * Otherwise create a new one
                 */
                try {
                    $timetableClassPeriod = $this->classPeriodMapper->findOneByColumnList([
                        'class_id' => $timetableClass->getId(),
                        'period_id' => $period->getId(),
                        'teacher_external_id' => $teacher['externalId']
                    ]);

                    $this->log->debug(sprintf(
                        'Reusing previously saved class period %s using period_id %s, taught by %s, with id %s',
                        $timetableClass->getName(),
                        $period->getId(),
                        $teacher['externalId'],
                        $timetableClassPeriod->getId()
                    ));
                } catch (Exception $e) {
                    $timetableClassPeriod = $this->classPeriodMapper->createModel()
                        ->setClass($timetableClass)
                        ->setPeriod($period)
                        ->setRoom($class['room']['name'])
                        ->setTeacher($teacher['externalId']);

                    $this->log->info(sprintf(
                        'Creating new class period %s %s, taught by %s',
                        $timetableClass->getName(),
                        $period->getId(),
                        $teacher['externalId']
                    ));

                    /* Only save this model if you're creating it anew
                     */
                    if ($this->perform) {
                        $this->classPeriodMapper->save($timetableClassPeriod);
                    }
                }

                $this->log->debug(sprintf(
                    '%s %s (user %s) is a teacher of subject %s',
                    $teacher['firstname'],
                    $teacher['surname'],
                    $teacher['externalId'],
                    $class['class']['code']
                ));
            }
        } else {
            /* This class doesn't have a teacher associated with it
             * See if a ClassPeriod exists already for the given (class_id, period_id); if so, reuse the first
             * of its kind since multiple might now exist, due to multiple teachers
             *
             * Otherwise create a new one
             */
            $timetableClassPeriod = $this->classPeriodMapper->findByColumnList([
                'class_id' => $timetableClass->getId(),
                'period_id' => $period->getId(),
            ])->first();

            // check here to see whether the ->first() is the one without teachers

            if (null !== $timetableClassPeriod) {
                $this->log->debug(sprintf(
                    'Reusing previously saved class period %s using period_id %s, with id %s',
                    $timetableClass->getName(),
                    $period->getId(),
                    $timetableClassPeriod->getId()
                ));
            } else {
                $timetableClassPeriod = $this->classPeriodMapper->createModel()
                    ->setClass($timetableClass)
                    ->setPeriod($period)
                    ->setRoom($class['room']['name']);

                $this->log->info(sprintf(
                    'Creating new class period %s %s',
                    $timetableClass->getName(),
                    $period->getId()
                ));

                /* Only save this model if you're creating it anew
                 */
                if ($this->perform) {
                    $this->classPeriodMapper->save($timetableClassPeriod);
                }
            }
        }

        $this->log->info(sprintf(
            'This occurs on Day %s (sequence %s) %s-%s in campus with ext_timetable_code %s',
            $class['period']['day'],
            $class['period']['sequence'],
            $class['period']['start'],
            $class['period']['end'],
            $ext
        ));
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
