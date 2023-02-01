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
use RuntimeException;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\Household;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup\EmergencyContact;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\ValueListConverter;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper;
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
     * emergency_contacts:list
     * parents:list
     */
    public const SCOPE = 'students:list parents:list';


    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


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
     * Profile constructor.
     *
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
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Import students' profile data
         * Import their specific parents' profile data
         */
        $this->log->info('Processing students and their parents.');

        $this->importStudents();

//        /* Import staff profile data
//         */
//        $this->log->info('Processing staff.');
//
//        $this->importStaff();
    }


    /**
     * Import students' profile data
     */
    private function importStudents(): void
    {
        /* Prefetch a list of households, which contain Parents
         */
        $householdLookup = (new Household($this->api))->generate();

        /* Prefetch a list of emergency contacts
         */
        $emergencyContactsLookup = (new EmergencyContact($this->api))->generate();

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

                $dob = $this->findDate($student['birthday']);
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

                /* Skip this if it's specifically id 0 (Mone)
                 */
                $tutorId = '';
                $tutorName = '';

                if ($student['homeroom_teacher']['id']) {
                    $tutorId = trim((string)$student['homeroom_teacher']['id']);
                    $tutorName = trim((string)$student['homeroom_teacher']['name']);
                }

                $tutorGroup = trim((string)$studentLookup['homeroom'][$student['homeroom']]);
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
                $legal = '';
                $medical = '';
                $messages = '';

//                /* TODO Customise these find* methods to suit your implementation
//                 *  Maintain the methods' return signature for easiest resolution
//                 *  though you are free to customise it as you see fit
//                 */
//
//                /* Emergency Contacts
//                 */
//                $emergencyContacts = $this->findEmergencyContacts();
//
                /* Find out a staff member via their user code
                 */
                $formTeacher = [];

                /* Fish out a staff member via their user code
                 */
                $housemaster = [];

                /* Fish out special needs
                 */
                $specialNeeds = null;

                /* Find out a staff member via their user code
                 */
                $yearLevelCoordinator = [];

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
                    ->setFormTeacherId($formTeacher['id'] ?? '')
                    ->setFormTeacherName($formTeacher['name'] ?? '')
                    ->setGender($gender)
                    ->setHeadOfHouseId($housemaster['id'] ?? '')
                    ->setHeadOfHouseName($housemaster['name'] ?? '')
                    ->setHouseName($academicHouse)
                    ->setLeavingDate($leaving)
                    ->setMobile($mobile)
                    ->setPhotoRedacted($photoRedacted)
                    ->setStudiesCode($studiesCode)
                    ->setTutorGroup($tutorGroup)
                    ->setTutorTeacherId($tutorId)
                    ->setTutorTeacherName($tutorName)
                    ->setYearLevel($yearLevel)
                    ->setYearLevelCoordinatorId($yearLevelCoordinator['id'] ?? '')
                    ->setYearLevelCoordinatorName($yearLevelCoordinator['name'] ?? '')

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
                        ->setDateOfBirth(new Alaress_Date_Format($dob, 'Ymd'));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (\count($emergencyContacts)) {
                    if (isset($emergencyContacts[0])) {
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContacts[0]['name'])
                            ->setEmergencyContactPhone1($emergencyContacts[0]['phone']);
                    }

                    if (isset($emergencyContacts[1])) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContacts[1]['name'])
                            ->setEmergencyContactPhone2($emergencyContacts[1]['phone']);
                    }

                    if (isset($emergencyContacts[2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts[2]['name'])
                            ->setEmergencyContactPhone3($emergencyContacts[2]['phone']);
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
                $this->importParentsOfStudent($externalId, $studentBaseProfile, $householdLookup);
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
     * Find all the meaningful emergency contacts for a student
     *
     * @return array
     */
    public function findEmergencyContacts(): array
    {
        $emergencyContacts = [];
        $contacts = [];
        $n = 0;

        foreach ($emergencyContacts as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                /* TODO Fill in these emergency contact vars
                 */
                $fullname = '';
                $relation = '';
                $phone = '';

                /* Suffix the $relation if provided
                 */
                if ('' !== $relation) {
                    $fullname .= " ({$relation})";
                }

                /* Aggregate them if we have a name
                 */
                if ('' !== $fullname) {
                    $contacts[] = [
                        'name' => $fullname,
                        'phone' => $phone
                    ];
                }
            }

            $n++;
        }

        return $contacts;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string $studentExternalId
     * @param Model  $studentBaseProfile
     * @param array  $householdLookup
     * @throws Exception
     * @throws DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(
        string $studentExternalId,
        UserProfile\Model $studentBaseProfile,
        array $householdLookup
    ): void {
        $parents = [];

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {
            /* TODO Fill in these basic parent vars
             */
            $externalId = '';
            $email = '';
            $firstname = '';
            $lastname = '';
            $phoneHome = '';
            $phoneMobile = '';
            $phoneWork = '';
            $relationship = '';
            $title = '';
            $username = '';

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
        $staffs = [];

        foreach ($staffs as $staff) {
            /* TODO Fill in basic staff vars
             */
            $externalId = '';

            $firstname = '';
            $lastname = '';
            $building = '';
            $campusName = '';
            $category = '';
            $department = '';
            $email = '';
            $phone = '';
            $positionTitle = '';
            $room = '';
            $title = '';
            $username = '';

            /* Find out if this externalId already has a staff profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
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
