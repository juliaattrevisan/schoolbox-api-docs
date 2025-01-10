<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Factory
{
    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_sentral';


    /**
     * Get timetable classes occurring this far in the past
     * Measured in weeks, defaults to -1 week
     */
    private const FROM = -1;


    /**
     * Get timetable classes up to this far into the future
     * Measured in weeks, defaults to 4 weeks
     */
    private const TO = 4;


    /**
     * @var ValidStrats
     */
    private ValidStrats $validStrats;


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
     * @var array
     */
    private array $watchlist;


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
     * @param ValidStrats                                $validStrats
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param string                                     $host
     * @param string                                     $key
     * @param array                                      $watchlist
     * @param Mapper                                     $baseProfileMapper
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
     * @param \Schoolbox\Resource\Storage\Mapper         $storageMapper
     */
    public function __construct(
        ValidStrats $validStrats,
        LoggerInterface $log,
        bool $perform,
        string $host,
        #[SensitiveParameter] string $key,
        array $watchlist,
        private bool $includePhoto,
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
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
    ) {
        $this->validStrats = $validStrats;
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->watchlist = $watchlist;

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
     * @throws \InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        $api = new Api($this->host, $this->key);

        /* A list of student ids that we previously imported
         */
        $registry = new Registry();

        /* Date range
         */
        $from = \Alaress_Date_Format::now()->addWeeks(self::FROM)->getDatabaseDate();
        $to = \Alaress_Date_Format::now()->addWeeks(self::TO)->getDatabaseDate();

        $bus = new GenericCollection(IStrategy::class);

        if ($this->validStrats->isProfileStratEnabled()) {
            $bus
                ->add(new Profile\Prepare(
                    $this->log,
                    $this->perform,
                    $this->baseProfileMapper,
                    $this->studentProfileMapper,
                    $this->parentProfileMapper,
                    $this->staffProfileMapper,
                    $this->relationshipMapper
                ))
                ->add(new Profile\Profile(
                    $this->validStrats,
                    $this->log,
                    $this->perform,
                    $api,
                    $this->watchlist,
                    $this->includePhoto,
                    $registry,
                    $this->baseProfileMapper,
                    $this->studentProfileMapper,
                    $this->parentProfileMapper,
                    $this->staffProfileMapper,
                    $this->relationshipMapper,
                    $this->storageMapper,
                ))
                ->add(new Profile\Cleanup(
                    $this->log,
                    $this->perform,
                    $this->baseProfileMapper,
                    $this->studentProfileMapper,
                    $this->parentProfileMapper,
                    $this->staffProfileMapper,
                    $this->relationshipMapper
                ));
        }

        if ($this->validStrats->isTimetableStratEnabled()) {
            $bus
                ->add(new TimetableCalendar\Prepare(
                    $this->log,
                    $this->perform,
                    $this->eventMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                    $this->classPeriodMapper,
                    $this->periodMapper,
                    $this->overridesMapper
                ))
                ->add(new TimetableCalendar\Classes(
                    $this->log,
                    $this->perform,
                    $api,
                    $registry,
                    $this->eventMapper,
                    $this->overridesMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                    $from,
                    $to
                ))
                ->add(new TimetableCalendar\Cleanup(
                    $this->log,
                    $this->perform,
                    $this->eventMapper
                ));
        }

        $bus->add(new ApiStatistics(
            $this->log,
            $api
        ));

        return $bus;
    }
}
