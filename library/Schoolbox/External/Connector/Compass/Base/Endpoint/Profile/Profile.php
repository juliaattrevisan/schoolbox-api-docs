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
use RuntimeException;
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
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
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
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    private function importStudents(): void
    {
        $lBound = \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c');
        $uBound = \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c');

        /* Shank all the parents active today
         */
        $this->log->info(sprintf(
            'Downloading parents active between %s and %s...',
            $lBound,
            $uBound
        ));

        /* Save them into a hash lookup because compass guids are used to
         * identify parents and this is will be a fast way to get them
         */
        $families = (new Families($this->api))->generate($lBound, $uBound);

        $this->log->info(sprintf(
            'Downloading students active between %s and %s...',
            $lBound,
            $uBound
        ));

        /* Shank all the students active today
         */
        $students = $this->api->retrieveDocument('person/v1/GetStudents', [
            'lBound' => $lBound,
            'uBound' => $uBound
        ]);

        foreach ($students['d'] as $student) {
            $externalId = $this->findExternalId($student['importIdentifiers']);
            $academicHouse = $this->findHouse($student['organisationMilestones']);
            $boardingStatus = $this->findBoardingStatus($student['studentDetails']);
            $campusName = $this->findCampusName($student['organisationMilestones']);
            $dob = $this->findDate($student['dateOfBirth']);
            $email = $this->findEmail($student['studentDetails']);

            /* Prioritise preferred names over official names
             */
            $firstname = (new FirstMeaningfulValue())->find([
                $student['preferredFirstName'],
                $student['firstName']
            ]);

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
                $this->log->info(sprintf(
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

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment(null)
                ->setAlertLegal(null)
                ->setAlertMedical($medical)
                ->setAlertMessage($messages)
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
                'Adding student profile for %s',
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
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
     * A student has multiple externalIds based on whichever school they're in
     * These import identifiers are not the same if they're involved in multiple schools
     * We have to find the external id relevant to the current school (host)
     *
     * The IApi conveniently knows this, though
     *
     * @param array  $importIdentifiers
     * @return string|null
     */
    private function findExternalId(array $importIdentifiers): ?string
    {
        /* Example usage:
         *
         * $this->api->getHost()        https://wyndhamchristiancollege-integration-vic.compass.education/api/
         * $importIdentifier['school']  wyndhamchristiancollege-integration.vic.edu.au
         *
         * Commonality:                 wyndhamchristiancollege-integration
         */
        foreach ($importIdentifiers as $importIdentifier) {
            if ($this->matchesHost($importIdentifier['school'])) {
                return $importIdentifier['importIdentifier'];
            }
        }

        return null;
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
        $boarders = ['Full Time', 'Part Time', 'Weekday'];

        $boarderType = $this->findDetailsField($studentDetails, 'boarderType');

        return in_array($boarderType, $boarders, true);
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
        /* TODO homeCampus is just a guid - I don't know where the lookup is located
         *  so for now, just provide the first active guid corresponding to the current host
         *  (UPS can assign arbitrarily based on this guid)
         */
        return $this->findOrganisationMilestoneField($organisationMilestones, 'homeCampus');
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string $date
     * @return Alaress_Date_Format|null
     * @throws \InvalidArgumentException
     */
    public function findDate(string $date): ?Alaress_Date_Format
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
     * Decide if $organisation matches the endpoint host in suds
     * This is how we identify records shared across multiple schools
     *
     * @param string $organisationName
     * @return bool
     */
    private function matchesHost(string $organisationName): bool
    {
        $school = explode('.', $organisationName)[0];

        return strpos($this->api->getHost(), $school) > -1;
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
        return $this->matchesHost($detail['organisationName']) && 'Active' === $detail['status'];
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
            if ($this->matchesHost($organisationMilestone['organisationName'])) {
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
                $this->log->info(sprintf(
                    'Skipping: could not find parent %s for %s %s',
                    $relationship['compassPersonToId'],
                    $studentBaseProfile->getFirstname(),
                    $studentBaseProfile->getLastname()
                ));
            }

            /* Otherwise grab them out and process them as usual
             */
            $parent = $families[$relationship['compassPersonToId']];

            /* Start piling in the deets
             */
            $externalId = $this->findExternalId($parent['importIdentifiers']);

            /* If the externalId was empty, skip them
             */
            if (null === $externalId) {
                $this->log->info(sprintf(
                    'Skipping parent %s (no external identifier)',
                    $relationship['compassPersonToId']
                ));

                continue;
            }

            /* TODO Waht about emailAllowed field?
             */
            $email = trim((string)$parent['emailAddress']);

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

            $phoneHome = trim((string)$parent['homePhoneNumber']);
            $phoneMobile = trim((string)$parent['mobileNumber']);
            $phoneWork = '';
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
        $lBound = \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c');
        $uBound = \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c');

        /* Shank all the staff active today
         */
        $this->log->info(sprintf(
            'Downloading staff active between %s and %s...',
            $lBound,
            $uBound
        ));

        /* Shank all the students active today
         */
        $staffs = $this->api->retrieveDocument('person/v1/GetStaff', [
            'lBound' => $lBound,
            'uBound' => $uBound
        ]);

        foreach ($staffs['d'] as $staff) {
            $externalId = $this->findExternalId($staff['importIdentifiers']);

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
            $campusName = '';

            /* I guess I just assume this department for now
             * Use it to indicate teaching/non teaching staff, I suppose
             */
            $category = (int)$this->findDetailsField($staff['staffDetails'], 'isTeachingStaff') ? 'Teaching Staff' : 'Non-Teaching Staff';

            /* This seems to be a set of organisationTitles elements
             */
            $department = implode(', ', $this->findDetailsFieldArray($staff['staffDetails'], 'organisationTitles'));

            /* TODO Email addresses also exist inside staffDetails and are
             *  instance-specific...what's the difference between those
             *  emails and this emailAddress one?
             *  Which one should take precedence?
             */
            $email = $this->findDetailsField($staff['staffDetails'], 'organisationUserEmail');

            /* The "Phone" field in compass ui ends up mapping to homePhoneNumber
             * so it's actually a generic Phone Number field
             */
            $phone = (new FirstMeaningfulValue())->find([
                $staff['homePhoneNumber'],
                $staff['mobileNumber']
            ]);

            $positionTitle = $this->findDetailsField($staff['staffDetails'], 'roleInSchool');

            /* This seems to be Office Location, but I can't save data in the test instance
             */
            $room = $this->findDetailsField($staff['staffDetails'], 'officeLocation');

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
