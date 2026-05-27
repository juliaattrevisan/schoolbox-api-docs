<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use GuzzleHttp\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
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
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
 */
class Factory
{
    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const string SOURCE_NAME = 'suds_veracross';


    /**
     * Get timetable classes occurring this far in the past
     * Measured in weeks, defaults to -1 weeks
     */
    private const int FROM = -1;


    /**
     * Get timetable classes up to this far into the future
     * Measured in weeks, defaults to 12 weeks (3 months)
     */
    private const int TO = 12;


    /**
     * Factory constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $host
     * @param string $key
     * @param array $watchlist
     * @param bool $includePhoto
     * @param Mapper $baseProfileMapper
     * @param UserProfile\Student\Mapper $studentProfileMapper
     * @param UserProfile\Parent\Mapper $parentProfileMapper
     * @param UserProfile\Staff\Mapper $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper $classPeriodMapper
     * @param TimetablePeriodMapper $periodMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private string $host,
        #[SensitiveParameter] private string $key,
        private array $watchlist,
        private bool $includePhoto,
        /* Profile pieces */
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        /* Timetable pieces */
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
    ) {
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        /* Schedule/SUDS Timetable: Date Range
         */
        $from = \Alaress_Date_Format::now()->addWeeks(self::FROM)->startOfWeek();
        $to = \Alaress_Date_Format::now()->addWeeks(self::TO)->endofWeek();

        /* Spawn an Api instance
         */
        $api = new Api(host: $this->host, apiKey: $this->key, logger: $this->log);

        /* A Watchlist instance that we'll reuse when diagnosing stuff
         */
        $watcher = new Watchlist();

        /* Command bus
         */
        $bus = new GenericCollection(IStrategy::class);

        if ($this->validStrats->isProfileStratEnabled()) {
            $bus
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
                    $this->validStrats,
                    $this->log,
                    $this->perform,
                    $api,
                    $watcher,
                    $this->watchlist,
                    $this->includePhoto,
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
                /* SUDS Timetable Events
                 */
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
                    $watcher,
                    $this->watchlist,
                    $this->eventMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                    $this->overridesMapper,
                    $from,
                    $to
                ))
                ->add(new TimetableCalendar\Cleanup(
                    $this->log,
                    $this->perform,
                    $this->eventMapper
                ));
        }

        $bus
            ->add(new ApiStatistics(
                $this->log,
                $api
            ));

        return $bus;
    }
}
