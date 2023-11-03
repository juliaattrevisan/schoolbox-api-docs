<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Config\Loader;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint as IsamsEndpoint;
use Schoolbox\External\Connector\Kamar\Base\Endpoint as KamarEndpoint;
use Schoolbox\External\Connector\Sentral\Base\Endpoint as SentralEndpoint;
use Schoolbox\External\Connector\TassApi\Base\Endpoint as TassApiEndpoint;
use Schoolbox\External\Connector\Compass\Base\Endpoint as CompassEndpoint;
use Schoolbox\External\Connector\Wonde\Base\Endpoint as WondeEndpoint;
use Schoolbox\External\Connector\Veracross\Base\Endpoint as VeracrossEndpoint;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
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
     * API Endpoint url
     *
     * @var string
     */
    private string $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private string $key;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * @var array
     */
    private array $watchlist;


    /**
     * @var ValidStrats
     */
    private ValidStrats $validStrats;


    /**
     * Factory constructor.
     *
     * @param string          $host
     * @param string          $key
     * @param bool            $perform
     * @param LoggerInterface $logger
     * @param array           $watchlist
     * @param ValidStrats     $validStrats
     */
    public function __construct(
        string $host,
        #[SensitiveParameter] string $key,
        bool $perform,
        LoggerInterface $logger,
        array $watchlist,
        ValidStrats $validStrats,
    ) {
        $this->host = $host;
        $this->key = $key;
        $this->perform = $perform;
        $this->logger = $logger;
        $this->watchlist = $watchlist;
        $this->validStrats = $validStrats;
    }


    /**
     * @param string|null $file
     * @param string      $type
     *
     * @return null|GenericCollection
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        switch ($type) {
            /* Produce the composites with optional Mock api connection
             * (use a locally-stored file path)
             */
            case self::ISAMS:
                return (new IsamsEndpoint\Factory(
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
                ))->produce();

            /* We never reach out to kamar so we technically don't need the $file option
             */
            case self::KAMAR:
                return (new KamarEndpoint\Factory(
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
                ))->produce();

            case self::SENTRAL:
                return (new SentralEndpoint\Factory(
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
                ))->produce();

            case self::TASS_API:
                return (new TassApiEndpoint\Factory(
                    $this->validStrats,
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,
                    $this->watchlist,
                    (int)Loader::getInstance()->get('external_tass_addnum_father'),
                    (int)Loader::getInstance()->get('external_tass_addnum_mother'),
                    (string)Loader::getInstance()->get('external_tass_address_description_father'),
                    (string)Loader::getInstance()->get('external_tass_address_description_mother'),
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
                ))->produce();

            case self::COMPASS:
                return (new CompassEndpoint\Factory(
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
                ))->produce();

            case self::WONDE:
                return (new WondeEndpoint\Factory(
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
                ))->produce();

            case self::VERACROSS:
                return (new VeracrossEndpoint\Factory(
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
                ))->produce();

            // TODO: Civica Endpoint/Factory
            case self::CIVICA:
                return null;

            default:
                // Use manual csv imports
                return null;
        }
    }
}
