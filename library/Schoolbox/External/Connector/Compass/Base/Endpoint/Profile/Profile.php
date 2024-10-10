<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Compass\Base\Endpoint\Helper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * For inexplicable reasons Compass decided to entertain the idea that multiple
     * roletype humans get mixed up in each endpoint and we have to manually sort them
     * out using this mechanism
     */
    public const BASEROLE_STUDENT = 1;
    public const BASEROLE_STAFF = 2;
    public const BASEROLE_PARENT = 3;


    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


    /**
     * Staff Category strings; they can be renamed to whatever you prefer
     */
    private const TEACHING_STAFF = 'Teaching Staff';
    private const NON_TEACHING_STAFF = 'Non-Teaching Staff';


    /**
     * A string corresponding to a record that is considered Active
     */
    private const ACTIVE = 'Active';


    /**
     * These strings represent Boarders
     *
     * I'm not sure what the difference is between a Part Time boarder
     * and a Weekday boarder, exactly...
     *
     * It seems like Part Time boarder might be someone who does everything
     * except sleep at school 5 days a week.
     *
     * A Weekday boarder might be like a full time boarder but only
     * for 5 days a week; they go home on weekends.
     */
    private const BOARDERS = ['Full Time', 'Part Time', 'Weekday'];


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of attempts to retry fetching a portrait url before skipping it
     */
    private const NUM_RETRY = 3;


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
     * @var bool
     */
    private bool $includePhoto;


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
     * @var Helper
     */
    private Helper $helper;


    /**
     * Used for api calls
     *
     * @var string
     */
    private string $lBound;


    /**
     * Used for api calls
     *
     * @var string
     */
    private string $uBound;


    /**
     * @var array
     */
    private array $campusLookup;


    /**
     * Profile constructor.
     *
     * @param ValidStrats                     $validStrats
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param IApi                            $api
     * @param bool                            $includePhoto
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper                   $storageMapper
     */
    public function __construct(
        ValidStrats $validStrats,
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        bool $includePhoto,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        StorageMapper $storageMapper
    ) {
        $this->validStrats = $validStrats;
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->includePhoto = $includePhoto;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
        $this->storageMapper = $storageMapper;

        /* Helper class
         */
        $this->helper = new Helper($this->api);

        /* Data is obtained daily; remember lower and upper bound values
         * for api calls. It needs to be in a specific format so might
         * as well remember that
         */
        $this->lBound = \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c');
        $this->uBound = \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c');

        /* Campus lookup
         */
        $this->campusLookup = [];
    }


    /**
     * Import user profile data into sis_user_profile*
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function import(): void
    {
        $this->log->info('Preloading campus lookup...');

        $this->campusLookup = (new Lookup\Campus($this->api, $this->helper->getOrganisationIdentifier()))
            ->generate();

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
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    private function importStudents(): void
    {
        /* Shank all the parents active today
         */
        $this->log->info(sprintf(
            'Downloading parents active between %s and %s...',
            $this->lBound,
            $this->uBound
        ));

        /* Save them into a hash lookup because compass guids are used to
         * identify parents and this is will be a fast way to get them
         */
        $families = (new Lookup\Families($this->api))->generate($this->lBound, $this->uBound);

        $this->log->info(sprintf(
            'Downloading students active between %s and %s...',
            $this->lBound,
            $this->uBound
        ));

        /* Shank all the students active today
         */
        $students = $this->api->retrieveDocument('person/v1/GetStudents', [
            'lBound' => $this->lBound,
            'uBound' => $this->uBound
        ]);

        foreach ($students['d'] as $student) {
//            if (!(new Watchlist())->isOnWatchlist($student['lastName'], ['DU BRUYN'])) {
//                continue;
//            }

            $externalId = $this->helper->findExternalId($student['importIdentifiers'], self::BASEROLE_STUDENT);

            /* If the externalId was empty, skip them
             * (externalId might also be empty because this person isn't in the correct baseRole)
             */
            if (null === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping %s %s (no external identifier)',
                    trim((string)$student['firstName']),
                    trim((string)$student['lastName'])
                ));

                continue;
            }

            $academicHouse = $this->findHouse($student['organisationMilestones']);
            $boardingHouseName = '';
            $boardingStatus = $this->findBoardingStatus($student['studentDetails']);
            $campusName = $this->findCampusName($student['organisationMilestones']);
            $dob = $this->findDate($student['dateOfBirth']);
            $email = $this->findEmail($student['studentDetails']);
            $firstname = (string)$student['firstName'];
            $firstnamePreferred = (string)$student['preferredFirstName'];

            $form = $this->findForm($student['organisationMilestones']);
            $gender = trim((string)$student['gender']);

            /* Prioritise preferred names over official names
             */
            $lastname = (new FirstMeaningfulValue())->find([
                $student['preferredLastName'],
                $student['lastName']
            ]);

            $mobile = '';

            /* Not sure where a studies code equivalent is, but have taken
             * a guess that it's one of govtCode* fields
             *
             * In the test instance this manifests in Personal > VSN
             */
            $studiesCode = (new FirstMeaningfulValue())->find([
                $student['govtCode1'],
                $student['govtCode2']
            ]);

            $title = trim((string)$student['title']);
            $tutor = $this->findTutor();
            $tutorGroup = $this->findTutorGroup($student['organisationMilestones']);
            $username = trim((string)$student['username']);
            $yearLevel = $this->findYearLevel($student['organisationMilestones']);

            /* No dates provided
             * The api only gives you active users anyway
             */
            $enrolmentDate = '';
            $leavingDate = '';

            /* Flags
             */
            $esl = (bool)$student['receivesEsl'];
            $ib = false;
            $ilp = false;
            $specialNeeds = false;

            /* Alerts
             */
            $comments = '';
            $legal = '';
            $medical = $this->findMedicalAlert($student['medicalConditions']);
            $messages = '';

            /* Emergency Contacts
             */
            $emergencyContacts = $this->findEmergencyContacts($student['emergencyContacts']);

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

            /* Import profile pictures
             */
            $this->importPortrait($studentBaseProfile, self::BASEROLE_STUDENT, $student);

            /* Then save it
             */
            if ($this->perform) {
                $this->baseProfileMapper->save($studentBaseProfile);
            }

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment(null)
                ->setAlertLegal(null)
                ->setAlertMedical($medical)
                ->setAlertMessage(null)
                ->setCampusName($campusName)
                ->setEmail($email)
                ->setEnrolmentDate($enrolment)
                ->setFormName($form)
                ->setFormTeacherId(null)
                ->setFormTeacherName(null)
                ->setGender($gender)
                ->setHeadOfHouseId(null)
                ->setHeadOfHouseName(null)
                ->setHouseName($academicHouse)
                ->setLeavingDate($leaving)
                ->setMobile($mobile)
                ->setStudiesCode($studiesCode)
                ->setTutorGroup($tutorGroup)
                ->setTutorTeacherId($tutor['id'])
                ->setTutorTeacherName($tutor['name'])
                ->setYearLevel($yearLevel)
                ->setYearLevelCoordinatorId(null)
                ->setYearLevelCoordinatorName(null)
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
                    ->setDateOfBirth($dob);
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
            }

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding student profile for %s (preferred: %s) (%s)',
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                $firstnamePreferred,
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
                $this->importParentsOfStudent($student['compassPersonId'], $student['relationships'], $studentBaseProfile, $families);
            }
        }
    }


    /**
     * Multiple Boarding Status might exist for a given student if they're involved in
     * multiple schools. Just like the externalId, find the record that is relevant to the
     * current school (host)
     *
     * Possible values: None, Full Time, Weekday, Part Time, Casual
     *
     * @param array $studentDetails
     * @return bool
     */
    private function findBoardingStatus(array $studentDetails): bool
    {
        /* I guess Full Time boarders result in true
         * but I don't know what a Part Time boarder is?
         *
         * I guess they're gonna return true, too
         */
        $boarderType = $this->findDetailsField($studentDetails, 'boarderType');

        return in_array($boarderType, self::BOARDERS, true);
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @param array $organisationMilestones
     * @return string|null
     */
    private function findCampusName(array $organisationMilestones): ?string
    {
        $campusId = $this->findOrganisationMilestoneField($organisationMilestones, 'homeCampus');

        /* If it exists in the lookup, use its name
         */
        if (isset($this->campusLookup[$campusId])) {
            return $this->campusLookup[$campusId]['name'];
        }

        return '';
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
        if ($date) {
            return new \Alaress_Date_Format($date, 'd/m/Y');
        }

        return null;
    }


    /**
     * Find a student's organisationUserEmail
     *
     * @param array $studentDetails
     * @return string|null
     */
    private function findEmail(array $studentDetails): ?string
    {
        return $this->findDetailsField($studentDetails, 'organisationUserEmail');
    }


    /**
     * Find the value of $field in the set of details $details
     * This iterates to find the first Active record that corresponds
     * to the current instance and organisationName
     *
     * @param array  $details
     * @param string $field
     * @return string|null
     */
    private function findDetailsField(array $details, string $field): ?string
    {
        foreach ($details as $detail) {
            if ($this->isActiveDetailsField($detail)) {
                return (string)$detail[$field];
            }
        }

        return null;
    }


    /**
     * Find the value of $field in the set of details $details
     * This iterates to find the first Active record that corresponds
     * to the current instance and organisationName
     *
     * Returns an array because sometimes that's what's in the details set
     *
     * @param array  $details
     * @param string $field
     * @return array|null
     */
    private function findDetailsFieldArray(array $details, string $field): ?array
    {
        foreach ($details as $detail) {
            if ($this->isActiveDetailsField($detail)) {
                return $detail[$field];
            }
        }

        return null;
    }


    /**
     * Decide if one of the records in studentDetails, parentDetails
     * or staffDetails is Active based on its status field and also
     * if it corresponds to the current host
     *
     * @param array $detail
     * @return bool
     */
    private function isActiveDetailsField(array $detail): bool
    {
        return $this->helper->matchesHost($detail['organisationName']) && self::ACTIVE === $detail['status'];
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param array $emergencyContacts
     * @return array
     */
    public function findEmergencyContacts(array $emergencyContacts): array
    {
        $contacts = [];
        $n = 0;

        foreach ($emergencyContacts as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                $fullname = trim((string)$emergencyContact['name']);
                $relation = trim((string)$emergencyContact['relation']);
                $phone = trim((string)$emergencyContact['contactNotes']);

                /* Suffix the $relation if provided
                 */
                if ('' !== $relation) {
                    $fullname .= " ({$relation})";
                }

                /* Aggregate them if we have a name
                 */
                if ('' !== $fullname) {
                    $contacts[$emergencyContact['contactOrder']] = [
                        'name' => $fullname,
                        'phone' => $phone
                    ];
                }
            }

            $n++;
        }

        /* There's a contactOrder field which seems to be a priority sequence integer
         * Reindex this array so it's zero-indexed again
         */
        return array_values($contacts);
    }


    /**
     * A student's Form is "typically groupA" in the payload
     *
     * Find the first organisationMilestone that specified a meaningful groupA value
     *
     * @param array $organisationMilestones
     * @return string|null
     */
    private function findForm(array $organisationMilestones): ?string
    {
        return $this->findOrganisationMilestoneField($organisationMilestones, 'groupA');
    }


    /**
     * A student's house is "typically groupC" in the payload
     * This manifests as "House" in the ui, so despite its arbitrary name it seems predictable
     *
     * Find the first organisationMilestone that specified a meaningful groupC value
     *
     * @param array $organisationMilestones
     * @return string|null
     */
    private function findHouse(array $organisationMilestones): ?string
    {
        return $this->findOrganisationMilestoneField($organisationMilestones, 'groupC');
    }


    /**
     * Find medical data for student
     *
     * @param array $medicalConditions
     * @return string|null
     */
    private function findMedicalAlert(array $medicalConditions): ?string
    {
        if (\count($medicalConditions)) {
            return 'This student has medical conditions.';
        }

        return null;
    }


    /**
     * Find a tutor from their id
     *
     * @return array
     */
    private function findTutor(): array
    {
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * A student's Tutor Group is "typically groupB" in the payload
     *
     * Find the first organisationMilestone that specified a meaningful groupB value
     *
     * @param array $organisationMilestones
     * @return string|null
     */
    private function findTutorGroup(array $organisationMilestones): ?string
    {
        return $this->findOrganisationMilestoneField($organisationMilestones, 'groupB');
    }


    /**
     * Find the first organisationMilestone that specified a meaningful yearLevel value
     *
     * @param array $organisationMilestones
     * @return string|null
     */
    private function findYearLevel(array $organisationMilestones): ?string
    {
        return $this->findOrganisationMilestoneField($organisationMilestones, 'yearLevel');
    }


    /**
     * Find a particular field inside the set of $organisationMilestones tuples
     *
     * @param array  $organisationMilestones
     * @param string $field
     * @return string|null
     */
    private function findOrganisationMilestoneField(array $organisationMilestones, string $field): ?string
    {
        foreach ($organisationMilestones as $organisationMilestone) {
            if ($this->helper->matchesHost($organisationMilestone['organisationName'])) {
                if ((int)$organisationMilestone['isActive'] && (int)$organisationMilestone['isCurrent']) {
                    if ('' !== trim((string)$organisationMilestone[$field])) {
                        return (string)$organisationMilestone[$field];
                    }
                }
            }
        }

        return null;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string $studentCompassId
     * @param array  $relationships
     * @param Model  $studentBaseProfile
     * @param array  $families
     * @throws Exception
     * @throws DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(string $studentCompassId, array $relationships, UserProfile\Model $studentBaseProfile, array $families): void
    {
        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($relationships as $relationship) {
            /* Can't find parent? Skip it
             */
            if (!isset($families[$relationship['compassPersonToId']])) {
                $this->log->warning(sprintf(
                    'Skipping: Could not find parent %s for %s %s (%s)',
                    $relationship['compassPersonToId'],
                    $studentBaseProfile->getFirstname(),
                    $studentBaseProfile->getLastname(),
                    $studentBaseProfile->getExternalId()
                ));

                continue;
            }

            /* Otherwise grab them out and process them as usual
             */
            $parent = $families[$relationship['compassPersonToId']];

            /* Start piling in the deets
             */
            $externalId = $this->helper->findExternalId($parent['importIdentifiers'], self::BASEROLE_PARENT);

            /* If the externalId was empty, skip them
             * (externalId might also be empty because this person isn't in the correct baseRole)
             */
            if (null === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping parent %s (no external identifier)',
                    $relationship['compassPersonToId']
                ));

                continue;
            }

            /* If their canSeePersonInPortal relationship is truthy,
             * this parent can see the student and should be created in schoolbox
             * Otherwise they should be skipped
             *
             * To do it, we need to find out if $parent has a relationship to $studentCompassId
             * where canSeePersonInPortal is truthy
             */
            $canAddParent = false;

            /* This is so dopey, it's tempting to turn $parent['relationships'] into a
             * hash lookup based on student's compass identifier in Families, just to
             * avoid this
             */
            foreach ($parent['relationships'] as $parentRelationship) {
                if ($parentRelationship['compassPersonToId'] === $studentCompassId && $parentRelationship['canSeePersonInPortal']) {
                    $canAddParent = true;
                }
            }

            if (!$canAddParent) {
                $this->log->debug(sprintf(
                    'Skipping parent %s %s (externalId %s) because they cannot see %s %s',
                    $parent['firstName'],
                    $parent['lastName'],
                    $externalId,
                    $studentBaseProfile->getFirstname(),
                    $studentBaseProfile->getLastname()
                ));

                continue;
            }

            $email = '';
            $phoneHome = '';
            $phoneMobile = '';

            /* This doesn't exist for Compass parents
             */
            $phoneWork = '';

            /* Do Not Contact
             * If this is ticked in the compass ui, don't get any phone/email info
             * The Compass API puts it out in a non-double-negative manner, 'contactable'
             */
            if ($parent['contactable']) {
                /* If Email Allowed is ticked, get their email address
                 */
                if ($parent['emailAllowed']) {
                    $email = trim((string)$parent['emailAddress']);
                }

                $phoneHome = trim((string)$parent['homePhoneNumber']);

                /* If SMS Allowed is ticked, get their mobile number
                 */
                if ($parent['smsAllowed']) {
                    $phoneMobile = trim((string)$parent['mobileNumber']);
                }
            } else {
                $this->log->debug(sprintf(
                    'Parent %s %s (externalId %s) is not contactable; disregarding email and phone fields.',
                    trim((string)$parent['firstName']),
                    trim((string)$parent['lastName']),
                    $externalId
                ));
            }

            $firstname = trim((string)$parent['firstName']);
            $firstnamePreferred = trim((string)$parent['preferredFirstName']);

            /* Prioritise preferred names over official names
             */
            $lastname = (new FirstMeaningfulValue())->find([
                $parent['preferredLastName'],
                $parent['lastName']
            ]);

            $relationship = trim((string)$relationship['type']);
            $title = trim((string)$parent['title']);
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
                'Adding relation between %s (%s), the %s of %s (%s)',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $parentBaseProfile->getExternalId(),
                trim($relationship),
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                $studentBaseProfile->getExternalId()
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
        /* Shank all the staff active today
         */
        $this->log->info(sprintf(
            'Downloading staff active between %s and %s...',
            $this->lBound,
            $this->uBound
        ));

        /* Shank all the students active today
         */
        $staffs = $this->api->retrieveDocument('person/v1/GetStaff', [
            'lBound' => $this->lBound,
            'uBound' => $this->uBound
        ]);

        foreach ($staffs['d'] as $staff) {
            $externalId = $this->helper->findExternalId($staff['importIdentifiers'], self::BASEROLE_STAFF);

            /* If the externalId was empty, skip them
             * (externalId might also be empty because this person isn't in the correct baseRole)
             */
            if (null === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping %s %s (no external identifier)',
                    trim((string)$staff['firstName']),
                    trim((string)$staff['lastName'])
                ));

                continue;
            }

            $campusName = $this->findCampusName($staff['organisationMilestones']);
            $firstname = trim((string)$staff['firstName']);
            $firstnamePreferred = trim((string)$staff['preferredFirstName']);

            /* Prioritise preferred names over official names
             */
            $lastname = (new FirstMeaningfulValue())->find([
                $staff['preferredLastName'],
                $staff['lastName']
            ]);

            /* Haven't encountered data this specific
             */
            $building = '';

            /* I guess I just assume this department for now
             * Use it to indicate teaching/non teaching staff, I suppose
             */
            $category = (int)$this->findDetailsField($staff['staffDetails'], 'isTeachingStaff') ? self::TEACHING_STAFF : self::NON_TEACHING_STAFF;

            /* This seems to be a set of organisationTitles elements
             */
            $departments = $this->findDetailsFieldArray($staff['staffDetails'], 'organisationTitles') ?? [];

            $department = implode(', ', $departments);

            /* TODO Email addresses also exist inside staffDetails and are
             *  instance-specific...what's the difference between those
             *  emails and this emailAddress one?
             *  Which one should take precedence?
             */
            $email = $this->findDetailsField($staff['staffDetails'], 'organisationUserEmail');

            /* The "Phone" field in compass ui ends up mapping to homePhoneNumber
             * so it's actually a generic Phone Number field.
             *
             * However: we've been instructed to never import private phone
             * numbers, and IMO there's too much risk of these numbers being
             * private; so we will just import the extension.
             */
            $phone = $this->findDetailsField($staff['staffDetails'], 'phoneExtension');

            $positionTitle = $this->findDetailsField($staff['staffDetails'], 'roleInSchool');

            /* What's Office Location, then?
             * I can't save anything to it in the compass ui anyway
             */
            $room = $this->findDetailsField($staff['staffDetails'], 'room');

            $title = trim((string)$staff['title']);
            $username = trim((string)$staff['username']);

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

            /* Import profile pictures
             */
            $this->importPortrait($staffBaseProfile, self::BASEROLE_STAFF, $staff);

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


    /**
     * Import a profile picture for the given $baseProfile
     *
     * Importing a profile picture requires you to enable photo_from_sis config var
     * ("Load Photos From SIS" needs to be on)
     *
     * This populates the portrait_hash in $baseProfile
     *
     * @param Model $baseProfile
     * @param int   $type
     * @param array $details
     * @return void
     */
    private function importPortrait(UserProfile\Model $baseProfile, int $type, array $details): void
    {
        /* Check if config setting allows photos from sis
         * If it's enabled, continue importing pics
         */
        if (!$this->includePhoto) {
            return;
        }

        /* Start validating the contents of $details to ensure we have what we need
         *
         * getPersonPhotoUrl node might not exist; if it doesn't, skip it
         */
        $url = $this->findPortraitUrl($type, $details);

        /* Node not present/poorly formed
         */
        if ('' === $url) {
            $this->log->debug(sprintf(
                'Profile picture: no data found for %s %s (%s)',
                $baseProfile->getFirstname(),
                $baseProfile->getLastname(),
                $baseProfile->getExternalId()
            ));

            return;
        }

        /* Fetch data from the url specified
         */
        $image = null;
        $fails = 0;

        while ($fails < self::NUM_RETRY) {
            try {
                // Use the API's HTTP client to fetch profile images:
                // it has the headers required for making valid requests against
                // Compass' CDN.
                $response = $this->api->getClient()->get($url);
                $image    = $response->getBody()->getContents();
                break;
            } catch (\Exception $e) {
                $this->log->warning(sprintf(
                    'Could not obtain profile picture %s time(s): %s',
                    $fails + 1,
                    $e->getMessage()
                ));

                /* Take a nap
                 */
                sleep(self::NAP_TIMEOUT);
            }

            /* Increment counter and try again
             */
            $fails++;
        }

        /* Died after too many retries; log it, leave it
         */
        if (self::NUM_RETRY === $fails) {
            $this->log->warning(sprintf(
                'Profile picture: could not obtain profile picture for %s %s (%s) after multiple retries; skipping',
                $baseProfile->getFirstname(),
                $baseProfile->getLastname(),
                $baseProfile->getExternalId()
            ));

            return;
        }

        if (!$image) {
            $this->log->debug(sprintf(
                'Profile picture: could not decode image data for %s %s (%s)',
                $baseProfile->getFirstname(),
                $baseProfile->getLastname(),
                $baseProfile->getExternalId()
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
            $portrait = $this->storageMapper->acceptStream($baseProfile->getExternalId() . '.jpg', $image);

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


    /**
     * From an arbitrary $details array, find Organisation*Details nodes
     * for the given role type and mine out a url accordingly (they're
     * in different places depending on your roletype...)
     *
     * Always returns a string. Empty string means one isn't found or
     * wasn't specified which equates to the same outcome: useless
     *
     * @param int   $type
     * @param array $person
     * @return string
     */
    private function findPortraitUrl(int $type, array $person): string
    {
        /* publishPhotoToInternal isn't found inside studentDetails (disgusting!)
         * despite what their api docs suggested.
         *
         * Check whether it's false; if it's false, we don't import their pictures
         *
         * There's field duplication and their docs contradict each setting.
         * Trust this $person one to be canonical.
         *
         * Disgusting.
         */
        if (!isset($person['publishPhotoToInternal']) || !$person['publishPhotoToInternal']) {
            return '';
        }

        /* Find the first Organisation*Details node among all the $details
         * nodes that compass might provide.
         */
        if ($type === self::BASEROLE_STUDENT) {
            $node = 'studentDetails';
            $nodeIdentifier = 'OrganisationStudentDetails:';
        } elseif ($type === self::BASEROLE_STAFF) {
            $node = 'staffDetails';
            $nodeIdentifier = 'OrganisationStaffDetails:';
        } else {
            /* Other roletypes don't get pictures
             */
            return '';
        }

        /* Dodgy dig into the darkness
         */
        foreach ($person[$node] as $detail) {
            if (str_contains($detail['__type'], $nodeIdentifier)) {
                if ($this->isActiveDetailsField($detail)) {
                    return trim((string)$detail['getPersonPhotoUrl']);
                }
            }
        }

        return '';
    }
}
