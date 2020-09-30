<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{

    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * Phone numbers exist inside these array keys
     */
    private const HOME_TELEPHONE_NUMBER = 'Home Telephone Number';
    private const WORK_TELEPHONE_NUMBER = 'Work Telephone Number';
    private const MOBILE_TELEPHONE_NUMBER = 'Mobile';


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
     * @var Api
     */
    private $api;


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
     * Profile constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param Api                             $api
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
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

        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = 'enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms';

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $student) {
                $externalId = (string)$student['attributes']['studentCode'];
                $academicHouse = '';
                $boardingStatus = false;
                $campusName = '';
                $comments = '';
                $dob = (string)$student['relationships']['person']['data']['attributes']['dateOfBirth'];
                $email = (string)$student['relationships']['person']['data']['attributes']['email'];
                $firstname = (string)$student['relationships']['person']['data']['attributes']['firstName'];
                $form = '';
                $gender = (string)$student['relationships']['person']['data']['attributes']['gender'];
                $lastname = (string)$student['relationships']['person']['data']['attributes']['lastName'];
                $legal = '';
                $medicalAlert = '';
                $messages = '';
                $mobile = '';
                $studiesCode = '';
                $title = (string)$student['relationships']['person']['data']['attributes']['title'];
                $tutor = '';
                $tutorGroup = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['tutorGroup'];
                $username = '';
                $yearLevel = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['schoolYear'];

                /* Dates
                 */
                $enrolmentDate = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['startDate'];
                $leavingDate = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['endDate'];

                /* Flags
                 */
                $esl = false;
                $ib = false;
                $ilp = false;
                $specialNeeds = false;

                /* Find out if this externalId already has a student profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                    $studentBaseProfile = $studentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $studentProfile = $this->studentProfileMapper->createModel();
                    $studentBaseProfile = $this->baseProfileMapper->createModel();

                    $studentProfile->setBaseProfile($studentBaseProfile);
                }

                $studentBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setLastname($lastname);

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding base profile for %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($studentBaseProfile);
                }

                /*  Maintain the methods' return signature for easiest resolution
                 *  though you are free to customise it as you see fit
                 */

                /* Fish out alert comments
                 */
                $alertComments = $this->findComments();

                /* Fish out legal alerts
                 */
                $alertLegal = $this->findLegal();

                /* Fish out campus name
                 */
                $campusName = $this->findCampusName();

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($student['relationships']['contacts']['data']);

                /* Find out a staff member via their user code
                 */
                $formTeacher = $this->findFormTeacher();

                /* Fish out a staff member via their user code
                 */
                $housemaster = $this->findHousemaster();

                /* Fish out special needs
                 */
                $specialNeeds = $this->findSpecialNeeds();

                /* Fish out a staff member via their user code
                 */
                $tutor = $this->findTutor();

                /* Find out a staff member via their user code
                 */
                $yearLevelCoordinator = $this->findYearLevelCoordinator();

                /* Dates
                 */
                $enrolment = $this->findDate($enrolmentDate);
                $leaving = $this->findDate($leavingDate);

                /* Smoosh it all together
                 */
                $studentProfile
                    ->setUsername($username)
                    ->setAlertComment($alertComments)
                    ->setAlertLegal($alertLegal)
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

//                /* If this student was processed earlier, we don't need to do it again
//                 * These fetch data from specific endpoints so we can save ourselves some
//                 * api requests which result in the same outcome
//                 *
//                 * (the alternative is to build a lookup after downloading their db table...)
//                 */
//                if (!isset($this->encounters[$externalId])) {
//
//                    /* Fish out medical alerts
//                     */
//                    $studentProfile->setAlertMedical($this->findMedicalAlert($studentBaseProfile, $studentPersonRelation));
//                }

                /* Date of birth requires special treatment as ADF doesn't deal with nulls well
                 */
                if ($dob) {
                    $studentProfile
                        ->setDateOfBirth($this->findDate($dob));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (\count($emergencyContacts)) {
                    if (isset($emergencyContacts[0])) {
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContacts[0]['name'])
                            ->setEmergencyContactPhone1($emergencyContacts[0]['phone'])
                            ->setEmergencyContactPhoneAlt1($emergencyContacts[0]['phoneAlt']);
                    }

                    if (isset($emergencyContacts[1])) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContacts[1]['name'])
                            ->setEmergencyContactPhone2($emergencyContacts[1]['phone'])
                            ->setEmergencyContactPhoneAlt2($emergencyContacts[1]['phoneAlt']);
                    }

                    if (isset($emergencyContacts[2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts[2]['name'])
                            ->setEmergencyContactPhone3($emergencyContacts[2]['phone'])
                            ->setEmergencyContactPhoneAlt3($emergencyContacts[2]['phoneAlt']);
                    }

                    $this->log->debug(sprintf(
                        'Found %s emergency contacts for %s (user %s)',
                        \count($emergencyContacts),
                        trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                        $studentBaseProfile->getExternalId()
                    ));
                }

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding student profile for %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->studentProfileMapper->save($studentProfile);
                }

                /* Add their parents' profile data
                 * This set is mixed with emergency contacts and other non-data, so
                 * sift through it all manually for the gems you need
                 */
                $this->importParentsOfStudent($externalId, $studentBaseProfile, $student['relationships']['contacts']['data']);
            }

            /* Prepare for next page if it exists
             */
            $nextUrl =  $payload['links']['next'] ?? null;
        }
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @return string|null
     */
    private function findCampusName(): ?string
    {
        return null;
    }


    /**
     * Find comments info for a given student
     *
     * @return string
     */
    private function findComments(): string
    {
        return '';
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string $date
     * @return Alaress_Date_Format|null
     */
    public function findDate(string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


    /**
     * Decide if $id is a derived (and non-followable) id
     *
     * @param string $id
     * @return bool
     */
    private function isDerivedId(string $id): bool
    {
        return strpos($id, 'derived') !== false;
    }


    /**
     * Decide if a record is a Parent, or an Emergency Contact
     * They seem to arrive in one big pile so you have to sift
     * through manually to separate Parents from Emergency Contacts
     *
     * @param array $contact
     * @return bool
     */
    private function isParent(array $contact): bool
    {
        return '' === trim((string)$contact['attributes']['isEmergencyContact']);
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param array             $contacts
     * @return array|null
     * @throws \InvalidArgumentException
     */
    public function findEmergencyContacts(array $contacts): ?array
    {
        $emergencyContacts = [];

        foreach ($contacts as $contact) {

            /* Derived Ids have no chance of succeeding despite their api
             * providing you links containing these ids (must be ints)
             */
            if ($this->isDerivedId($contact['id'])) {
                continue;
            }

            /* Check if this person has isEmergencyContact set
             * If it doesn't, it's not an emergency contact
             */
            if ($this->isParent($contact)) {
                continue;
            }

            /* Otherwise this is an emergency contact so stack it up
             *
             * Go digging in another endpoint for names and numbers
             * Construct the endpoint manually, because you can't trust the ids
             * provided by the API (some are non-integers which die)
             */
            $endpoint = "enrolments/person/{$contact['attributes']['personId']}?include=phoneNumbers";

            $payload = $this->api->retrieveDocument($endpoint);

            $firstname = $payload['data']['attributes']['firstName'];
            $lastname  = $payload['data']['attributes']['lastName'];

            /* Catalog each of this guy's phone numbers so we can try and assign some
             */
            $numbers = [];

            if (isset($parent['data']['relationships']['phoneNumbers'])) {
                foreach ($payload['data']['relationships']['phoneNumbers']['data'] as $phoneNumber) {
                    $numbers[$phoneNumber['attributes']['typeName']] = $phoneNumber['attributes']['number'];
                }
            }

            /* Presume some implementation details about typeName being hardcoded
             * Find the first non-null value suitable for phone and phoneAlt fields
             */
            $emergencyContacts[] = [
                'name'  => trim("{$firstname} {$lastname}"),
                'phone' => $numbers[self::MOBILE_TELEPHONE_NUMBER] ?? $numbers[self::HOME_TELEPHONE_NUMBER] ?? '',
                'phoneAlt' => $numbers[self::WORK_TELEPHONE_NUMBER] ?? ''
            ];
        }

        return $emergencyContacts;
    }

    /**
     * Find a form teacher from their form code
     *
     * @return array
     */
    private function findFormTeacher(): array
    {
        /* TODO Fill in these form teacher vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a housemaster from their house name
     *
     * @return array
     */
    private function findHousemaster(): array
    {
        /* TODO Fill in these housemaster vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find legal alerts
     *
     * @return string|null
     */
    private function findLegal(): ?string
    {
        /* TODO Fill in these legal alert vars
         */
        return null;
    }


    /**
     * Find medical data for student
     *
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $studentPersonRelation
     * @return string|null
     * @throws \InvalidArgumentException
     */
    private function findMedicalAlert(UserProfile\Model $studentBaseProfile, array $studentPersonRelation): ?string
    {

        /* Find another endpoint who can tell us the answer
         */
        $endpoint = $studentPersonRelation['relationships']['studentPerson']['data']['links']['medicalConditions'];

        /* No endpoint? No way to find out more
         * Just exit, there's no link to medical conditions
         */
        if ('' === (string)$endpoint) {
            return null;
        }

        /* Otherwise keep digging
         */
        $payload = $this->api->retrieveDocument($endpoint);

        $numConditions = \count($payload['data']);

        $this->log->debug(sprintf(
            'Secondary api inspection at %s for medical conditions belonging to %s (user %s) - found %s',
            $endpoint,
            trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
            $studentBaseProfile->getExternalId(),
            $numConditions
        ));

        if ($numConditions) {
            return 'This student has medical conditions.';
        }

        return '';
    }


    /**
     * Find out if this student has special needs
     *
     * @return bool
     */
    private function findSpecialNeeds(): bool
    {
        /* TODO Fill in these special needs vars
         */
        return false;
    }


    /**
     * Find a tutor from their id
     *
     * @return array
     */
    private function findTutor(): array
    {
        /* TODO Fill in these tutor vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a year level coordinator from their year level
     *
     * @return array
     */
    private function findYearLevelCoordinator(): array
    {
        /* TODO Fill in these ylc vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string            $studentExternalId
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $contacts
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile, array $contacts): void
    {
        /* Each student comes with a set of Contacts
         * Some are Emergency Contacts, some are not
         */
        foreach ($contacts as $contact) {

            /* Derived Ids have no chance of succeeding despite their api
             * providing you links containing these ids (must be ints)
             */
            if ($this->isDerivedId($contact['id'])) {
                continue;
            }

            /* Check if this person has isEmergencyContact set
             * If it does, it's an emergency contact and we don't need it
             */
            if (!$this->isParent($contact)) {
                continue;
            }

            /* Go digging in another endpoint for data
             */
            $endpoint = "enrolments/person/{$contact['attributes']['personId']}?include=phoneNumbers";

            $parent = $this->api->retrieveDocument($endpoint);

            /* Catalog each of this guy's phone numbers so we can try and assign some
             */
            $numbers = [];

            if (isset($parent['data']['relationships']['phoneNumbers'])) {
                foreach ($parent['data']['relationships']['phoneNumbers']['data'] as $phoneNumber) {
                    $numbers[$phoneNumber['attributes']['typeName']] = $phoneNumber['attributes']['number'];
                }
            }

            /* For every associated parent, find out if they have a parent profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            $externalId = (string)$parent['data']['attributes']['externalId'];
            $email = (string)$parent['data']['attributes']['email'];
            $firstname = (string)$parent['data']['attributes']['firstName'];
            $lastname = (string)$parent['data']['attributes']['lastName'];
            $phoneHome = $numbers[self::HOME_TELEPHONE_NUMBER] ?? '';
            $phoneMobile = $numbers[self::MOBILE_TELEPHONE_NUMBER] ?? '';
            $phoneWork = $numbers[self::WORK_TELEPHONE_NUMBER] ?? '';
            $relationship = (string)$contact['attributes']['relationName'];
            $title = (string)$parent['data']['attributes']['title'];
            $username = '';

            if ('' === trim($externalId)) {
                $this->log->warning(sprintf(
                    'External Id for parent %s is empty; skipping',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Parents might inadvertently have the same externalId as someone else's
             * base profile (e.g. parent has same external id as student)
             *
             * This is not usually supposed to happen.
             * sentral lets you have broken data in this way
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

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding base profile for %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
            ));

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
                'Adding parent profile for %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
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

        /** @var Staff $staff */
        foreach ($staffs as $staff) {

            /* TODO Fill in basic staff vars
             */
            $externalId = '';
            $username = '';
            $title = '';
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

            /* Staff without usernames are skipped
             */
            if ('' === $username) {
                $this->log->warning(sprintf(
                    'Skipping base profile for staff %s - no username detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Find out if this externalId already has a staff profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
                $staffBaseProfile = $staffProfile->getBaseProfile();
            } catch (Exception $e) {
                $staffProfile = $this->staffProfileMapper->createModel();
                $staffBaseProfile = $this->baseProfileMapper->createModel();

                $staffProfile->setBaseProfile($staffBaseProfile);
            }

            $staffBaseProfile
                ->setExternalId($externalId)
                ->setTitle($title)
                ->setFirstname($firstname)
                ->setLastname($lastname);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding base profile for %s',
                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
            ));

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
                'Adding staff profile for %s',
                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->staffProfileMapper->save($staffProfile);
            }
        }
    }
}
