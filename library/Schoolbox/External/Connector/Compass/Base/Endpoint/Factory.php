<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

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
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use SensitiveParameter;
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
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_compass';


    /**
     * Factory constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $host
     * @param string $key
     * @param bool $includePhoto
     * @param string $customUserAgent
     * @param Mapper $baseProfileMapper
     * @param UserProfile\Student\Mapper $studentProfileMapper
     * @param UserProfile\Parent\Mapper $parentProfileMapper
     * @param UserProfile\Staff\Mapper $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper $storageMapper
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
        private bool $includePhoto,
        private string $customUserAgent,
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
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        $api = new Api($this->host, $this->key, $this->customUserAgent);

        /* Data is obtained daily; remember lower and upper bound values
         * for api calls. It needs to be in a specific format so might
         * as well remember that
         */
        $from = \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c');
        $to = \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c');

        /* Purge previously-imported profile relationships and classes
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
                    $this->includePhoto,
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
                    $this->overridesMapper,
                    self::SOURCE_NAME
                ))
                ->add(new TimetableCalendar\Classes(
                    $this->log,
                    $this->perform,
                    $api,
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

        $bus->add(new ApiCertification(
            $this->log,
            $api
        ));

        return $bus;
    }
}
