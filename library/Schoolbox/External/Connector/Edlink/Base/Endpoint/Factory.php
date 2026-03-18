<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Class Factory
 *
 * The overall import recipe for Edlink. A composite of multiple classes each
 * of which has its own particular responsibility.
 *
 * Configuration is loaded from a local config.json file in this directory.
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
     * Path to the config file, relative to this directory
     */
    private const CONFIG_FILE = __DIR__ . '/config.json';

    /**
     * @param ValidStrats                                $validStrats
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param array                                      $watchlist
     * @param UserProfile\Mapper                         $baseProfileMapper
     * @param UserProfile\Student\Mapper                 $studentProfileMapper
     * @param UserProfile\Parent\Mapper                  $parentProfileMapper
     * @param UserProfile\Staff\Mapper                   $staffProfileMapper
     * @param UserProfile\Relationship\Mapper            $relationshipMapper
     * @param StorageMapper                              $storageMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param ClassPeriodMapper                          $classPeriodMapper
     * @param TimetablePeriodMapper                      $periodMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private array $watchlist,
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
     * Load configuration from the local config.json file
     *
     * @return array
     * @throws \RuntimeException
     */
    private function loadConfig(): array
    {
        if (!file_exists(self::CONFIG_FILE)) {
            throw new \RuntimeException(
                'Edlink config file not found. Copy config.json.example to config.json and add your integration access token.'
            );
        }

        $config = json_decode(file_get_contents(self::CONFIG_FILE), true, 512, JSON_THROW_ON_ERROR);

        if (empty($config['token'])) {
            throw new \RuntimeException('Edlink config.json must contain a non-empty "token" field.');
        }

        return $config;
    }

    /**
     * @return GenericCollection
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        $this->log->info('EDLINK DIAGNOSTIC: Factory::produce() called');

        $config = $this->loadConfig();

        $this->log->info(sprintf('EDLINK DIAGNOSTIC: Config loaded, token length=%d', strlen($config['token'])));

        /* Spawn an Api instance using the token from config
         */
        $api = new Api($config['token']);

        $importPhotos = (bool)($config['import_photos'] ?? true);

        $bus = new GenericCollection(IStrategy::class);

        $this->log->info(sprintf(
            'EDLINK DIAGNOSTIC: profileEnabled=%s, timetableEnabled=%s',
            $this->validStrats->isProfileStratEnabled() ? 'true' : 'false',
            $this->validStrats->isTimetableStratEnabled() ? 'true' : 'false'
        ));

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
                    $importPhotos,
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
                    $this->eventMapper,
                    $this->overridesMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper
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
