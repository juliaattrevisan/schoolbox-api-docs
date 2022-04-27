<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
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
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


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
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $periodMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param string                                     $host
     * @param string                                     $key
     * @param UserProfile\Mapper                         $baseProfileMapper
     * @param UserProfile\Student\Mapper                 $studentProfileMapper
     * @param UserProfile\Parent\Mapper                  $parentProfileMapper
     * @param UserProfile\Staff\Mapper                   $staffProfileMapper
     * @param UserProfile\Relationship\Mapper            $relationshipMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param ClassPeriodMapper                          $classPeriodMapper
     * @param TimetablePeriodMapper                      $periodMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
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
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetablePeriodMapper $periodMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
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
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->periodMapper = $periodMapper;
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function produce(): GenericCollection
    {
        $api = new Api($this->host, $this->key);

        /* Default event owner
         */
        $credentials = (new Codec\DSN())->decode($this->key);

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
            ->add(new TimetableCalendar\Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper,
                $this->periodMapper,
                $this->overridesMapper,
            ))
//            ->add(new TimetableCalendar\Events(
//                $this->log,
//                $this->perform,
//                $api,
//                $defaultOwner,
//                $defaultEventType,
//                $this->userMapper,
//                $this->eventMapper,
//                $this->eventSourceMapper,
//                $this->relationPersonalMapper,
//                $this->relationFolderMapper,
//                $this->folderMapper,
//                $this->classEntryMapper,
//                $this->classInvolvementMapper,
//            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
