<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class AdditionalSubjects
 *
 * Adds additional non-timetabled subjects from the sis.
 *
 * These don't have any associated sis_class_period records;
 * it only has sis_class and sis_class_user records.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class AdditionalSubjects implements IStrategy
{

    /**
     * This endpoint contains a list of subjects that a student/staff owns
     * These subjects might not appear in the Timetable endpoints so
     * have to superimpose or add them specifically in this way
     */
    private const ENDPOINT_STUDENTSUBJECTS = 'getStudentSubjects';
    private const ENDPOINT_TEACHERSUBJECTS = 'getTeacherSubjects';


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
     * @var array
     */
    private array $api;


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
     * @param Api[]                  $api
     * @param Lookup                 $lookup
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * Import data from api endpoints into local schema
     */
    public function import(): void
    {
        $this->importStudentAdditionalSubjects();
        $this->importStaffAdditionalSubjects();
    }


    /**
     * Associate a student with additional subjects
     * Students are assigned specifically to $code
     *
     * @throws \DomainException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importStudentAdditionalSubjects(): void
    {
        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         *
         * Cache a flat key-value array of (day, period)
         */
        $users = $this->lookup->getStudents();
        $endpoint = self::ENDPOINT_STUDENTSUBJECTS;

        $involvements = $this->lookup->getInvolvements();

        foreach ($users as $externalId => $userData) {
            $data = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument($endpoint, [
                'code' => $externalId
            ]);

            /* No additional subjects
             */
            if (!isset($data[$externalId])) {
                continue;
            }

            /* Otherwise, add some more things that we didn't already have from
             * the student's timetable import
             */
            foreach ($data[$externalId] as $additionalSubject) {
                $code = (new Codec\Code())->encode(
                    (string)$additionalSubject['year_grp'],
                    (string)$additionalSubject['sub_code'],
                    (string)$additionalSubject['class']
                );

                /* If they're already involved, don't worry about it
                 *
                 * This hash lookup lets us skips a lot of mapper nonsense if we don't need to do anything
                 * since we don't need to ->find()
                 */
                if (isset($involvements[$externalId][$code])) {
                    continue;
                }

                /* Otherwise install them and move onwards
                 *
                 * First find out if the subject already exists; if so, use its existing record
                 * to create a new class involvement. If it doesn't, create the class, then
                 * create the involvement
                 */
                try {
                    $class = $this->classEntryMapper->findOneByColumn('code', $code);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $class = $this->classEntryMapper->createModel()
                        ->setName($additionalSubject['sub_long'])
                        ->setCode($code);

                    $this->classEntryMapper->save($class);

                    $this->log->info(sprintf(
                        "Additional subject %s (%s) added for %s (user %s)",
                        $additionalSubject['sub_long'],
                        $code,
                        trim("{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}"),
                        $externalId
                    ));
                }

                /* Find out if there's already an involvement, adding it if necessary
                 */
                try {
                    $classInvolvement = $this->classInvolvementMapper->findOneByColumnList([
                        'external_id' => $externalId,
                        'class_id'    => $class->getId()
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $classInvolvement = $this->classInvolvementMapper->createModel()
                        ->setExternalId((string)$externalId)
                        ->setClass($class);

                    $this->classInvolvementMapper->save($classInvolvement);

                    $this->log->info(sprintf(
                        "Additional subject involvement in %s (%s) added for %s (user %s)",
                        $additionalSubject['sub_long'],
                        $code,
                        trim("{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}"),
                        $externalId
                    ));
                }
            }
        }
    }

    /**
     * Associate a staff with additional subjects
     *
     * Staff are assigned to $code and also its non-unitised variant
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importStaffAdditionalSubjects(): void
    {
        /* Cache a flat key-value array of (user.id, user.synergy_id)
         * The alternative would be to iterate findById() in a loop which probably costs more at scale
         *
         * Cache a flat key-value array of (day, period)
         */
        $users = $this->lookup->getStaff();
        $endpoint = self::ENDPOINT_TEACHERSUBJECTS;

        foreach ($users as $externalId => $userData) {
            /* Allow continuation even if this dies irrecoverably
             */
            try {
                $data = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument($endpoint, [
                    'code' => (string)$externalId
                ]);
            } catch (\RuntimeException $e) {
                $this->log->warning($e->getMessage());

                continue;
            }

            /* No additional subjects
             */
            if (!isset($data[$externalId])) {
                continue;
            }

            /* Otherwise, add some more things that we didn't already have from
             * the student's timetable import
             */
            foreach ($data[$externalId] as $additionalSubject) {
                $code = (new Codec\Code())->encode(
                    (string)$additionalSubject['year_grp'],
                    (string)$additionalSubject['sub_code'],
                    (string)$additionalSubject['class']
                );

                /* Also try to find the unitised version of the $code in addition
                 *
                 * e.g. search for -1011-A (unitised) in addition to 10-1011-A (non-unitised)
                 * since a teacher is apparently associated with both possible outcomes;
                 * one or the other might exist first
                 */
                $pb = ($this->classEntryMapper->getPredicateBuilder())
                    ->like('code', "%{$code}");

                $classes = $this->classEntryMapper->findByPredicates($pb);

                if (!$classes->count()) {
                    $nonUnitisedClass = $this->classEntryMapper->createModel()
                        ->setName($additionalSubject['sub_long'])
                        ->setCode($code);

                    if ($this->perform) {
                        $this->classEntryMapper->save($nonUnitisedClass);
                    }

                    $this->log->info(sprintf(
                        "Additional subject %s (%s) added for %s (user %s)",
                        $additionalSubject['sub_long'],
                        $code,
                        trim("{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}"),
                        $externalId
                    ));

                    $classes->add($nonUnitisedClass);
                }

                foreach ($classes as $class) {
                    /* Find out if there's already an involvement, adding it if necessary
                     */
                    try {
                        $classInvolvement = $this->classInvolvementMapper->findOneByColumnList([
                            'external_id' => $externalId,
                            'class_id'    => $class->getId()
                        ]);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        /* Maybe this person is a teacher; check sis_class_period to see
                         * If they are already a teacher, don't need to make an arbitrary involvement
                         */
                        try {
                            $isTeacher = $this->classPeriodMapper->findOneByColumnList([
                                'class_id'            => $class->getId(),
                                'teacher_external_id' => $externalId
                            ]);

                            $this->log->info(sprintf(
                                '%s (user %s) is already a teacher of class %s (%s) - no subject involvement change required',
                                trim("{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}"),
                                $externalId,
                                $class->getId(),
                                $class->getName()
                            ));
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            $classInvolvement = $this->classInvolvementMapper->createModel()
                                ->setExternalId((string)$externalId)
                                ->setClass($class);

                            if ($this->perform) {
                                $this->classInvolvementMapper->save($classInvolvement);
                            }

                            $this->log->info(sprintf(
                                "Additional subject involvement in %s (%s) added for %s (user %s)",
                                $additionalSubject['sub_long'],
                                $code,
                                trim("{$users[$externalId]['firstname']} {$users[$externalId]['lastname']}"),
                                $externalId
                            ));
                        }
                    }
                }
            }
        }
    }
}
