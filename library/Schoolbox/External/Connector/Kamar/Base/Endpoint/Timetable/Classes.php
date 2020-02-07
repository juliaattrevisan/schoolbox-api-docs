<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Assessment\Term;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriod;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile\Registry;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\Mapper\Exception;
use SimpleXMLElement;
use UnexpectedValueException;

/**
 * Class Classes
 *
 * Import all the classes and their involvements for all the timetables in the xml
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
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
     * @var Registry
     */
    private $registry;


    /**
     * @var Batchxml
     */
    private $timetableBatchXml;


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
     * @var Term\Mapper
     */
    private $termMapper;


    /**
     * @var Lookup
     */
    private $lookup;


    /**
     * TimetableClass constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Registry               $registry
     * @param Batchxml               $timetableBatchXml
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param Term\Mapper            $termMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Registry $registry,
        Batchxml $timetableBatchXml,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        Term\Mapper $termMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->registry = $registry;
        $this->timetableBatchXml = $timetableBatchXml;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->termMapper = $termMapper;
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
     * @param string|null $periodName
     * @return TimetablePeriod|null
     */
    private function findPeriod(string $ext, array $periods, string $day, ?string $periodName): ?TimetablePeriod
    {
        return $periods['ext'][$ext]['day'][$day]['period'][$periodName] ?? null;
    }


    /**
     * Determine which week we're in, if possible
     * Returns null when it can't be determined
     *
     * @param int $year
     * @return int|null
     * @throws Exception
     * @throws PDOException
     */
    private function findWeekOffset(int $year): ?int
    {
        $terms = $this->termMapper->findForYear($year);

        if ($terms->count()) {
            $term = $terms->first();
            $now = \Alaress_Date_Format::now();

            $startWeek = (int)date('W', $term->getStart()->getUnixTimestamp());
            $currentWeek = (int)date('W', $now->getUnixTimestamp());

            /* Include the current week in calculations using a +1 offset
             * e.g. if term begins on 8/1/20,
             *     8/1/20, 9/1/20 and 10/1/20 are in week 1
             *     11/1/20 is in week 2
             *     17/1/20 is in week 2
             *     21/2/20 is in week 3
             */
            $offset = $currentWeek - $startWeek + 1;

            if ($offset > 0) {
                return $offset;
            }
        }

        return null;
    }


    /**
     * Find the current week node for a given $studentId
     *
     * @param SimpleXMLElement $timetable
     * @param int              $weekOffset
     * @return SimpleXMLElement
     */
    private function findCurrentCycle(SimpleXMLElement $timetable, int $weekOffset): SimpleXMLElement
    {
        return $timetable->xpath("week[@index='{$weekOffset}']")[0];
    }


    /**
     * Import data from xml into local schema
     *
     * @throws RuntimeException
     * @throws Exception
     */
    public function import(): void
    {
        /* TODO: Potentially configurable
         * You could potentially make this configurable, giving the school an opportunity
         * to view an arbitrary weekOffset's data (maybe repurpose a config field?)
         */
        $currentWeekOffset = $this->findWeekOffset((int)date('Y'));

        /* Can't determine which week we're in; can't import anything
         */
        if (null === $currentWeekOffset) {
            $this->log->warning('Cannot determine week index for timetable import.');

            return;
        }

        $this->log->info(sprintf(
            'Importing timetables using week offset %s',
            $currentWeekOffset
        ));

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
        $timetables = $this->timetableBatchXml->fetchXml('/SMSDirectoryData/timetables/timetable');

        /* Remember the one and only campus identifier
         * TODO: Unify this with usage in Profile.php
         */
        $ext = (string)$this->timetableBatchXml->fetchXml('/SMSDirectoryData/schools/school')[0]->xpath('@index')[0];

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No timetables detected.');
            return;
        }

        foreach ($timetables as $timetable) {
            $studentId = trim((string)$timetable->xpath('@student')[0]);

            /* Find all the classes and their details as best we can
             *
             * Some parts will rely on data to have already been imported into local schema,
             * such as period ids, user ids, etc. so we try to get those and assemble them into
             * a convenient struct which we can then just ram home
             */
            $cycle = $this->findCurrentCycle($timetable, $currentWeekOffset);

            foreach ($cycle->xpath('days/day') as $day) {
                $dayNum = trim((string)$day->xpath('@index')[0]);

                $subjects = new SubjectIterator($this->log, $this->registry, $studentId, $day);

                foreach ($subjects as $subject) {

                    /* Subject doesn't exist in this slot
                     */
                    if (null === $subject) {
                        continue;
                    }

                    /* Look up the period instance by (name, day)
                     *
                     * If the period doesn't correspond to something in our timetable definition,
                     * we can't process it anyway, so skip it
                     */
                    $timetablePeriod = $this->findPeriod($ext, $periods, $dayNum, $subject['period']);

                    if (null === $timetablePeriod) {
                        continue;
                    }

                    $this->log->info(sprintf(
                        'Looking for ext_timetable_code %s, week %s, day %s, period %s',
                        $ext,
                        $currentWeekOffset,
                        $dayNum,
                        $subject['period']
                    ));

                    /* Assemble the pieces
                     */
                    try {
                        $timetableClass = $this->createTimetableClass($subject);
                    } catch(UnexpectedValueException $e) {
                        $this->log->info(sprintf(
                            'No classes found for student %s, day %s, period %s',
                            $studentId,
                            $dayNum,
                            $subject['period']
                        ));

                        continue;
                    }

                    $this->createTimetableClassPeriod($users, $subject, $timetablePeriod, $timetableClass);

                    /* Process student
                     *
                     * kamar sends each student's timetable individually so there's only one student
                     * per timetable class instance
                     */
                    $student = $subject['student'];

                    /* Throw warning, but assign them anyway
                     */
                    if (null === $student) {
                        $this->log->warning(sprintf(
                            'No student data for %s %s',
                            $timetableClass->getName(),
                            $timetableClass->getCode()
                        ));
                    } elseif (!isset($users[$student['username']])) {
                        $this->log->warning(sprintf(
                            'Tried to assign nonexistent schoolbox account %s (%s) to class %s %s; username might not exist in schoolbox user table',
                            $student['username'],
                            $student['externalId'],
                            $timetableClass->getName(),
                            $timetableClass->getCode()
                        ));
                    } else {
                        $this->createTimetableClassInvolvement($student, $timetableClass);
                    }

                    /* Process additional teachers (we already dealt with the first one)
                     */
                    array_shift($subject['teachers']);

                    foreach ($subject['teachers'] as $teacher) {

                        /* Throw warning, but assign them anyway
                         */
                        if (null === $teacher || !isset($users[$teacher['username']])) {
                            $this->log->warning(sprintf(
                                'Tried to assign nonexistent schoolbox account %s (%s) to class %s %s; username might not exist in schoolbox user table',
                                $teacher['username'],
                                $teacher['externalId'],
                                $timetableClass->getName(),
                                $timetableClass->getCode()
                            ));
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

        if ($this->perform) {
            $this->classEntryMapper->save($timetableClass);
        }

        return $timetableClass;
    }


    /**
     * Create a Timetable Class Period
     *
     * @param array           $users
     * @param array           $subject
     * @param TimetablePeriod $period
     * @param ClassEntry      $timetableClass
     * @return ClassPeriod
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createTimetableClassPeriod(array $users, array $subject, TimetablePeriod $period, ClassEntry $timetableClass): ClassPeriod
    {
        /* create record for class_period - should have all the data from $class iterator output
         * so you can look up individual pieces of the puzzle
         *
         * - room comes from $subject['room']
         * - teacherExternalIdId is the staff's external_id
         * - period_id is a schoolbox sis_timetable_period via lookup of Day and Period Name
         * - class_id comes from $timetableClass->getId()
         */

        /** @var ClassPeriod $timetableClassPeriod */
        $timetableClassPeriod = $this->classPeriodMapper->createModel()
            ->setClass($timetableClass)
            ->setPeriod($period)
            ->setRoom($subject['room']['name']);

        /* Throw warning, but assign them anyway
         */
        if (\count($subject['teachers'])) {
            if (!isset($users[$subject['teachers'][0]['username']])) {
                $this->log->warning(sprintf(
                    'Tried to assign nonexistent schoolbox account %s (%s) as teacher to class %s %s',
                    $subject['teachers'][0]['username'],
                    $subject['teachers'][0]['externalId'],
                    $timetableClass->getName(),
                    $timetableClass->getCode()
                ));
            }

            $timetableClassPeriod->setTeacher($subject['teachers'][0]['externalId']);

            $this->log->info(sprintf(
                'Creating class period relation for teacher %s %s in room %s',
                $subject['teachers'][0]['firstname'],
                $subject['teachers'][0]['surname'],
                $subject['room']['name']
            ));
        } else {
            $this->log->warning(sprintf(
                'No teachers found for class %s %s',
                $timetableClass->getName(),
                $timetableClass->getCode()
            ));
        }

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
        /** @var ClassInvolvement $timetableClassInvolvement */
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
