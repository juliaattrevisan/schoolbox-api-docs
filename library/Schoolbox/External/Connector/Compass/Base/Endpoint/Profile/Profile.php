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
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
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
        $this->log->info('Processing students and their parents.');

        $this->importStudents();

        /* Import staff profile data
         */
        $this->log->info('Processing staff.');

        $this->importStaff();
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
            $externalId = $this->helper->findExternalId($student['importIdentifiers']);
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

            /* If the externalId was empty, skip them
             */
            if (null === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping %s %s (no external identifier)',
                    $firstname,
                    $lastname
                ));

                continue;
            }

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
            $this->importParentsOfStudent($student['relationships'], $studentBaseProfile, $families);
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
                        return $organisationMilestone[$field];
                    }
                }
            }
        }

        return null;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param array             $relationships
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $families
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(array $relationships, UserProfile\Model $studentBaseProfile, array $families): void
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
            }

            /* Otherwise grab them out and process them as usual
             */
            $parent = $families[$relationship['compassPersonToId']];

            /* Start piling in the deets
             */
            $externalId = $this->helper->findExternalId($parent['importIdentifiers']);

            /* If the externalId was empty, skip them
             */
            if (null === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping parent %s (no external identifier)',
                    $relationship['compassPersonToId']
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
                    $parent['firstName'],
                    $parent['lastName'],
                    $externalId
                ));
            }

            /* Prioritise preferred names over official names
             */
            $firstname = (new FirstMeaningfulValue())->find([
                $parent['preferredFirstName'],
                $parent['firstName']
            ]);

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
            $externalId = $this->helper->findExternalId($staff['importIdentifiers']);
            $campusName = $this->findCampusName($staff['organisationMilestones']);

            /* Prioritise preferred names over official names
             */
            $firstname = (new FirstMeaningfulValue())->find([
                $staff['preferredFirstName'],
                $staff['firstName']
            ]);

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
//            $phone = (new FirstMeaningfulValue())->find([
//                $this->findDetailsField($staff['staffDetails'], 'phoneExtension'),
//                $staff['homePhoneNumber'],
//                $staff['mobileNumber']
//            ]);

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
