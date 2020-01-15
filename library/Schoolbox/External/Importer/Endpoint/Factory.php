<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

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
    public const CSV = 'csv';
    public const ISAMS = 'isams';
    public const KAMAR = 'kamar';
    public const SENTRAL = 'sentral';


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
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        switch ($type) {
            case self::ISAMS:
                return $this->produceIsams($file);

            case self::KAMAR:
                return $this->produceKamar();

            case self::SENTRAL:
            default:
                // Use manual csv imports
                return null;
        }
    }


    /**
     * Produce the composites with optional Mock api connection
     * (use a locally-stored file path)
     *
     * @param string|null $file
     * @return GenericCollection
     * @throws UnexpectedValueException
     */
    private function produceIsams(?string $file): GenericCollection
    {
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
    }


    /**
     * We never reach out to kamar so we technically don't need the $file option
     *
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    private function produceKamar(): GenericCollection
    {
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
    }
}
