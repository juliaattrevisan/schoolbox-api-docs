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
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\Config\Loader;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint as IsamsEndpoint;
use Schoolbox\External\Connector\Kamar\Base\Endpoint as KamarEndpoint;
use Schoolbox\External\Connector\Sentral\Base\Endpoint as SentralEndpoint;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * Create a set of Composites which import Profile and Timetable data
 * from third-party endpoints into schoolbox
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class Factory
{

    /**
     * The family of importers that we can spawn
     */
    public const ISAMS = 'isams';
    public const KAMAR = 'kamar';
    public const SENTRAL = 'sentral';
    public const CSV = 'csv';


    /**
     * API Endpoint url
     *
     * @var string
     */
    private $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private $key;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Factory constructor.
     *
     * @param string          $host
     * @param string          $key
     * @param bool            $perform
     * @param LoggerInterface $logger
     */
    public function __construct(string $host, string $key, bool $perform, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->key = $key;
        $this->perform = $perform;
        $this->logger = $logger;
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
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        switch ($type) {

            /* Produce the composites with optional Mock api connection
             * (use a locally-stored file path)
             */
            case self::ISAMS:
                return (new IsamsEndpoint\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,
                    $file,

                    /* Profile pieces
                     */
                    UserProfile\Mapper::getInstance(),
                    UserProfile\Student\Mapper::getInstance(),
                    UserProfile\Parent\Mapper::getInstance(),
                    UserProfile\Staff\Mapper::getInstance(),
                    UserProfile\Relationship\Mapper::getInstance(),

                    /* Timetable pieces
                     */
                    CampusMapper::getInstance(),
                    UserMapper::getInstance(),
                    TimetableCampusMapper::getInstance(),
                    TimetableCycleMapper::getInstance(),
                    TimetablePeriodMapper::getInstance(),
                    ClassEntryMapper::getInstance(),
                    ClassInvolvementMapper::getInstance(),
                    ClassPeriodMapper::getInstance()
                ))->produce();

            /* We never reach out to kamar so we technically don't need the $file option
             */
            case self::KAMAR:
                return (new KamarEndpoint\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    Loader::getInstance()->get('stream_dir'),

                    /* Profile pieces
                     */
                    UserProfile\Mapper::getInstance(),
                    UserProfile\Student\Mapper::getInstance(),
                    UserProfile\Parent\Mapper::getInstance(),
                    UserProfile\Staff\Mapper::getInstance(),
                    UserProfile\Relationship\Mapper::getInstance(),

                    /* Timetable pieces
                     */
                    CampusMapper::getInstance(),
                    TimetableCampusMapper::getInstance(),
                    TimetableCycleMapper::getInstance(),
                    TimetablePeriodMapper::getInstance(),
                    ClassEntryMapper::getInstance(),
                    ClassInvolvementMapper::getInstance(),
                    ClassPeriodMapper::getInstance(),
                    Term\Mapper::getInstance()
                ))->produce();

            case self::SENTRAL:
                return (new SentralEndpoint\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,

                    /* Profile pieces
                    */
                    UserProfile\Mapper::getInstance(),
                    UserProfile\Student\Mapper::getInstance(),
                    UserProfile\Parent\Mapper::getInstance(),
                    UserProfile\Staff\Mapper::getInstance(),
                    UserProfile\Relationship\Mapper::getInstance(),

                    /* Timetable pieces
                    */
                    CampusMapper::getInstance(),
                    UserMapper::getInstance(),
                    TimetableCampusMapper::getInstance(),
                    TimetableCycleMapper::getInstance(),
                    TimetablePeriodMapper::getInstance(),
                    ClassEntryMapper::getInstance(),
                    ClassInvolvementMapper::getInstance(),
                    ClassPeriodMapper::getInstance()
                ))->produce();

            default:
                // Use manual csv imports
                return null;
        }
    }
}
