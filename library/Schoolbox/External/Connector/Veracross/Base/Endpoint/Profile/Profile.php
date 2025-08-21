<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

use Alaress_Date_Format;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ContactInfo;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\EmergencyContact;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Health;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Household;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Language;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Photo;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Relationship;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ValueListConverter;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * The access token's scope, used throughout this strat
     * (perhaps could work on subdividing or unifying this further)
     *
     * Required scopes:
     *
     * students:list
     * emergency_contacts:list
     * health.patients:list
     * admission.languages:list
     * parents:list
     * relationships:list
     * staff_faculty:list
     * contact_info:list
     * person_photos:list
     */
    public const SCOPE = '
        students:list
        emergency_contacts:list
        health.patients:list
        admission.languages:list
        parents:list
        relationships:list
        staff_faculty:list
        contact_info:list
        person_photos:list
    ';


    /**
     * How long to wait if we're trying to access a veracross-generated url
     * for profile pics
     */
    private const TIMEOUT = 15;


    /**
     * Special event query generator that saves partial rows
     *
     * @var ProfileHandler
     */
    private ProfileHandler $profileHandler;


    /**
     * This lookup could be shared between student/parent/staff imports
     * depending on your strat selection for segregated imports
     *
     * @var ContactInfo
     */
    private ContactInfo $contactInfoLookup;


    /**
     * A list of people's profile pics and where to grab them
     *
     * @var Photo
     */
    private Photo $photoLookup;


    /**
     * A dedicated guzzle client purely to access a veracross-generated url
     *
     * @var Client
     */
    private Client $client;


    /**
     * Profile constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param array $watchlist
     * @param bool $includePhoto
     * @param Mapper $baseProfileMapper
     * @param UserProfile\Student\Mapper $studentProfileMapper
     * @param UserProfile\Parent\Mapper $parentProfileMapper
     * @param UserProfile\Staff\Mapper $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private array $watchlist,
        private bool $includePhoto,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
    ) {
        /* Profile Chandler that saves a subset of profile records' columns
         * to minimise changes to the db, due to db ignoring the indexes
         */
        $this->profileHandler = new ProfileHandler(
            $this->baseProfileMapper,
            $this->studentProfileMapper,
            $this->parentProfileMapper,
            $this->staffProfileMapper,
            $this->log
        );

        /* Shared lookups
         */
        $this->contactInfoLookup = new ContactInfo($this->api);

        /* Students might have profile pics;
         * Staff might have profile pics
         */
        $this->photoLookup = (new Photo($this->baseProfileMapper, $this->api, $this->watchlist));

        /* A random guzzle client with no special configuration,
         * used purely to access a url Veracross has generated for us.
         */
        $this->client = new Client([
            'timeout' => self::TIMEOUT,
            'http_errors' => true,
        ]);
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Import students' profile data
         * Import their specific parents' profile data
         */
        if ($this->validStrats->isStudentStratEnabled() || $this->validStrats->isFamiliesStratEnabled()) {
            $this->log->info('Processing students and their parents.');

            $this->importStudents();
        }

        /* Import staff profile data
         */
        if ($this->validStrats->isStaffStratEnabled()) {
            $this->log->info('Processing staff.');

            $this->importStaff();
        }
    }


    /**
     * Import students' profile data
     */
    private function importStudents(): void
    {
        /* Prefetch a list of medically-afflicted dudes
         */
        $this->log->debug('Prefetching medicals...');
        $medicalLookup = (new Health($this->api, $this->watchlist))->generate();

        /* Prefetch a list of language usages
         */
        $this->log->debug('Prefetching languages...');
        $languageLookup = (new Language($this->api, $this->watchlist))->generate();

        /* Optionally process profile pics
         */
        $photoLookup = null;

        if ($this->includePhoto) {
            $photoLookup = $this->photoLookup->generate();
        }

        /* Only available when Families strat is enabled
         */
        $householdLookup = [];
        $relationshipLookup = [];
        $emergencyContactsLookup = [];
        $contactInfoLookup = [];

        if ($this->validStrats->isFamiliesStratEnabled()) {
            /* Prefetch a list of households, which contain Parents
             */
            $this->log->debug('Prefetching households...');
            $householdLookup = (new Household($this->api))->generate();

            /* Prefetch a list of relationships
             */
            $this->log->debug('Prefetching relationships...');
            $relationshipLookup = (new Relationship($this->api))->generate();

            /* Prefetch a list of emergency contacts
             */
            $this->log->debug('Prefetching emergency contacts...');
            $emergencyContactsLookup = (new EmergencyContact($this->api))->generate();

            /* Prefetch a list of contact info, which contain phone numbers
             * This generally only applies to Parents and Staff
             */
            $this->log->debug('Prefetching contact info...');
            $contactInfoLookup = $this->contactInfoLookup->generate();
        }

        /* Get ready to remember an indexed set of Value Lists for Students
         */
        $studentLookupConverter = new ValueListConverter();

        /* Spawn a Generator of paginated payloads
         */
        $studentPayloadGenerator = $this->api->retrieve('GET', 'students', self::SCOPE);

        foreach ($studentPayloadGenerator as $studentPage) {
            $studentLookup = $studentLookupConverter->generate($studentPage['value_lists']);

            foreach ($studentPage['data'] as $student) {
                $externalId = trim((string)$student['id']);

                /* Only import for specific users
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                $academicHouse = '';
                $boardingStatus = false;

                /* Maybe the campus node exists...not sure if it ALWAYS exists, or just sometimes.
                 */
                $campusName = $student['campus']['description'] ?? '';

                $dob = trim((string)$student['birthday']);
                $email = trim((string)$student['email_1']);

                $firstname = trim((string)$student['first_name']);
                $firstnamePreferred = trim((string)$student['preferred_name']);

                $form = '';
                $gender = trim((string)$studentLookup['gender'][$student['gender']]);
                $lastname = trim((string)$student['last_name']);
                $mobile = '';
                $photoRedacted = false;
                $studiesCode = '';
                $title = '';

                /* $student['homeroom'] is a lookup identifier that might not
                 * actually exist in the lookup itself; Veracross has automated
                 * cleanups which could be turned off, leading to orphaned data.
                 */
                $tutor = [
                    'id' => null,
                    'name' => null
                ];

                if ($student['homeroom_teacher']['id']) {
                    $tutor = [
                        'id' => trim((string)$student['homeroom_teacher']['id']),
                        'name' => trim((string)$student['homeroom_teacher']['name']),
                    ];
                }

                $tutorGroup = trim((string)($studentLookup['homeroom'][$student['homeroom']] ?? ''));

                $username = trim((string)$student['username']);
                $yearLevel = trim((string)$studentLookup['grade_level'][$student['grade_level']]);

                /* Dates
                 */
                $enrolmentDate = trim((string)$student['entry_date']);
                $leavingDate = trim((string)$student['exit_date']);

                /* Flags
                 */
                $esl = (bool)($languageLookup[$externalId] ?? false);
                $ib = false;
                $ilp = false;

                /* Alerts
                 */
                $comments = '';

                /* TODO Find out how a split family is represented in pilot school
                 *
                 * $legal might be inferred from relationships:list,
                 * which provides (bool)legal_custody and (string)custody_status
                 * The relationships lookup specifically filters to legal_custody 1
                 * so anyone appearing here is legally allowed to do so
                 */
                $legal = '';

                /* This student might not have any meaningful medical records
                 */
                $medical = null;

                if (isset($medicalLookup[$student['id']])) {
                    $medical = 'This student has medical conditions.';
                }

                $messages = '';

                /* From Thad, 7/2/23:
                 *     "In the U.S., an Advisor is typically a faculty or staff member who meets with
                 *      the student to monitor their academic success and provide/coordinate support
                 *      services. Most commonly, we see this in Upper school divisions where this
                 *      person is the “College Advisor” and is helping the student track their progress
                 *      toward graduation requirements, apply to colleges/universities, etc."
                 *
                 * Not sure whether this is closer to YLC or Form Teacher, but I'll put it here for now
                 */
                $formTeacher = [
                    'id' => null,
                    'name' => null
                ];

                if ($student['advisor']['id']) {
                    $formTeacher = [
                        'id' => trim((string)$student['advisor']['id']),
                        'name' => trim((string)$student['advisor']['name'])
                    ];
                }

                /* Fish out a staff member via their user code
                 */
                $housemaster = [
                    'id' => null,
                    'name' => null
                ];

                /* Fish out special needs
                 */
                $specialNeeds = null;

                /* Find out a staff member via their user code
                 */
                $yearLevelCoordinator = [
                    'id' => null,
                    'name' => null
                ];

                /* Dates
                 */
                $enrolment = $this->findDate($enrolmentDate);
                $leaving = $this->findDate($leavingDate);

                /* Parents might inadvertently have the same externalId as someone else's
                 * base profile (e.g. parent has same external id as student)
                 *
                 * This is not usually supposed to happen.
                 * some sis implementations let you have broken data in this way
                 *
                 * Try and load a student profile based on external id. If it exists, just reuse
                 * those pieces. If it doesn't exist, first check if there's an existing base
                 * profile before making a whole new one (this is how you end up with fk issues)
                 */
                try {
                    $oldStudentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                    $oldStudentBaseProfile = $oldStudentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $oldStudentProfile = $this->studentProfileMapper->createModel();

                    try {
                        $oldStudentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $oldStudentBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $oldStudentProfile->setBaseProfile($oldStudentBaseProfile);
                }

                $studentBaseProfile = clone $oldStudentBaseProfile;
                $studentProfile = clone $oldStudentProfile;
                $studentProfile->setBaseProfile($studentBaseProfile);

                $studentBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Find their profile pic and assign it, if applicable
                 */
                if ($this->includePhoto) {
                    $this->log->debug(sprintf(
                        'Fetching picture for %s %s (%s)',
                        $studentBaseProfile->getFirstname(),
                        $studentBaseProfile->getLastname(),
                        $studentBaseProfile->getExternalId()
                    ));

                    $this->importPhoto($studentBaseProfile, $photoLookup);
                }

                /* Then save it
                 */
                if ($this->perform) {
                    $this->profileHandler->saveBaseProfile($studentBaseProfile, $oldStudentBaseProfile);
                }

                /* Smoosh it all together
                 */
                $studentProfile
                    ->setUsername($username)
                    ->setAlertComment($comments)
                    ->setAlertLegal($legal)
                    ->setAlertMedical($medical)
                    ->setAlertMessage($messages)
                    ->setCampusName($campusName)
                    ->setEmail($email)
                    ->setEnrolmentDate($enrolment)
                    ->setFormName($form)
                    ->setFormTeacherId($formTeacher['id'])
                    ->setFormTeacherName($formTeacher['name'])
                    ->setGender($gender)
                    ->setHeadOfHouseId($housemaster['id'])
                    ->setHeadOfHouseName($housemaster['name'])
                    ->setHouseName($academicHouse)
                    ->setLeavingDate($leaving)
                    ->setMobile($mobile)
                    ->setPhotoRedacted($photoRedacted)
                    ->setStudiesCode($studiesCode)
                    ->setTutorGroup($tutorGroup)
                    ->setTutorTeacherId($tutor['id'])
                    ->setTutorTeacherName($tutor['name'])
                    ->setYearLevel($yearLevel)
                    ->setYearLevelCoordinatorId($yearLevelCoordinator['id'])
                    ->setYearLevelCoordinatorName($yearLevelCoordinator['name'])

                    /**
                     * Flags
                     */
                    ->setBoardingStatus($boardingStatus)
                    ->setEsl($esl)
                    ->setIb($ib)
                    ->setIndividualLearningPlans($ilp)
                    ->setSpecialNeeds($specialNeeds);

                /* Date of birth requires special treatment as ADF doesn't deal with nulls well
                 */
                if ($dob) {
                    $studentProfile
                        ->setDateOfBirth(new Alaress_Date_Format($dob, 'Y-m-d'));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (\count($emergencyContactsLookup[$student['id']] ?? [])) {
                    if (isset($emergencyContactsLookup[$student['id']][0])) {
                        $fullname = trim(sprintf(
                            '%s %s (%s)',
                            $emergencyContactsLookup[$student['id']][0]['first_name'],
                            $emergencyContactsLookup[$student['id']][0]['last_name'],
                            $emergencyContactsLookup[$student['id']][0]['relationship'],
                        ));

                        $phone = (new FirstMeaningfulValue())->find([
                            $emergencyContactsLookup[$student['id']][0]['mobile_phone'],
                            $emergencyContactsLookup[$student['id']][0]['business_phone'],
                            $emergencyContactsLookup[$student['id']][0]['home_phone'],
                        ]);

                        $studentProfile
                            ->setEmergencyContactName1($fullname)
                            ->setEmergencyContactPhone1($phone);
                    }

                    if (isset($emergencyContactsLookup[$student['id']][1])) {
                        $fullname = trim(sprintf(
                            '%s %s (%s)',
                            $emergencyContactsLookup[$student['id']][1]['first_name'],
                            $emergencyContactsLookup[$student['id']][1]['last_name'],
                            $emergencyContactsLookup[$student['id']][1]['relationship'],
                        ));

                        $phone = (new FirstMeaningfulValue())->find([
                            $emergencyContactsLookup[$student['id']][1]['mobile_phone'],
                            $emergencyContactsLookup[$student['id']][1]['business_phone'],
                            $emergencyContactsLookup[$student['id']][1]['home_phone'],
                        ]);

                        $studentProfile
                            ->setEmergencyContactName2($fullname)
                            ->setEmergencyContactPhone2($phone);
                    }

                    if (isset($emergencyContactsLookup[$student['id']][2])) {
                        $fullname = trim(sprintf(
                            '%s %s (%s)',
                            $emergencyContactsLookup[$student['id']][2]['first_name'],
                            $emergencyContactsLookup[$student['id']][2]['last_name'],
                            $emergencyContactsLookup[$student['id']][2]['relationship'],
                        ));

                        $phone = (new FirstMeaningfulValue())->find([
                            $emergencyContactsLookup[$student['id']][2]['mobile_phone'],
                            $emergencyContactsLookup[$student['id']][2]['business_phone'],
                            $emergencyContactsLookup[$student['id']][2]['home_phone'],
                        ]);

                        $studentProfile
                            ->setEmergencyContactName3($fullname)
                            ->setEmergencyContactPhone3($phone);
                    }
                } else {
                    /* This student doesn't have any emergency contacts anymore
                     * Clear whatever was there
                     */
                    $studentProfile
                        ->setEmergencyContactName1(null)
                        ->setEmergencyContactPhone1(null)
                        ->setEmergencyContactName2(null)
                        ->setEmergencyContactPhone2(null)
                        ->setEmergencyContactName3(null)
                        ->setEmergencyContactPhone3(null);
                }

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding student profile for %s (%s)',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->profileHandler->saveStudentProfile($studentProfile, $oldStudentProfile);
                }

                /* Add their parents' profile data
                 */
                if ($this->validStrats->isFamiliesStratEnabled()) {
                    $this->importParentsOfStudent(
                        $externalId,
                        $studentBaseProfile,
                        $contactInfoLookup,
                        $relationshipLookup,
                        $householdLookup[$student['household_id']] ?? []
                    );
                }
            }
        }
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string|null $date
     * @return Alaress_Date_Format|null
     * @throws \InvalidArgumentException
     */
    public function findDate(?string $date): ?Alaress_Date_Format
    {
        /* These don't have any timezone data, specifically
         */
        if ($date) {
            return (new \Alaress_Date_Format($date, \Alaress_Date_Format::DATABASE_DATE_FORMAT));
        }

        return null;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string $studentExternalId
     * @param Model  $studentBaseProfile
     * @param array  $contactInfoLookup
     * @param array  $relationshipLookup
     * @param array  $household
     * @throws DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(
        string $studentExternalId,
        UserProfile\Model $studentBaseProfile,
        array $contactInfoLookup,
        array $relationshipLookup,
        array $household
    ): void {
        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($household as $parent) {
            $externalId = trim((string)$parent['id']);
            $email = trim((string)$parent['email_1']);

            /* I guess we can forget about first_nick_name
             * Not sure you'd refer to someone like that in our world?
             * (In test data nickname is the same as firstname anyway)
             */
            $firstname = trim((string)$parent['first_name']);
            $firstnamePreferred = trim((string)$parent['preferred_name']);

            $lastname = trim((string)$parent['last_name']);
            $phoneHome = trim((string)($contactInfoLookup[$parent['id']]['home_phone'] ?? ''));
            $phoneMobile = trim((string)($contactInfoLookup[$parent['id']]['mobile_phone'] ?? ''));
            $phoneWork = trim((string)($contactInfoLookup[$parent['id']]['business_phone'] ?? ''));

            /* Dig out some info about these guys' relationships to a student
             */
            $relationship = trim((string)($relationshipLookup[$studentExternalId][$externalId]['relationship'] ?? ''));

            $title = trim((string)$parent['name_prefix']);
            $username = trim((string)$parent['username']);

            /* Parents might inadvertently have the same externalId as someone else's
             * base profile (e.g. parent has same external id as student)
             *
             * This is not usually supposed to happen.
             * some sis implementations let you have broken data in this way
             *
             * Try and load a parent profile based on external id. If it exists, just reuse
             * those pieces. If it doesn't exist, first check if there's an existing base
             * profile before making a whole new one (this is how you end up with fk issues)
             */
            try {
                $oldParentProfile = $this->parentProfileMapper->findByExternalId($externalId);
                $oldParentBaseProfile = $oldParentProfile->getBaseProfile();
            } catch (Exception $e) {
                $oldParentProfile = $this->parentProfileMapper->createModel();

                try {
                    $oldParentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                } catch (Exception $e) {
                    $oldParentBaseProfile = $this->baseProfileMapper->createModel();
                }

                $oldParentProfile->setBaseProfile($oldParentBaseProfile);
            }

            $parentBaseProfile = clone $oldParentBaseProfile;
            $parentProfile = clone $oldParentProfile;
            $parentProfile->setBaseProfile($parentBaseProfile);

            $parentBaseProfile
                ->setExternalId($externalId)
                ->setTitle($title)
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

            /* Then save it
             */
            if ($this->perform) {
                $this->profileHandler->saveBaseProfile($parentBaseProfile, $oldParentBaseProfile);
            }

            $parentProfile
                ->setPhoneHome($phoneHome)
                ->setPhoneWork($phoneWork)
                ->setMobile($phoneMobile)
                ->setEmail($email)
                ->setUsername($username);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding parent profile for %s (%s)',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->profileHandler->saveParentProfile($parentProfile, $oldParentProfile);
            }

            /* Manufacture relations
             */

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding relation between %s, the %s of %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                trim($relationship),
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $relationship = $this->relationshipMapper->createModel()
                    ->setUserProfile($parentBaseProfile)
                    ->setChildProfile($studentBaseProfile)
                    ->setType($relationship);

                $this->relationshipMapper->save($relationship);
            }
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        /* Prefetch a list of contact info, which contain phone numbers
         */
        $this->log->debug('Prefetching contact info...');
        $contactInfoLookup = $this->contactInfoLookup->generate();

        /* Get ready to remember an indexed set of Value Lists for Students
         */
        $staffLookupConverter = new ValueListConverter();

        /* Spawn a Generator of paginated payloads
         */
        $staffPayloadGenerator = $this->api->retrieve('GET', 'staff_faculty', self::SCOPE);

        foreach ($staffPayloadGenerator as $staffPage) {
            $staffLookup = $staffLookupConverter->generate($staffPage['value_lists']);

            foreach ($staffPage['data'] as $staff) {
                $externalId = trim((string)$staff['id']);

                /* Only import for specific users
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                $firstname = trim((string)$staff['first_name']);
                $firstnamePreferred = trim((string)$staff['preferred_name']);

                $lastname = trim((string)$staff['last_name']);
                $building = '';
                $campusName = trim((string)$staffLookup['campus'][$staff['campus']]);
                $category = trim((string)$staffLookup['faculty_type'][$staff['faculty_type']]);
                $department = trim((string)$staffLookup['department'][$staff['department']]);
                $email = trim((string)$staff['email_1']);
                $house = '';
                $phone = trim((string)($contactInfoLookup[$staff['id']]['business_phone'] ?? ''));
                $positionTitle = trim((string)$staff['job_title']);
                $room = '';
                $title = trim((string)$staffLookup['name_prefix'][$staff['name_prefix']]);
                $username = trim((string)$staff['username']);

                /* Find out if this externalId already has a staff profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $oldStaffProfile = $this->staffProfileMapper->findByExternalId($externalId);
                    $oldStaffBaseProfile = $oldStaffProfile->getBaseProfile();
                } catch (Exception $e) {
                    $oldStaffProfile = $this->staffProfileMapper->createModel();

                    try {
                        $oldStaffBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $oldStaffBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $oldStaffProfile->setBaseProfile($oldStaffBaseProfile);
                }

                $staffBaseProfile = clone $oldStaffBaseProfile;
                $staffProfile = clone $oldStaffProfile;
                $staffProfile->setBaseProfile($staffBaseProfile);

                $staffBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Then save it
                 */
                if ($this->perform) {
                    $this->profileHandler->saveBaseProfile($staffBaseProfile, $oldStaffBaseProfile);
                }

                $staffProfile
                    ->setBuilding($building)
                    ->setCampusName($campusName)
                    ->setCategory($category)
                    ->setDepartment($department)
                    ->setEmail($email)
                    ->setHouseName($house)
                    ->setPhone($phone)
                    ->setPositionTitle($positionTitle)
                    ->setRoom($room)
                    ->setUsername($username);

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding staff profile for %s (%s)',
                    trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}"),
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->profileHandler->saveStaffProfile($staffProfile, $oldStaffProfile);
                }
            }
        }
    }


    /**
     * Import a profile picture for the given profile
     *
     * @param UserProfile\Model $baseProfile
     * @param array|null $photoLookup
     * @return void
     * @throws GuzzleException
     */
    private function importPhoto(UserProfile\Model $baseProfile, ?array $photoLookup): void
    {
        $externalId = $baseProfile->getExternalId();

        /* This person doesn't have a photo,
         * or, their existing picture doesn't need changing.
         * Exit early
         */
        if (null === ($photoLookup[$externalId] ?? null)) {
            $this->log->debug(sprintf(
                'No changes required for user %s; they already have the latest, or no pic exists',
                $externalId
            ));

            return;
        }

        /* Start the part where we save the image
         */
        $this->log->debug(sprintf(
            'Importing profile picture for %s %s (%s)',
            $baseProfile->getFirstname(),
            $baseProfile->getLastname(),
            $baseProfile->getExternalId()
        ));

        /* Only try to persist images if perform mode is activated
         */
        if (!$this->perform) {
            return;
        }

        /* Otherwise, all the parts are here and valid; continue with the persistence
         * to storage and assignation to the $baseProfile by reference
         */
        try {
            $response = $this->client->request('GET', $photoLookup[$externalId]);
            $fileContents = $response->getBody()->getContents();

            $portrait = $this->storageMapper->acceptStream($baseProfile->getExternalId() . '.jpg', $fileContents);

            /* You get a picture if any of the following occur:
             *     - you don't have a portrait yet; you can get one added
             *     - your existing picture is different to the one we just got; update it
             */
            if (!$baseProfile->getPortrait() || ($baseProfile->getPortrait()->getHash() !== $portrait->getHash())) {
                $baseProfile->setPortrait($portrait);
            }
        } catch (\Exception $e) {
            // noop, but warn the cli that something unusual has transpired
            $this->log->warning(sprintf(
                'Profile picture: exception thrown. %s',
                $e->getMessage()
            ));
        }
    }
}
