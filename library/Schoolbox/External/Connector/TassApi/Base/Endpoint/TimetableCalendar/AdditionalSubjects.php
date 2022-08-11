<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class AdditionalSubjects
 *
 * Adds additional non-timetabled subjects from the sis.
 *
 * These don't have any associated sis_class_period records;
 * it only has sis_class and sis_class_user records.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar
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
     * @var Lookup
     */
    private Lookup $lookup;


    /**
     * @var array
     */
    private array $watchlist;


    /**
     * TimetableClass constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param array                  $api
     * @param Lookup                 $lookup
     * @param array                  $watchlist
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup,
        array $watchlist,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;
        $this->watchlist = $watchlist;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
    }


    /**
     * Import data from api endpoints into local schema
     *
     * @return void
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
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
     * @throws \JsonException
     * @throws \PDOException
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
            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!(new Watchlist())->isOnWatchlist((string)$externalId, $this->watchlist)) {
                continue;
            }

            try {
                $data = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument($endpoint, [
                    'code' => $externalId
                ]);
            } catch (\RuntimeException $e) {
                $this->log->warning(sprintf(
                    "Skipping timetable data for student %s.",
                    $externalId
                ));

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

                /* If the FROM_DATE is not null and the TO_DATE is not null,
                 * check that the current time is between these two dates
                 * If we're outside these two dates, skip this subject
                 *
                 * FROM_DATE and TO_DATE might not even exist in the API payload
                 * for some mysterious reason, because TASS reasons I suppose
                 */
                $fromDate = $additionalSubject['from_date'] ?? '';
                $toDate = $additionalSubject['to_date'] ?? '';

                $isActive = (new ValidActiveEnrolment())->isSatisfiedBy($fromDate, $toDate);

                if (!$isActive) {
                    $this->log->info(sprintf(
                        'Skipping class %s - we are outside the bounds of from_date %s and to_date %s',
                        $additionalSubject['sub_code'],
                        $additionalSubject['from_date'],
                        $additionalSubject['to_date']
                    ));

                    continue;
                }

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
                        "Additional subject %s (%s) added",
                        $additionalSubject['sub_long'],
                        $code
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
                        "Additional subject involvement in %s (%s) added for %s (%s)",
                        $additionalSubject['sub_long'],
                        $code,
                        trim("{$userData['firstname']} {$userData['lastname']}"),
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
     * @throws \JsonException
     * @throws \PDOException
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
            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!(new Watchlist())->isOnWatchlist((string)$externalId, $this->watchlist)) {
                continue;
            }

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
                        "Additional subject %s (%s) added",
                        $additionalSubject['sub_long'],
                        $code
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
                            trim("{$userData['firstname']} {$userData['lastname']}"),
                            $externalId
                        ));
                    }
                }
            }
        }
    }
}
