<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\IsamsApi\Base\Endpoint as IsamsApiEndpoint;
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
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Factory
{
    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_isams';


    /**
     * Factory constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $host
     * @param string $key
     * @param array $watchlist
     * @param string $zipDir
     * @param bool $includePhoto
     * @param string|null $file
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
        private array $watchlist,
        private string $zipDir,
        private bool $includePhoto,
        private ?string $file,
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
     */
    public function produce(): GenericCollection
    {
        if ($this->file) {
            $this->log->info("Loading from locally-cached xml file: {$this->file}");

            /* Forge an equivalent that reads from a local file where possible
             * Staff and Students photos does nothing, intentionally, atm.
             * Only the xml files can/should be stored and read locally afaict
             */
            $api = [
                DSN::MODULE_BATCHXML => new Batchxml(new MockFileApi($this->file, $this->key)),
            ];
        } else {
            $this->log->info('Accessing api endpoint');

            /* Connect and shank data for all the modules we know about
             */
            $api = [
                DSN::MODULE_BATCHXML => new Batchxml(new Api($this->host, $this->key, DSN::MODULE_BATCHXML)),
            ];
        }

        // if batch API exists, use that for photos, else fallback to batchxml thumbnails
        $credentials = (new DSN())->decode($this->key);
        if ($credentials['modules'][DSN::MODULE_BATCHAPIHOST]['key'] !== '') {
            $photoDownloader = new IsamsApiEndpoint\Profile\PhotoDownloader($this->host, $this->key, $this->zipDir);
        } else {
            /* This lets you shank down zip files containing everyone's profile pictures
         */
            $photoDownloader = new Profile\PhotoDownloader($this->host, $this->key, $this->zipDir);
        }

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
                    $photoDownloader,
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
                    $this->includePhoto,
                    $this->baseProfileMapper,
                    $this->studentProfileMapper,
                    $this->parentProfileMapper,
                    $this->staffProfileMapper,
                    $this->relationshipMapper,
                    $photoDownloader
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
                    $this->watchlist,
                    $this->eventMapper,
                    $this->overridesMapper,
                    $this->classEntryMapper,
                    $this->classInvolvementMapper,
                ))
                ->add(new TimetableCalendar\Cleanup(
                    $this->log,
                    $this->perform,
                    $this->eventMapper
                ));
        }

        return $bus;
    }
}
