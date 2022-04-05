<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\GenericCollection;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
 */
class Factory
{

    /**
     * Get timetable classes occurring this far in the past
     * Measured in months, defaults to -1 month
     */
    private const FROM = -1;


    /**
     * Get timetable classes up to this far into the future
     * Measured in months, defaults to 6 months
     */
    private const TO = 6;


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
     * The timezone string set in schoolbox instance's Admin area
     * https://vagrant.dev.schoolbox.cloud/adminv2/setting/Localisation#date_timezone
     *
     * This is used to convert the Wonde UTC datetimes' timezone into
     * wherever your schoolbox instance lives
     *
     * @var string
     */
    private string $timezone;


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
     * @param string                                     $timezone
     * @param UserProfile\Mapper                         $baseProfileMapper
     * @param UserProfile\Student\Mapper                 $studentProfileMapper
     * @param UserProfile\Parent\Mapper                  $parentProfileMapper
     * @param UserProfile\Staff\Mapper                   $staffProfileMapper
     * @param UserProfile\Relationship\Mapper            $relationshipMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        string $timezone,
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
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->timezone = $timezone;

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
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        /* Date range
         */
        $from = \Alaress_Date_Format::now()->addWeeks(self::FROM);
        $to = \Alaress_Date_Format::now()->addMonths(self::TO);

        /* Decide a timezone for all the UTC dates Wonde gives to us
         */
        $timezone = new \DateTimeZone($this->timezone);

        /* Spawn an Api instance
         */
        $api = new Api($this->host, $this->key);

        return (new GenericCollection(IStrategy::class))
            /* Purge previously-imported profile relationships and classes
             */
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
                $this->log,
                $this->perform,
                $api,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new Profile\Cleanup(
                $this->log,
                $this->perform,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new TimetableCalendar\Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->overridesMapper
            ))
            ->add(new TimetableCalendar\Classes(
                $this->log,
                $this->perform,
                $api,
                $this->eventMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->overridesMapper,
                $timezone,
                $from,
                $to
            ))
            ->add(new ApiStatistics(
                $this->log,
                $api
            ));
    }
}
