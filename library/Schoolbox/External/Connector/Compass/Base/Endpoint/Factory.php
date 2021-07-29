<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class Factory
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
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * @var UserProfile\Mapper
     */
    private UserProfile\Mapper $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private UserProfile\Student\Mapper $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private UserProfile\Parent\Mapper $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private UserProfile\Staff\Mapper $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private UserProfile\Relationship\Mapper $relationshipMapper;


    /**
     * @var CampusMapper
     */
    private CampusMapper $campusMapper;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


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
     * Factory constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param string                          $host
     * @param string                          $key
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param CampusMapper                    $campusMapper
     * @param TimetableCycleMapper            $timetableCycleMapper
     * @param TimetablePeriodMapper           $timetablePeriodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        /* Timetable pieces */
        CampusMapper $campusMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;

        /* Profile pieces
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        /* Timetable pieces
         */
        $this->campusMapper = $campusMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         */
        $lookup = new Timetable\Lookup($this->timetablePeriodMapper);

        $api = new Api($this->host, $this->key);

        $academicGroups = new Timetable\AcademicGroups($api);
        $schedule       = new Timetable\Schedule($api);

        return (new GenericCollection(IStrategy::class))

            /* Purge previously-imported profile relationships and classes
             */
//            ->add(new Profile\Prepare(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
//            ->add(new Profile\Profile(
//                $this->log,
//                $this->perform,
//                $api,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
//            ->add(new Profile\Cleanup(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
            ->add(new Timetable\Prepare(
                $this->log,
                $this->perform,
                $this->timetablePeriodMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new Timetable\Definition(
                $this->log,
                $this->perform,
                $academicGroups,
                $schedule,
                $this->timetableCycleMapper,
                $this->timetablePeriodMapper
            ))
            ->add(new Timetable\Classes(
                $this->log,
                $this->perform,
                $lookup,
                $academicGroups,
                $schedule,
                $api,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
