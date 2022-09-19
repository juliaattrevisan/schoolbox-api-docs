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
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
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
     * These consts, used to denote what type of person we're processing,
     * also help us decide which timetable-lessons endpoint to access later
     */
    public const TYPE_STUDENT = 'student';
    public const TYPE_STAFF = 'staff';


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
     * This relationshipName identifies a contact as a sibling
     * This might be configurable in sentral, though
     */
    private const RELATIONSHIP_SIBLING = 'Sibling';


    /**
     * Employment status for departed staff: if a staff member is departed, they
     * should not be synced.
     */
    private const STAFF_EMPLOYMENT_STATUS_DEPARTED = 'left';


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
     * @var Registry
     */
    private Registry $registry;


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
     * @param Registry                        $registry
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
        Registry $registry,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;

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

        /* Import staff profile data
         */
        $this->log->info('Processing staff.');

        $this->importStaff();
    }


    /**
     * Import students' profile data
     * @throws Exception
     * @throws DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importStudents(): void
    {
        /* Constrain to academic periods relevant to today's date
         */
        $today = date('Y-m-d');
        $academicPeriodIds = $this->findAcademicPeriodIds($today);

        if (!\count($academicPeriodIds)) {
            $this->log->warning(sprintf(
                'No academic periods found for %s; exiting',
                $today
            ));

            exit();
        }

        /* Collapse this var's contents; we won't use it again
         */
        $academicPeriodIds = implode(',', $academicPeriodIds);

        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = "enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms&academicPeriodIds={$academicPeriodIds}&limit=200";

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $student) {
                $externalId = (string)$student['attributes']['studentCode'];
                $boardingHouseName = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['boardingHouse'];
                $boardingStatus = (bool)$student['relationships']['primaryEnrolment']['data']['attributes']['isBoarding'];
                $campusName = '';
                $comments = '';
                $dob = (string)$student['relationships']['person']['data']['attributes']['dateOfBirth'];
                $email = (string)$student['relationships']['person']['data']['attributes']['email'];

                /* Find the first meaningful value out of this set
                 */
                $firstname = (new FirstMeaningfulValue())->find([
                    $student['relationships']['person']['data']['attributes']['firstName'],
                    $student['relationships']['person']['data']['attributes']['givenNames']
                ]);

                $firstnamePreferred = trim((string)$student['relationships']['person']['data']['attributes']['preferredName']);

                $form = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['rollClass'];
                $gender = (string)$student['relationships']['person']['data']['attributes']['gender'];
                $lastname = (string)$student['relationships']['person']['data']['attributes']['lastName'];
                $legal = '';
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

                /* A shortcut for convenience in logging
                 * (Sometimes people don't have all the parts of their name; odd but ok)
                 */
                $fullname = trim("{$firstname} {$lastname}");

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

                /*  Maintain the methods' return signature for easiest resolution
                 *  though you are free to customise it as you see fit
                 */

                /* Fish out a House
                 */
                $academicHouse = $this->findHouse($studentBaseProfile, $student);

                /* Fish out alert comments
                 */
                $alertComments = $this->findComments();

                /* Fish out legal alerts
                 */
                $alertLegal = $this->findLegal();

                /* Fish out medical alerts
                 */
                $studentProfile->setAlertMedical($this->findMedicalAlert($studentBaseProfile, $student));

                /* Fish out campus name
                 */
                $campusName = $this->findCampusName();

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($student);

                /* Find out a staff member via their user code
                 */
                $formTeacher = $this->findFormTeacher();

                /* Fish out a staff member via their user code
                 */
                $housemaster = $this->findHousemaster();

                /* Fish out special needs
                 */
                $specialNeeds = $this->findSpecialNeeds($externalId);

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

                /* Shank another endpoint to find email data
                 * We need their personId first
                 */
                $personId = (string)$student['relationships']['person']['data']['id'];

                /* Access the endpoint and if the personId doesn't exist
                 * or if they're broken, just ignore it - they go in without
                 * an email address
                 */
                try {
                    $emailPayload = $this->api->retrieveDocument("enrolments/person/{$personId}?include=emails");

                    /* Each guy could have multiple emails;
                     * Assume the first one is enough
                     */
                    $email = trim(($emailPayload['data']['relationships']['emails']['data'][0]['attributes']['email'] ?? ''));
                } catch (\InvalidArgumentException | \DomainException $e) {
                    // noop - endpoint died
                }

                /* Smoosh it all together
                 */
                $studentProfile
                    ->setUsername($username)
                    ->setAlertComment($alertComments)
                    ->setAlertLegal($alertLegal ?? null)
                    ->setAlertMessage($messages)
                    ->setCampusName($campusName)
                    ->setEmail($email)
                    ->setEnrolmentDate($enrolment)
                    ->setFormName($form)
                    ->setFormTeacherId($formTeacher['id'] ?? null)
                    ->setFormTeacherName($formTeacher['name'] ?? null)
                    ->setGender($gender)
                    ->setHeadOfHouseId($housemaster['id'] ?? null)
                    ->setHeadOfHouseName($housemaster['name'] ?? null)
                    ->setHouseName($academicHouse ?? null)
                    ->setLeavingDate($leaving)
                    ->setMobile($mobile)
                    ->setStudiesCode($studiesCode)
                    ->setTutorGroup($tutorGroup)
                    ->setTutorTeacherId($tutor['id'] ?? null)
                    ->setTutorTeacherName($tutor['name'] ?? null)
                    ->setYearLevel($yearLevel)
                    ->setYearLevelCoordinatorId($yearLevelCoordinator['id'] ?? null)
                    ->setYearLevelCoordinatorName($yearLevelCoordinator['name'] ?? null)
                    ->setBoardingHouseName($boardingHouseName)

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
                        ->setDateOfBirth($this->findDate($dob));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (isset($emergencyContacts) && \count($emergencyContacts)) {
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
                        'Found %s emergency contacts for %s (%s)',
                        \count($emergencyContacts),
                        trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                        $studentBaseProfile->getExternalId()
                    ));
                }

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding student profile for %s (preferred: %s) (%s)',
                    $fullname,
                    $firstnamePreferred,
                    $externalId
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
                $this->importParentsOfStudent($studentBaseProfile, $student['relationships']['contacts']['data']);

                /* Remember that we saw a particular student id
                 * (-not- their externalId, the primary key in sentral for this student)
                 *
                 * We need to get each dude's timetable separately via some other api endpoint
                 * and we won't encounter this id again unless we remember it here
                 */
                $this->registry->remember($externalId, [
                    'person' => [
                        'id' => $student['id'],
                        'type' => self::TYPE_STUDENT,
                        'externalId' => $externalId,
                        'firstname' => $studentBaseProfile->getFirstname(),
                        'firstnamePreferred' => $studentBaseProfile->getFirstnamePreferred(),
                        'lastname' => $studentBaseProfile->getLastname()
                    ],
                ]);
            }

            /* Prepare for next page if it exists
             */
            $nextUrl = $payload['links']['next'] ?? null;
        }
    }


    /**
     * Find the Academic Period instances relevant to $date
     *
     * Used to filter enrolments/students endpoint which would otherwise give
     * you all the students who ever passed through its halls
     *
     * Note: the field "isActive" doesn't actually tell you whether a student is
     * actually active in the school, it's more to do with sentral data, not the
     * student themselves, and therefore "isMisleading"
     *
     * @param string $date
     * @return array
     */
    private function findAcademicPeriodIds(string $date): array
    {
        $ids = [];

        $nextUrl = "enrolments/academic-period?date={$date}&limit=200";

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $row) {
                $ids[] = $row['id'];
            }

            /* Prepare for next page if it exists
             */
            $nextUrl = $payload['links']['next'] ?? null;
        }

        return $ids;
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @phpstan-ignore-next-line: this will eventually return a string
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
     * @param array $student
     * @return array|null
     */
    public function findEmergencyContacts(array $student): ?array
    {
        /* There's a sideload for emergencyContactLinks via Get Student Person
         *  ...but it doesn't give you any at all, it seems broken.
         *  http://development.sentral.com.au/#operation/getStudentPerson
         *
         * The way it's implemented down here is the way it's supposed to be implemented
         * EmergencyContactLinks isn't really a thing, it seems to be part of some other
         * broken system they've tapped into. Presumably it just gave me all the contacts
         * whose isEmergencyContact flag was set to 1, but it might not even do that.
         *
         * Anyway, I already can get this data, I don't need a special way
         */

        $contacts = $student['relationships']['contacts']['data'];

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

            $this->log->debug(sprintf(
                'Secondary api inspection at %s for emergency contact details belonging to %s',
                $endpoint,
                trim("{$firstname} {$lastname}")
            ));

            /* Catalog each of this guy's phone numbers so we can try and assign some
             */
            $numbers = [];

            if (isset($payload['data']['relationships']['phoneNumbers'])) {
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
     * Find a House name for this person
     *
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $student
     * @return string|null
     */
    private function findHouse(UserProfile\Model $studentBaseProfile, array $student): ?string
    {
        /* Find another endpoint who can tell us the answer
         */
        $endpoint = $student['relationships']['primaryEnrolment']['data']['relationships']['house']['links']['related'];

        /* Sentral provides endpoints that result in guaranteed failure
         * ie. endpoints to ids that don't exist
         *
         * Instead of dying irrecoverably, alert with warning log that this record
         * might be a bit broken; house can't be found, and will become null
         */
        try {
            $payload = $this->api->retrieveDocument($endpoint);
        } catch (\InvalidArgumentException $e) {
            $this->log->warning($e->getMessage());

            return null;
        }

        $houseName = $payload['data']['attributes']['name'] ?? '';

        $this->log->debug(sprintf(
            'Secondary api inspection at %s for House belonging to %s (user %s) - found %s',
            $endpoint,
            trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
            $studentBaseProfile->getExternalId(),
            $houseName
        ));

        return (string)$houseName;
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
     * @phpstan-ignore-next-line: this will eventually return a string
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
     * @param array             $student
     * @return string
     */
    private function findMedicalAlert(UserProfile\Model $studentBaseProfile, array $student): string
    {
        /* Find another endpoint who can tell us the answer
         */
        $endpoint = $student['relationships']['person']['data']['relationships']['medicalConditions']['links']['related'];

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
     * @param string $studentExternalId
     * @return bool
     */
    private function findSpecialNeeds(string $studentExternalId): bool
    {
        /* Go digging in another endpoint for data
         * http://development.sentral.com.au/#operation/getSpecialNeedsPrograms
         */
//        $endpoint = "enrolments/special-needs-program?studentIds={$studentExternalId}";
//
//        $payload = $this->api->retrieveDocument($endpoint);
//
//        if (\count($payload['data'])) {
//            print_r($payload);
//            die();
//
//        }

        /* Special Needs in sentral was a concept only, and partially implemented and documented
         * Apparently no-one uses this notion and we are supposed to ignore its existence for now
         * (weird bu ok, whatever)
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
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $contacts
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile, array $contacts): void
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

            /* The relationship of this person to our student
             *
             * Siblings and the student themselves might appear here
             * for no apparent reason, but ok
             */
            $relationshipName = trim((string)$contact['attributes']['relationName']);

            /* Skip Sibling relationships
             */
            if ($relationshipName === self::RELATIONSHIP_SIBLING) {
                $this->log->debug(sprintf(
                    'Person id %s is a %s of the student - skipping.',
                    $contact['attributes']['personId'],
                    $relationshipName
                ));

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
            $externalId = (string)$parent['data']['attributes']['contactCode'];
            $email = (string)$parent['data']['attributes']['email'];

            /* Find the first meaningful value out of this set
             */
            $firstname = (new FirstMeaningfulValue())->find([
                $parent['data']['attributes']['firstName'],
                $parent['data']['attributes']['givenNames']
            ]);

            $firstnamePreferred = trim((string)$parent['data']['attributes']['preferredName']);

            $lastname = (string)$parent['data']['attributes']['lastName'];
            $phoneHome = $numbers[self::HOME_TELEPHONE_NUMBER] ?? '';
            $phoneMobile = $numbers[self::MOBILE_TELEPHONE_NUMBER] ?? '';
            $phoneWork = $numbers[self::WORK_TELEPHONE_NUMBER] ?? '';
            $title = (string)$parent['data']['attributes']['title'];
            $username = '';

            /* A shortcut for convenience in logging
             * (Sometimes people don't have all the parts of their name; odd but ok)
             */
            $fullname = trim("{$firstname} {$lastname}");

            /* The student appeared as a contact of themselves
             * Not sure if misconfiguration or third party vendor tomfoolery
             * In either case, we don't want this
             */
            if ($externalId === $studentBaseProfile->getExternalId()) {
                $this->log->warning(sprintf(
                    'Student %s %s appeared as contact of themselves - skipping. Double-check with endpoint: %s',
                    $studentBaseProfile->getFirstname(),
                    $studentBaseProfile->getLastname(),
                    $endpoint
                ));

                continue;
            }

            if ('' === trim($externalId)) {
                $this->log->warning(sprintf(
                    'External Id for parent %s is empty; skipping',
                    $fullname
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
                'Adding parent profile for %s (preferred; %s) (%s)',
                $fullname,
                $firstnamePreferred,
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
                'Adding relation between %s (%s), the %s of %s (%s)',
                $fullname,
                $externalId,
                $relationshipName,
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                $studentBaseProfile->getExternalId()
            ));

            /* Then save it
             */
            if ($this->perform) {
                $relationship = $this->relationshipMapper->createModel()
                    ->setUserProfile($parentBaseProfile)
                    ->setChildProfile($studentBaseProfile)
                    ->setType($relationshipName);

                $this->relationshipMapper->save($relationship);
            }
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = 'enrolments/staff?include=person,qualifications,employments&limit=200';

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            foreach ($payload['data'] as $staff) {
                $externalId = (string)$staff['attributes']['staffCode'];

                /* Find the first meaningful value out of this set
                 */
                $firstname = (new FirstMeaningfulValue())->find([
                    $staff['relationships']['person']['data']['attributes']['firstName'],
                    $staff['relationships']['person']['data']['attributes']['givenNames']
                ]);

                $firstnamePreferred = trim((string)$staff['relationships']['person']['data']['attributes']['preferredName']);

                $building = '';
                $campusName = '';
                $category = (string)$staff['attributes']['employmentClassification'];
                $department = '';
                $email = (string)$staff['relationships']['person']['data']['attributes']['email'];
                $lastname = (string)$staff['relationships']['person']['data']['attributes']['lastName'];
                $positionTitle = (string)$staff['attributes']['jobTitle'];
                $room = '';
                $title = (string)$staff['relationships']['person']['data']['attributes']['title'];
                $username = (string)$staff['attributes']['timetableCode'];

                /* A shortcut for convenience in logging
                 * (Sometimes people don't have all the parts of their name; odd but ok)
                 */
                $fullname = trim("{$firstname} {$lastname}");

                /* Disregard inactive staff
                 */
                if (!(int)$staff['attributes']['isActive']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is inactive',
                        $fullname
                    ));

                    continue;
                }

                /* Disregard departed staff
                 */
                if (self::STAFF_EMPLOYMENT_STATUS_DEPARTED === $staff['attributes']['employmentStatus']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is departed',
                        $fullname
                    ));

                    continue;
                }

                /* Go digging in another endpoint for names and numbers
                 * Construct the endpoint manually, because you can't trust the ids
                 * provided by the API (some are non-integers which die)
                 */
                $endpoint = "enrolments/person/{$staff['relationships']['person']['data']['id']}?include=phoneNumbers";

                try {
                    $staffPayload = $this->api->retrieveDocument($endpoint);
                } catch (\InvalidArgumentException $e) {
                    /* This endpoint might try to access someone who doesn't exist
                     * resulting in a dead payload - can't do anything with that
                     */
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - bad payload',
                        $fullname
                    ));

                    continue;
                }

                $firstname = (string)$staffPayload['data']['attributes']['firstName'];
                $lastname = (string)$staffPayload['data']['attributes']['lastName'];

                $this->log->debug(sprintf(
                    'Secondary api inspection at %s for phone numbers belonging to %s',
                    $endpoint,
                    $fullname
                ));

                /* Catalog each of this guy's phone numbers so we can try and assign some
                 */
                $numbers = [];

                if (isset($staffPayload['data']['relationships']['phoneNumbers'])) {
                    foreach ($staffPayload['data']['relationships']['phoneNumbers']['data'] as $phoneNumber) {
                        $numbers[$phoneNumber['attributes']['typeName']] = $phoneNumber['attributes']['number'];
                    }
                }

                $phone = $numbers[self::WORK_TELEPHONE_NUMBER] ?? '';

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
                    'Adding staff profile for %s (preferred: %s) (%s)',
                    $fullname,
                    $firstnamePreferred,
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->staffProfileMapper->save($staffProfile);
                }

                /* Remember that we saw a particular staff id
                 * (-not- their externalId, the primary key in sentral for this staff)
                 *
                 * We need to get each dude's timetable separately via some other api endpoint
                 * and we won't encounter this id again unless we remember it here
                 */
                $this->registry->remember($externalId, [
                    'person' => [
                        'id' => $staff['id'],
                        'type' => self::TYPE_STAFF,
                        'externalId' => $externalId,
                        'firstname' => $staffBaseProfile->getFirstname(),
                        'firstnamePreferred' => $staffBaseProfile->getFirstnamePreferred(),
                        'lastname' => $staffBaseProfile->getLastname()
                    ],
                ]);
            }

            /* Prepare for next page if it exists
             */
            $nextUrl = $payload['links']['next'] ?? null;
        }
    }
}
