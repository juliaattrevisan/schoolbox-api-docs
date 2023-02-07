<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ContactInfo;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Health;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Household;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\ValueListConverter;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
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
     * (perhaps) could work on subdividing or unifying this further)
     *
     * Required scopes:
     *
     * students:list
     * emergency_contacts:list OR relationships:list
     * health.patients:list
     * parents:list
     * staff_faculty:list
     * contact_info:list
     */
    public const SCOPE = 'students:list health.patients:list parents:list staff_faculty:list contact_info:list';


    /**
     * @var ValidStrats
     */
    private ValidStrats $validStrats;


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
     * @var IApi
     */
    private IApi $api;


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
     * This lookup could be shared between student/parent/staff imports
     * depending on your strat selection for segregated imports
     *
     * @var ContactInfo
     */
    private ContactInfo $contactInfoLookup;


    /**
     * Profile constructor.
     *
     * @param ValidStrats                     $validStrats
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param IApi                            $api
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        ValidStrats $validStrats,
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->validStrats = $validStrats;
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        /* Shared lookups
         */
        $this->contactInfoLookup = new ContactInfo($this->api);
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
        /* Prefetch a list of households, which contain Parents
         */
        $this->log->debug('Prefetching households...');
        $householdLookup = (new Household($this->api))->generate();

        /* Prefetch a list of emergency contacts
         * TODO: We don't have perms yet; need Veracross to let us in
         * $emergencyContactsLookup = (new EmergencyContact($this->api))->generate();
         */
        $this->log->debug('Prefetching emergency contacts...');
        $emergencyContactsLookup = [];

        /* Prefetch a list of medically-afflicted dudes
         */
        $this->log->debug('Prefetching medicals...');
        $medicalLookup = (new Health($this->api))->generate();

        /* Prefetch a list of contact info, which contain phone numbers
         * This generally only applies to Parents and Staff
         */
        $contactInfoLookup = [];

        if ($this->validStrats->isFamiliesStratEnabled()) {
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
                $academicHouse = '';
                $boardingStatus = false;
                $campusName = '';

                $dob = trim((string)$student['birthday']);
                $email = trim((string)$student['email_1']);

                $firstname = trim((string)$student['first_name']);
                $firstnamePreferred = (new FirstMeaningfulValue())->find([
                    $student['preferred_name'],
                   $firstname,
                ]);

                $form = '';
                $gender = trim((string)$studentLookup['gender'][$student['gender']]);
                $lastname = trim((string)$student['last_name']);
                $mobile = '';
                $photoRedacted = false;
                $studiesCode = '';
                $title = '';

                /* TODO: Maybe Skip this if it's specifically id 0 (None)
                 *  These lookups are user configurable though
                 */
                $tutorId = trim((string)$student['homeroom_teacher']['id']);
                $tutorName = trim((string)$student['homeroom_teacher']['name']);

                /* $student['homeroom'] is a lookup identifier that might not
                 * actually exist in the lookup itself
                 */
                $tutorGroup = trim((string)($studentLookup['homeroom'][$student['homeroom']] ?? ''));

                $username = trim((string)$student['username']);
                $yearLevel = trim((string)$studentLookup['grade_level'][$student['grade_level']]);

                $enrolmentDate = trim((string)$student['entry_date']);
                $leavingDate = trim((string)$student['exit_date']);

                /* Flags
                 */
                $esl = false;
                $ib = false;
                $ilp = false;

                /* Alerts
                 */
                $comments = '';

                /* TODO Need relationships:list scope
                 *  $legal might be inferred from relationships:list,
                 *  which provides (bool)legal_custody and (string)custody_status
                 */
                $legal = '';

                $medical = $medicalLookup[$student['id']] ? 'This student has medical conditions.' : null;
                $messages = '';

                /* Find out a staff member via their user code
                 */
                $formTeacher = [
                    'id' => null,
                    'name' => null
                ];

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
                    $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                    $studentBaseProfile = $studentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $studentProfile = $this->studentProfileMapper->createModel();

                    try {
                        $studentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $studentBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $studentProfile->setBaseProfile($studentBaseProfile);
                }

                $studentBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($studentBaseProfile);
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
                    ->setTutorTeacherId($tutorId)
                    ->setTutorTeacherName($tutorName)
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
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContactsLookup[$student['id']][0]['name'])
                            ->setEmergencyContactPhone1($emergencyContactsLookup[$student['id']][0]['phone']);
                    }

                    if (isset($emergencyContactsLookup[$student['id']][1])) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContactsLookup[$student['id']][1]['name'])
                            ->setEmergencyContactPhone2($emergencyContactsLookup[$student['id']][1]['phone']);
                    }

                    if (isset($emergencyContactsLookup[$student['id']][2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContactsLookup[$student['id']][2]['name'])
                            ->setEmergencyContactPhone3($emergencyContactsLookup[$student['id']][2]['phone']);
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
                    $this->studentProfileMapper->save($studentProfile);
                }

                /* Add their parents' profile data
                 */
                if ($this->validStrats->isFamiliesStratEnabled()) {
                    $this->importParentsOfStudent($externalId, $studentBaseProfile, $contactInfoLookup, $householdLookup[$student['household_id']] ?? []);
                }
            }
        }
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string|null $date
     * @return Alaress_Date_Format|null
     */
    public function findDate(?string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string $studentExternalId
     * @param Model  $studentBaseProfile
     * @param array  $contactInfoLookup
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
            $firstnamePreferred = (new FirstMeaningfulValue())->find([
                $parent['preferred_name'],
                $firstname
            ]);

            $lastname = trim((string)$parent['last_name']);
            $phoneHome = trim((string)$contactInfoLookup[$parent['id']]['home_phone']);
            $phoneMobile = trim((string)$contactInfoLookup[$parent['id']]['mobile_phone']);
            $phoneWork = trim((string)$contactInfoLookup[$parent['id']]['business_phone']);

            /* TODO Need relationships:list scope
             *  $relationship might be inferred from relationships:list,
             *  which provides a Value List
             */
            $relationship = '';

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
                $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
                $parentBaseProfile = $parentProfile->getBaseProfile();
            } catch (Exception $e) {
                $parentProfile = $this->parentProfileMapper->createModel();

                try {
                    $parentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                } catch (Exception $e) {
                    $parentBaseProfile = $this->baseProfileMapper->createModel();
                }

                $parentProfile->setBaseProfile($parentBaseProfile);
            }

            $parentBaseProfile
                ->setExternalId($externalId)
                ->setTitle($title)
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

            /* Then save it
             */
            if ($this->perform) {
                $this->baseProfileMapper->save($parentBaseProfile);
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
                $this->parentProfileMapper->save($parentProfile);
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

                $firstname = trim((string)$staff['first_name']);
                $firstnamePreferred = (new FirstMeaningfulValue())->find([
                    $staff['preferred_name'],
                    $firstname
                ]);

                $lastname = trim((string)$staff['last_name']);
                $building = '';
                $campusName = trim((string)$staffLookup['campus'][$staff['campus']]);
                $category = trim((string)$staffLookup['faculty_type'][$staff['faculty_type']]);
                $department = trim((string)$staffLookup['department'][$staff['department']]);
                $email = trim((string)$staff['email_1']);
                $phone = trim((string)$contactInfoLookup[$staff['id']]['business_phone']);
                $positionTitle = trim((string)$staff['job_title']);
                $room = '';
                $title = trim((string)$staffLookup['name_prefix'][$staff['name_prefix']]);
                $username = trim((string)$staff['username']);

                /* Find out if this externalId already has a staff profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $staffProfile     = $this->staffProfileMapper->findByExternalId($externalId);
                    $staffBaseProfile = $staffProfile->getBaseProfile();
                } catch (Exception $e) {
                    $staffProfile = $this->staffProfileMapper->createModel();

                    try {
                        $staffBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $staffBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $staffProfile->setBaseProfile($staffBaseProfile);
                }

                $staffBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($staffBaseProfile);
                }

                $staffProfile
                    ->setBuilding($building)
                    ->setCampusName($campusName)
                    ->setCategory($category)
                    ->setDepartment($department)
                    ->setEmail($email)
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
                    $this->staffProfileMapper->save($staffProfile);
                }
            }
        }
    }
}
