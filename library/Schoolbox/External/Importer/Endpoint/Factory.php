<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use DomainException;
use External\Tass\Api\Exception\InvalidApiKey;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Config\Loader;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Base\ShadowTable;
use Schoolbox\External\Connector\Civica\Base\Endpoint as CivicaEndpoint;
use Schoolbox\External\Connector\Compass\Base\Endpoint as CompassEndpoint;
use Schoolbox\External\Connector\Isams\Base\Endpoint as IsamsEndpoint;
use Schoolbox\External\Connector\Kamar\Base\Endpoint as KamarEndpoint;
use Schoolbox\External\Connector\Liss\Base\Endpoint as LissEndpoint;
use Schoolbox\External\Connector\Sentral\Base\Endpoint as SentralEndpoint;
use Schoolbox\External\Connector\TassApi\Base\Endpoint as TassApiEndpoint;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Veracross\Base\Endpoint as VeracrossEndpoint;
use Schoolbox\External\Connector\Wonde\Base\Endpoint as WondeEndpoint;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * Create a set of Composites which import Profile and Timetable data
 * from third-party endpoints into schoolbox
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class Factory implements IFactory
{
    /**
     * The family of importers that we can spawn
     */
    public const ISAMS = 'isams';
    public const KAMAR = 'kamar';
    public const SENTRAL = 'sentral';
    public const TASS_API = 'tassapi';
    public const COMPASS = 'compass';
    public const WONDE = 'wonde';
    public const LISS = 'liss';
    public const VERACROSS = 'veracross';
    public const CIVICA = 'civica';
    public const CSV = 'csv';


    /**
     * Formal naming
     *
     * This was first used in the User Modify screen so it could say
     * "TASS ID" or "iSAMS ID", a connector-specific rendition of
     * external id's label.
     */
    public const NAME = [
        self::ISAMS => 'iSAMS',
        self::KAMAR => 'Kamar',
        self::SENTRAL => 'Sentral',
        self::TASS_API => 'TASS',
        self::COMPASS => 'Compass',
        self::WONDE => 'Wonde',
        self::LISS => 'LISS',
        self::VERACROSS => 'Veracross',
        self::CIVICA => 'Civica',
        self::CSV => 'External',

        /* When you've not chosen a SUDS Data Source
         * (ie. you're still on "select an option")
         */
        '' => 'External'
    ];


    /**
     * Factory constructor.
     *
     * @param string $host
     * @param string $key
     * @param bool $perform
     * @param bool $lissAdditiveMode
     * @param LoggerInterface $logger
     * @param array $watchlist
     * @param ValidStrats $validStrats
     * @param FilesystemProxy $filesystemProxy
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $key,
        private bool $perform,
        private bool $lissAdditiveMode,
        private LoggerInterface $logger,
        private array $watchlist,
        private ValidStrats $validStrats,
        private FilesystemProxy $filesystemProxy
    ) {
    }


    /**
     * @param string|null $file
     * @param string $type
     *
     * @return GenericCollection|null
     * @throws DomainException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     * @throws InvalidApiKey
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        /* These are the usual sis Profile and sis Timetable busses.
         *
         * Produce the composites with optional Mock api connection
         * (use a locally-stored file path)
         */
        $composites = match ($type) {
            self::ISAMS => (new IsamsEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                $this->watchlist,
                Loader::getInstance()->get('zip_dir'),
                Loader::getInstance()->get('photo_sis_import'),
                $file,
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                StorageMapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            self::KAMAR => (new KamarEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                Loader::getInstance()->get('stream_dir'),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                TimetableCycleMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache()
            ))->produce(),

            self::SENTRAL => (new SentralEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                $this->watchlist,
                Loader::getInstance()->get('photo_sis_import'),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache(),
                StorageMapper::getInstance()->disableCache(),
            ))->produce(),

            self::TASS_API => (new TassApiEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                $this->watchlist,
                (int)Loader::getInstance()->get('external_tass_addnum_father'),
                (int)Loader::getInstance()->get('external_tass_addnum_mother'),
                Loader::getInstance()->get('date_local'),
                Loader::getInstance()->get('photo_sis_import'),
                trim((string)Loader::getInstance()->get('external_tass_spouse_prefix')),
                trim((string)Loader::getInstance()->get('external_tass_commrules_typecode')),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                StorageMapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            self::COMPASS => (new CompassEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                Loader::getInstance()->get('photo_sis_import'),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                StorageMapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            self::WONDE => (new WondeEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                Loader::getInstance()->get('date_timezone'),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            self::VERACROSS => (new VeracrossEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                $this->watchlist,
                Loader::getInstance()->get('date_timezone'),
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            self::CIVICA => (new CivicaEndpoint\Factory(
                $this->validStrats,
                $this->logger,
                $this->perform,
                $this->host,
                $this->key,
                $this->watchlist,
                /* Profile pieces
                 */
                UserProfile\Mapper::getInstance()->disableCache(),
                UserProfile\Student\Mapper::getInstance()->disableCache(),
                UserProfile\Parent\Mapper::getInstance()->disableCache(),
                UserProfile\Staff\Mapper::getInstance()->disableCache(),
                UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                /* Timetable pieces
                 */
                \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                ClassEntryMapper::getInstance()->disableCache(),
                ClassInvolvementMapper::getInstance()->disableCache(),
                ClassPeriodMapper::getInstance()->disableCache(),
                TimetablePeriodMapper::getInstance()->disableCache(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
            ))->produce(),

            /* Use manual csv imports
             */
            default => new GenericCollection(IStrategy::class),
        };

        /* Always look for liss payloads and add their data if we can
         */
        $lissComposites = (new LissEndpoint\Factory(
            $this->logger,
            $this->perform,
            $this->lissAdditiveMode,
            trim((string)Loader::getInstance()->get('external_liss_default_event_type')),
            trim((string)Loader::getInstance()->get('external_liss_exclusions')),
            trim((string)Loader::getInstance()->get('external_liss_teacher_identifier')),
            $this->filesystemProxy,
            \Schoolbox\Resource\Storage\Mapper::getInstance(),
            /* Timetable pieces
             */
            \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
            ClassEntryMapper::getInstance()->disableCache(),
            ClassInvolvementMapper::getInstance()->disableCache(),
            ClassPeriodMapper::getInstance()->disableCache(),
            TimetablePeriodMapper::getInstance()->disableCache(),
            \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache()
        ))->produce();

        $composites = $composites->merge($lissComposites);

        // Enable shadow tables before all other strategies,
        // and disable them after all other strategies
        return $composites
            ->prepend(
                new ShadowTable\All\Enable(
                    $this->logger,
                    $this->perform,
                    UserProfile\Mapper::getInstance()->disableCache(),
                    UserProfile\Student\Mapper::getInstance()->disableCache(),
                    UserProfile\Parent\Mapper::getInstance()->disableCache(),
                    UserProfile\Staff\Mapper::getInstance()->disableCache(),
                    UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                    ClassEntryMapper::getInstance()->disableCache(),
                    ClassInvolvementMapper::getInstance()->disableCache(),
                    ClassPeriodMapper::getInstance()->disableCache(),
                    TimetableCycleMapper::getInstance()->disableCache(),
                    TimetablePeriodMapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache(),
                )
            )
            ->add(
                new ShadowTable\All\Disable(
                    $this->logger,
                    $this->perform,
                    UserProfile\Mapper::getInstance()->disableCache(),
                    UserProfile\Student\Mapper::getInstance()->disableCache(),
                    UserProfile\Parent\Mapper::getInstance()->disableCache(),
                    UserProfile\Staff\Mapper::getInstance()->disableCache(),
                    UserProfile\Relationship\Mapper::getInstance()->disableCache(),
                    ClassEntryMapper::getInstance()->disableCache(),
                    ClassInvolvementMapper::getInstance()->disableCache(),
                    ClassPeriodMapper::getInstance()->disableCache(),
                    TimetableCycleMapper::getInstance()->disableCache(),
                    TimetablePeriodMapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Overrides\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache(),
                )
            );

        /* Notes from previous findings:
         *
         * The IFactory injected might not know about liss, such as when we're given a
         * CalendarFactory instance. That has nothing to do with liss, and that might snap
         * Scheduler.php unless we coalesce it to an empty collection that we can operate upon...
         *
         * ...but there was talk about having liss events being imported too; someone asked
         * about them, but we currently don't support that usage.
         *
         * $composites equates to an empty collection when the factories are unable to
         * figure out the $type you provided, or if you chose for it to do nothing in
         * the suds control panel. The advent of liss being bolted on makes this always
         * the case now, where previously it was possible for Factories to return a null.
         */
    }
}
