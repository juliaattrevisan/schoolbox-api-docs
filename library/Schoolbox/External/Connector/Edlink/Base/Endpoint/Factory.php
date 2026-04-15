<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\Config\Loader;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Lookup\PeopleIds;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use SensitiveParameter;

/**
 * Class Factory
 *
 * The overall import recipe for Edlink. A composite of multiple classes each
 * of which has its own particular responsibility.
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class Factory
{
    /**
     * Event source identifier for mass operations
     */
    public const SOURCE_NAME = 'suds_edlink';

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
     * Factory constructor.
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
        private StorageMapper $storageMapper,
        /* Timetable pieces */
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
    }

    /**
     * @return GenericCollection
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        /* Schedule/SUDS Timetable: Date Range
         */
        $fromDate = \Alaress_Date_Format::now()->addMonths(self::FROM)->startOfMonth();
        $toDate = \Alaress_Date_Format::now()->addMonths(self::TO)->endOfMonth();

        $timezone = new \DateTimeZone(
            (string)Loader::getInstance()->get('date_timezone') ?: 'UTC'
        );

        /* Spawn an Api instance
         */
        $api = new Api(host: $this->host, apiKey: $this->key);

        /* Watchlist helper instance (reused across strategies)
         */
        $watcher = new Watchlist();

        /* Shared Edlink UUID -> external ID lookup, populated by Profile and
         * consumed by Classes. Generated on demand when only timetable sync
         * is enabled.
         */
        $peopleIds = new PeopleIds();

        /* Command bus
         */
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
                    $watcher,
                    $this->watchlist,
                    $this->includePhoto,
                    $peopleIds,
                    $this->baseProfileMapper,
                    $this->studentProfileMapper,
                    $this->parentProfileMapper,
                    $this->staffProfileMapper,
                    $this->relationshipMapper,
                    $this->storageMapper
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
                    $peopleIds,
                    $this->eventMapper,
                    $this->overridesMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                    $fromDate,
                    $toDate,
                    $timezone
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
