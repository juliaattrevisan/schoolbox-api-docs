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
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Watchlist;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

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
    public const STAFF_PHOTO_ENDPOINT = 'enrolments/staff/%s/photo';


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
     * Employment status for departed staff: if a staff member is departed, they
     * should not be synced.
     */
    private const STAFF_EMPLOYMENT_STATUS_DEPARTED = 'left';


    /**
     * These relationship types, by default, are imported as Parents of a Student
     * They are used when you've never configured this in SUDS Control Panel
     * (the school should check and revise these selections in their instance)
     *
     * 01 Parent
     * 02 Step-Parent
     * 03 Adoptive Parent
     * 04 Foster Parent
     * 20 Guardian
     */
    private const DEFAULT_ALLOWED_RELATIONSHIP_IDS = [
        '01', '02', '03', '04', '20'
    ];


    /**
     * Campuses are separated by semicolons here.
     */
    private const CAMPUS_SEPARATOR = ';';


    /**
     * @var array
     */
    private array $campuses;


    /**
     * @var array
     */
    private array $houses;


    /**
     * Profile constructor.
     *
     * @param ValidStrats                     $validStrats
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param IApi                            $api
     * @param array                           $watchlist
     * @param bool                            $includePhoto
     * @param Registry                        $registry
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper                   $storageMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private array $watchlist,
        private bool $includePhoto,
        private Registry $registry,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper,
        private StorageMapper $storageMapper,
    ) {
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Grab a list of campuses
         */
        $this->campuses = (new Lookup\Campus($this->api))->generate();

        /* Grab a list of houses
         */
        $this->houses = (new Lookup\House($this->api))->generate();

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

                /* Only import data for externalIds in $watchlist, if provided
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                /* Skip deceased humans
                 */
                if ($student['relationships']['person']['data']['attributes']['isDeceased']) {
                    $this->log->debug(sprintf(
                        'Person id %s is deceased: skipping.',
                        $student['relationships']['person']['data']['id']
                    ));

                    continue;
                }

                $boardingHouseName = (string)$student['relationships']['primaryEnrolment']['data']['attributes']['boardingHouse'];
                $boardingStatus = (bool)$student['relationships']['primaryEnrolment']['data']['attributes']['isBoarding'];
                $campusName = $this->findCampusName($student['relationships']['primaryEnrolment']['data']['relationships']['campus']['data']['id'] ?? null);
                $comments = '';
                $dob = (string)$student['relationships']['person']['data']['attributes']['dateOfBirth'];
                $email = (string)$student['relationships']['person']['data']['attributes']['email'];

                /* Find the first meaningful value out of this set
                 */
                $firstname = (new FirstMeaningfulValue())->find([
                                                                    $student['relationships']['person']['data']['attributes']['firstName'],
                                                                    $student['relationships']['person']['data']['attributes']['givenNames']
                                                                ]);

                $firstnamePreferred = (new FirstMeaningfulValue())->find([
                                                                             $student['relationships']['person']['data']['attributes']['preferredName'],
                                                                             $firstname
                                                                         ]);

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

                $this->importPortrait(
                    $studentBaseProfile,
                    (string)$student['links']['photo'],
                    (string)$student['attributes']['updatedAt']['iso8601']
                );

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
                $academicHouse = $this->findStudentHouse($student);

                /* Fish out alert comments
                 */
                $alertComments = $this->findComments();

                /* Fish out legal alerts
                 */
                $alertLegal = $this->findLegal();

                /* Fish out medical alerts
                 */
                $studentProfile->setAlertMedical($this->findMedicalAlert($studentBaseProfile, $student));

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
                if ($this->validStrats->isFamiliesStratEnabled()) {
                    $this->importParentsOfStudent($studentBaseProfile, $student['relationships']['contacts']['data']);
                }

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
     * Import a profile picture for the given $baseProfile
     *
     * Importing a profile picture requires you to enable photo_from_sis config var
     * ("Load Photos From SIS" needs to be on)
     *
     * @param UserProfile\Model $baseProfile
     * @param string            $photoEndpoint
     * @param string            $updatedAtTime
     * @return void
     */
    private function importPortrait(UserProfile\Model $baseProfile, string $photoEndpoint, string $updatedAtTime): void
    {
        /* Skipping if the url is not provided
         */
        if (!$photoEndpoint) {
            return;
        }
        /* Check if config setting allows photos from sis
         * If it's enabled, continue importing pics
         */
        if (!$this->includePhoto) {
            return;
        }

        /* Does user already have a portrait? If not let's try to find and update
         */
        if ($baseProfile->getPortrait() !== null) {
            /* Check if the user was modified since the last update
             * If yes, the photo could also have been updated, let's grab it
             */
            $updated_at = $this->findDate($updatedAtTime)?->getDatabase();
            $stmt       = $this->baseProfileMapper->getDatabase()->executeQuery(
                "
            SELECT updated_at
            FROM {$this->baseProfileMapper->getBaseTableName()}
            WHERE external_id = :externalId
            AND updated_at < :updatedAt
        ",
                [
                    'externalId' => $baseProfile->getExternalId(),
                    'updatedAt'  => $updated_at,
                ]
            );

            if ($stmt->rowCount() === 0) {
                $this->log->debug(
                    sprintf(
                        'Portrait of %s %s (%s) appears to be up to date. Skipping.',
                        $baseProfile->getFirstname(),
                        $baseProfile->getLastname(),
                        $baseProfile->getExternalId()
                    )
                );
                return;
            }
        }

        $endpoint = "$photoEndpoint?width=500&height=500";
        $photoContents = $this->api->retrievePortrait($endpoint);

        /* Only try to persist images if perform mode is activated
         */
        if (!$this->perform) {
            return;
        }

        try {
            $externalId = $baseProfile->getExternalId();
            $portrait = $this->storageMapper->acceptStream($externalId . '.jpg', $photoContents);

            /* You get a picture if any of the following occur:
             *     - you don't have a portrait yet; you can get one added
             *     - your existing picture is different to the one we just got; update it
             */
            if (!$baseProfile->getPortrait() || ($baseProfile->getPortrait()->getHash() !== $portrait->getHash())) {
                $this->log->debug(sprintf(
                    'Profile picture: %s %s (%s) has profile hash %s derived from %s.png',
                    $baseProfile->getFirstname(),
                    $baseProfile->getLastname(),
                    $externalId,
                    $portrait->getHash(),
                    $externalId,
                ));

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
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @param string|null $campusId
     * @return string|null
     */
    private function findCampusName(?string $campusId): ?string
    {
        $campusName = null;

        if (null !== $campusId) {
            $campusName = $this->campuses[(int)$campusId] ?? null;

            $this->log->debug(sprintf(
                'Assigning campus name %s',
                $campusName
            ));
        }

        return $campusName;
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
     * @param array             $student
     * @return string|null
     */
    private function findStudentHouse(array $student): ?string
    {
        if (!isset($student['relationships']['primaryEnrolment'])) {
            $this->log->debug('Relationships primaryEnrolment data does not exist.');
            return null;
        }

        $enrolment = $student['relationships']['primaryEnrolment']['data'];

        /* "house" might not be a node in the relationships;
         * This student doesn't have a house.
         */
        if (isset($enrolment['relationships']['house']['data'])) {
            $houseId = $enrolment['relationships']['house']['data']['id'] ?? null;

            if (null !== $houseId) {
                return $this->houses[$houseId];
            }
        }

        return null;
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
        // Get default relationIds, if no options selected in config
        $allowedRelationshipIds = $this->api->getOptions()['parentRelationIds'] ?? self::DEFAULT_ALLOWED_RELATIONSHIP_IDS;

        /* Decide if "Receives Portal Access" plays a part in filtering contacts out
         */
        $requiresReceivesPortalAccess = $this->api->getOptions()['receivesPortalAccess'] ?? false;

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

            /* "Receives Portal Access" is required
             */
            if ($requiresReceivesPortalAccess && !$contact['attributes']['receivesPortalAccess']) {
                $this->log->debug(sprintf(
                    'Person id %s does not have portal access - skipping.',
                    $contact['attributes']['personId']
                ));

                continue;
            }

            /* The relationship of this person to our student
             *
             * Siblings and the student themselves might appear here
             * for no apparent reason, but ok
             */
            $relationshipId = trim((string)$contact['attributes']['relationType']);
            $relationshipName = trim((string)$contact['attributes']['relationName']);

            /* We only assign Parents as chosen by the school in Sentral Configuration
             * in the SUDS Control Panel; anyone outside the chosen set is ignored
             */
            if (!in_array($relationshipId, $allowedRelationshipIds, true)) {
                $this->log->debug(sprintf(
                    'Person id %s is a "%s %s" of the student - skipping.',
                    $contact['attributes']['personId'],
                    $relationshipId,
                    $relationshipName
                ));

                continue;
            }

            /* Go digging in another endpoint for data
             */
            $endpoint = "enrolments/person/{$contact['attributes']['personId']}?include=phoneNumbers";

            $parent = $this->api->retrieveDocument($endpoint);

            /* Skip deceased humans
             */
            if ($parent['data']['attributes']['isDeceased']) {
                $this->log->debug(sprintf(
                    'Person id %s is deceased: skipping.',
                    $parent['data']['id']
                ));

                continue;
            }

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

            $firstnamePreferred = (new FirstMeaningfulValue())->find([
                                                                         $parent['data']['attributes']['preferredName'],
                                                                         $firstname
                                                                     ]);

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
                'Adding parent profile for %s (preferred: %s) (%s)',
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
                $externalId = trim((string)$staff['attributes']['staffCode']);

                /* Only import data for externalIds in $watchlist, if provided
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                /* Skip deceased humans
                 */
                if ($staff['relationships']['person']['data']['attributes']['isDeceased']) {
                    $this->log->debug(sprintf(
                        'Person id %s is deceased: skipping.',
                        $staff['relationships']['person']['data']['id']
                    ));

                    continue;
                }

                /* Find the first meaningful value out of this set
                 */
                $firstname = (new FirstMeaningfulValue())->find(
                    [
                        $staff['relationships']['person']['data']['attributes']['firstName'],
                        $staff['relationships']['person']['data']['attributes']['givenNames']
                    ]
                );

                $firstnamePreferred = (new FirstMeaningfulValue())->find(
                    [
                        $staff['relationships']['person']['data']['attributes']['preferredName'],
                        $firstname
                    ]
                );

                $building = '';

                /* Aggregate multiple staff campuses/campii from all their employments, I guess
                 */
                $campuses = [];

                /* For some reason, sentral staff don't always have an Employments node lol
                 * A staff member with no record of employment is strange, but ok
                 */
                if (isset($staff['relationships']['employments'])) {
                    foreach ($staff['relationships']['employments']['data'] as $employment) {
                        /* Apparently, campuses are optional too...
                         */
                        if (isset($employment['relationships']['campusLocations'])) {
                            foreach ($employment['relationships']['campusLocations']['data'] as $campus) {
                                $campuses[] = $this->findCampusName($campus['id']);
                            }
                        }
                    }
                }

                $campusName = implode(self::CAMPUS_SEPARATOR . ' ', $campuses);

                $category = trim((string)$staff['attributes']['employmentClassification']);
                $department = '';
                $email = trim((string)$staff['relationships']['person']['data']['attributes']['email']);
                $house = $this->findStaffHouse($staff);
                $lastname = trim((string)$staff['relationships']['person']['data']['attributes']['lastName']);
                $positionTitle = trim((string)($staff['attributes']['jobTitle'] ?? ''));
                $room = '';
                $title = trim((string)$staff['relationships']['person']['data']['attributes']['title']);
                $username = trim((string)$staff['attributes']['timetableCode']);

                /* Employment status/dates
                 */
                $isEmployed = $this->findEmploymentStatus($staff);

                /* A shortcut for convenience in logging
                 * (Sometimes people don't have all the parts of their name; odd but ok)
                 */
                $fullname = trim("{$firstname} {$lastname}");

                /* Disregard unemployed staff
                 */
                if (!$isEmployed) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is not currently employed',
                        $fullname
                    ));

                    continue;
                }

                /* Disregard inactive staff
                 */
                if (!(int)$staff['attributes']['isActive']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is inactive',
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

                $firstname = trim((string)$staffPayload['data']['attributes']['firstName']);
                $lastname = trim((string)$staffPayload['data']['attributes']['lastName']);

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
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                $this->importPortrait(
                    $staffBaseProfile,
                    sprintf(self::STAFF_PHOTO_ENDPOINT, (string)$staff['id']),
                    (string)$staff['attributes']['updatedAt']['iso8601']
                );

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
                    ->setHouseName($house)
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


    /**
     * Staff don't get Houses as a sideload, so you have to fetch them the hard way.
     *
     * @param array $staff
     * @return string|null
     */
    private function findStaffHouse(array $staff): ?string
    {
        /* Find the first employment data and accept that as canon
         */
        // 'employments' may not exist for invalid data
        if (!isset($staff['relationships']['employments'])) {
            $this->log->debug('Relationships employments data does not exist.');
            return null;
        }

        foreach ($staff['relationships']['employments']['data'] as $employment) {
            if ('staffEmployment' === $employment['type']) {
                /* "house" might not be a node in the relationships;
                 * This staff member doesn't have a house.
                 */
                if (isset($employment['relationships']['house']['data'])) {
                    $houseId = $employment['relationships']['house']['data']['id'] ?? null;

                    /* This was added for Queenwood, might not appear anywhere else
                     */
                    if (null !== $houseId) {
                        return $this->houses[$houseId];
                    }
                }
            }
        }

        return null;
    }


    /**
     * Find the first employment data and use that to decide something about
     * a staff member's employment state
     *
     * @param array $staff
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function findEmploymentStatus(array $staff): bool
    {
        /* Find the first employment data and accept that as canon
         */
        // 'employments' may not exist for invalid data
        if (!isset($staff['relationships']['employments'])) {
            $this->log->debug('Relationships employments data does not exist.');
            return false;
        }

        foreach ($staff['relationships']['employments']['data'] as $employment) {
            if ('staffEmployment' === $employment['type']) {
                $now = \Alaress_Date_Format::now();

                /* I suppose this might be conclusive, if set.
                 * We can skip the date analysis
                 */
                if (self::STAFF_EMPLOYMENT_STATUS_DEPARTED === $employment['attributes']['employmentStatus']) {
                    return false;
                }

                /* Otherwise, inspect the dates.
                 * One or more of the dates might be missing
                 *
                 * I presume (perhaps unsafely) that an employee's finish date
                 * must come after their start date
                 */

                /* Start date was specified
                 */
                $employmentStart = trim((string)$employment['attributes']['startDate']);

                if ('' !== $employmentStart) {
                    $start = new \Alaress_Date_Format($employmentStart, \Alaress_Date_Format::DATABASE_DATE_FORMAT);

                    /* They started before now? I guess their employment has begun
                     * Otherwise, they haven't yet started
                     */
                    if ($now->lt($start)) {
                        return false;
                    }
                }

                /* End date was specified
                 */
                $employmentEnd = trim((string)$employment['attributes']['endDate']);

                if ('' !== $employmentEnd) {
                    $end = new \Alaress_Date_Format($employmentEnd, \Alaress_Date_Format::DATABASE_DATE_FORMAT);

                    /* They finish somewhere in the future? I guess they're employed
                     * Otherwise, they finished somewhere in the past
                     */
                    if ($now->lt($end)) {
                        return true;
                    }

                    return false;
                }

                return true;
            }
        }

        /* They're an active employed staff member unless otherwise decided
         */
        return true;
    }
}
