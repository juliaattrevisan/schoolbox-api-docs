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
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable\Registry as TimetableRegistry;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
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
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var string
     */
    private $host;


    /**
     * @var string
     */
    private $key;


    /**
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private $spousePrefix;


    /**
     * Set in admin, corresponds to external_tass_addnum_father
     *
     * @var int
     */
    private $addnumFather;


    /**
     * Set in admin, corresponds to external_tass_addnum_mother
     *
     * @var int
     */
    private $addnumMother;


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
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param string                          $host
     * @param string                          $key
     * @param string                          $spousePrefix
     * @param int                             $addnumFather
     * @param int                             $addnumMother
     * @param string                          $fatherAddressDescription
     * @param string                          $motherAddressDescription
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
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
        string $spousePrefix,
        int $addnumFather,
        int $addnumMother,
        string $fatherAddressDescription,
        string $motherAddressDescription,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        /* Timetable pieces */
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
        $this->spousePrefix = $spousePrefix;
        $this->addnumFather = $addnumFather;
        $this->addnumMother = $addnumMother;
        $this->fatherAddressDescription = $fatherAddressDescription;
        $this->motherAddressDescription = $motherAddressDescription;

        /* Profile pieces
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        /* Timetable pieces
         */
        $this->timetableCycleMapper = $timetableCycleMapper;
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
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

        /* Timetable Registry
         *
         * This registry contains all the timetables we iteratively shanked from an endpoint
         * so we can trade space for time
         */
        $timetableRegistry = new TimetableRegistry();

        /* Cycle Registry
         *
         * This registry contains all the cycles we mined from the Timetables we iteratively
         * shanked from an endpoint so we can trade space for time
         */
        $cycleRegistry = new TimetableRegistry();

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         */
        $lookup = new Timetable\Lookup($this->timetablePeriodMapper);

        /* Purge previously-imported profile relationships and classes
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $this->log,
                $this->perform,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper,
                $this->timetablePeriodMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new Profile(
                $this->log,
                $this->perform,
                $api,
                $this->spousePrefix,
                $this->addnumFather,
                $this->addnumMother,
                $this->fatherAddressDescription,
                $this->motherAddressDescription,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new Timetable\Definition(
                $this->log,
                $this->perform,
                $api,
                $lookup,
                $timetableRegistry,
                $cycleRegistry,
                $this->timetableCycleMapper,
                $this->timetablePeriodMapper
            ))
            ->add(new Timetable\AdditionalSubjects(
                $this->log,
                $this->perform,
                $api,
                $lookup,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new Timetable\Classes(
                $this->log,
                $this->perform,
                $lookup,
                $timetableRegistry,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
                $this->classPeriodMapper
            ))
            ->add(new Cleanup(
                $this->log,
                $this->perform,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
