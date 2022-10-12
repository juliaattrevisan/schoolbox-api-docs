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
use Schoolbox\External\Connector\Isams\Base\Endpoint\Profile;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;
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
     * @var string
     */
    private string $zipDir;


    /**
     * @var bool
     */
    private bool $includePhoto;


    /**
     * @var string|null
     */
    private ?string $file;


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
     * @var StorageMapper
     */
    private StorageMapper $storageMapper;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @var TimetableCycleMapper
     */
    private TimetableCycleMapper $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private ClassPeriodMapper $classPeriodMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param string                          $host
     * @param string                          $key
     * @param string                          $zipDir
     * @param bool                            $includePhoto
     * @param string|null                     $file
     * @param Mapper                          $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper                   $storageMapper
     * @param UserMapper                      $userMapper
     * @param TimetableCycleMapper            $timetableCycleMapper
     * @param TimetablePeriodMapper           $timetablePeriodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        string $zipDir,
        bool $includePhoto,
        ?string $file,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        StorageMapper $storageMapper,
        /* Timetable pieces */
        UserMapper $userMapper,
        TimetableCycleMapper $timetableCycleMapper,
        TimetablePeriodMapper $timetablePeriodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->zipDir = $zipDir;
        $this->includePhoto = $includePhoto;
        $this->file = $file;

        /* Profile pieces
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
        $this->storageMapper = $storageMapper;

        /* Timetable pieces
         */
        $this->userMapper = $userMapper;
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
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
                DSN::MODULE_BATCHXML => new Batchxml(new MockFileApi($this->file)),
            ];
        } else {
            $this->log->info('Accessing api endpoint');

            /* Connect and shank data for all the modules we know about
             */
            $api = [
                DSN::MODULE_BATCHXML => new Batchxml(new Api($this->host, $this->key, DSN::MODULE_BATCHXML)),
            ];
        }

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         */
        $lookup = new Timetable\Lookup($this->timetablePeriodMapper, $this->userMapper);

        /* This lets you shank down zip files containing everyone's profile pictures
         */
        $photoDownloader = new Profile\PhotoDownloader($this->host, $this->key, $this->zipDir);

        /* Purge previously-imported profile relationships and classes
         */
        return (new GenericCollection(IStrategy::class))
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
            ))
            ->add(new Timetable\Prepare(
                $this->log,
                $this->perform,
                $this->timetablePeriodMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new Timetable\Identifier(
                $this->log,
                $this->perform,
                $api
            ))
            ->add(new Timetable\Definition(
                $this->log,
                $this->perform,
                $api,
                $this->timetableCycleMapper,
                $this->timetablePeriodMapper
            ))
            ->add(new Timetable\Classes(
                $this->log,
                $this->perform,
                $api,
                $lookup,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ;
    }
}
