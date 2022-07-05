<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class Factory
{
    /**
     * An arbitrarily-chosen string to denote where these events came from
     */
    public const SOURCE_NAME = 'suds_tass';


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
     * This is the config setting for date_local
     * It's used by the Nat Picker in the optional TASS Academic Periods area
     *
     * @var string
     */
    private string $dateLocalFormat;


    /**
     * @var bool
     */
    private bool $includePhoto;


    /**
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private string $spousePrefix;


    /**
     * Set in admin, corresponds to external_tass_addnum_father
     *
     * @var int
     */
    private int $addnumFather;


    /**
     * Set in admin, corresponds to external_tass_addnum_mother
     *
     * @var int
     */
    private int $addnumMother;


    /**
     * The address_description field in the getCommunicationRulesDetails payload
     * that refers to a father specifically, as determined by schools' instances
     *
     * @var string
     */
    private string $fatherAddressDescription;


    /**
     * The address_description field in the getCommunicationRulesDetails payload
     * that refers to a mother specifically, as determined by schools' instances
     *
     * @var string
     */
    private string $motherAddressDescription;


    /**
     * TASS communication rules type codes (in comma separated format) for all guardian relationships
     * that can be associated with students
     * @var string|null
     */
    private ?string $communicationTypes;


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
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


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
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $periodMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param string                                     $host
     * @param string                                     $key
     * @param string                                     $dateLocalFormat
     * @param bool                                       $includePhoto
     * @param string                                     $spousePrefix
     * @param int                                        $addnumFather
     * @param int                                        $addnumMother
     * @param string                                     $fatherAddressDescription
     * @param string                                     $motherAddressDescription
     * @param string|null                                $communicationTypes
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
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        string $dateLocalFormat,
        bool $includePhoto,
        string $spousePrefix,
        int $addnumFather,
        int $addnumMother,
        string $fatherAddressDescription,
        string $motherAddressDescription,
        ?string $communicationTypes,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        StorageMapper $storageMapper,
        /* Timetable pieces */
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetablePeriodMapper $periodMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->dateLocalFormat = $dateLocalFormat;
        $this->includePhoto = $includePhoto;
        $this->spousePrefix = $spousePrefix;
        $this->addnumFather = $addnumFather;
        $this->addnumMother = $addnumMother;
        $this->fatherAddressDescription = $fatherAddressDescription;
        $this->motherAddressDescription = $motherAddressDescription;
        $this->communicationTypes = $communicationTypes;

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
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->periodMapper = $periodMapper;
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws InvalidArgumentException
     */
    public function produce(): GenericCollection
    {
        $this->log->info('Accessing api endpoint');

        $modules = [
            Codec\DSN::MODULE_LMS_INTEGRATION,
            Codec\DSN::MODULE_STUDENT_DETAILS,
            Codec\DSN::MODULE_IDENTITY_MANAGEMENT
        ];

        $api = [];

        foreach ($modules as $module) {
            $api[$module] = new Api($this->host, $this->key, $module);
        }

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         */
        $lookup = new TimetableCalendar\Lookup($this->periodMapper);

        /* Purge previously-imported profile relationships and classes
         */
        return (new GenericCollection(IStrategy::class))
//            ->add(new Profile\Prepare(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
//            ->add(new Profile\Profile(
//                $this->log,
//                $this->perform,
//                $api,
//                $this->includePhoto,
//                $this->spousePrefix,
//                $this->addnumFather,
//                $this->addnumMother,
//                $this->fatherAddressDescription,
//                $this->motherAddressDescription,
//                $this->communicationTypes,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper,
//                $this->storageMapper
//            ))
//            ->add(new Profile\Cleanup(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
            ->add(new TimetableCalendar\Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper,
                $this->periodMapper,
                $this->overridesMapper,
            ))
//            ->add(new TimetableCalendar\AdditionalSubjects(
//                $this->log,
//                $this->perform,
//                $api,
//                $lookup,
//                $this->classEntryMapper,
//                $this->classInvolvementMapper
//            ))
            ->add(new TimetableCalendar\Classes(
                $this->log,
                $this->perform,
                $api,
                $lookup,
                $this->dateLocalFormat,
                $this->eventMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->overridesMapper
            ))
            ->add(new TimetableCalendar\Cleanup(
                $this->log,
                $this->perform,
                $this->eventMapper
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
