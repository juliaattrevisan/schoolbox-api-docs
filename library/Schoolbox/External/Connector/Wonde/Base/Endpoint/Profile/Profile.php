<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\Profile;

use Alaress_Date_Format;
use DateTimeZone;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\IApi;
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
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint\Profile
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
     * Number of records per page
     * The maximum is an unwritten 200 or so, apparently
     */
    private const NUM_PER_PAGE = 200;


    /**
     * Decide between teaching and non-teaching staff
     * This goes into sis_user_profile_staff.category
     *
     * These were originally used as values before we assigned
     * the role_code csv instead.  The role_code csv isn't
     * guaranteed to be populated, so you might have to fall
     * back to this previous implementation
     */
    private const EMPLOYEE = 'employee';
    private const TEACHER = 'teacher';


    /**
     * Departments are a kind of Wonde Group
     */
    private const STAFF_DEPARTMENT_TYPE = 'DEPARTMENT';


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
     */
    private function importStudents(): void
    {
        /* Get a set of students that we need to process
         *
         * Includes:
         * Trailing commas leaves you with http 400 from them, because
         * the forgot to do that for you - implode() to dodge around it.
         * Plus, it's easier to add more includes like this
         */
        $nextUrl = sprintf(
            'students?page=%s&per_page=%s&include=%s',
            1,
            self::NUM_PER_PAGE,
            implode(',', [
                'contact_details',
                'contacts',
                'contacts.contact_details',
                'education_details',
                'extended_details',
                'house',
                'identifiers',
                'medical_conditions',
                'permissions',
                'sen_needs',
                'year',
            ])
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $students = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($students['data'] as $student) {
                $externalId     = trim((string)$student['mis_id']);
                $academicHouse  = trim((string)($student['house']['data']['name'] ?? ''));
                $boardingStatus = false;
                $campusName     = '';

                /* Dig out a date of birth if it was set properly
                 */
                $dob = null;

                if (isset($student['date_of_birth']['date'])) {
                    $dob = $this->findDate(
                        (string)$student['date_of_birth']['date'],
                        (string)$student['date_of_birth']['timezone']
                    );
                }

                /* Work Email takes precedence since that's typically their school email
                 * Then Email, I guess
                 *
                 * From Colin 7/3/22:
                 * Primary email vs email. Apologies, the emails can be a bit confusing.
                 * In Australia, the primary email field is the main email field if the SIS
                 * specifies this is the primary email above others that should be used.
                 * The email field is one we populate when either they only supply one email
                 * address field or from work or home emails (home > email). Important to
                 * note that the "work" email field represents the student's school email.
                 */
                $email = (new FirstMeaningfulValue())->find([
                    $student['contact_details']['data']['emails']['work'],
                    $student['contact_details']['data']['emails']['email'],
                ]);

                /* I think legal_forename would be the most formal
                 * so it goes into $firstname, and forename goes into preferred_name
                 */
                $firstname = trim((string)$student['legal_forename']);
                $firstnamePreferred = trim((string)$student['forename']);

                $form           = '';
                $gender         = trim((string)$student['gender']);
                $lastname       = trim((string)$student['surname']);
                $mobile         = '';
                $photoRedacted  = (bool)$student['permissions']['data']['photograph_student'];
                $studiesCode    = trim((string)$student['education_details']['data']['learner_number']);
                $title          = '';
                $tutorGroup     = '';

                /* There's a Username in the identifiers object, but it's not always set
                 * I guess we take a punt about meaningful values, though there's this other
                 * field active_directory_username (surprise, also not mandatory)
                 *
                 * https://docs.wonde.com/docs/api/sync#student-identifiers-object
                 */
                $username = (new FirstMeaningfulValue())->find([
                    $student['identifiers']['data']['username'],
                    $student['identifiers']['data']['active_directory_username'],
                    $externalId
                ]);

                /* Hmm. It turns out that Year Level is a kind of Wonde "Group"
                 */
                $yearLevel = trim((string)($student['year']['data']['name'] ?? ''));

                /* Flags
                 */
                $esl = (bool)$student['extended_details']['data']['english_as_additional_language'];
                $ib  = false;
                $ilp = false;

                /* Alerts
                 */
                $comments = $this->findComments();
                $legal    = $this->findLegal($student['extended_details']);
                $medical  = $this->findMedicalAlert($student['medical_conditions']);
                $messages = '';

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($student['contacts']);

                /* Find out a staff member via their user code
                 */
                $formTeacher = $this->findFormTeacher();

                /* Fish out a staff member via their user code
                 */
                $housemaster = $this->findHousemaster($student['house'] ?? []);

                /* Fish out special needs
                 */
                $specialNeeds = $this->findSpecialNeeds($student['sen_needs'] ?? []);

                /* Fish out a staff member via their user code
                 */
                $tutor = $this->findTutor();

                /* Find out a staff member via their user code
                 */
                $yearLevelCoordinator = $this->findYearLevelCoordinator();

                /* Haven't seen any data for these fields yet, but take a guess anyway
                 */
                $enrolmentDate = null;

                if (isset($student['education_details']['data']['admission_date']['date'])) {
                    $enrolmentDate = $this->findDate(
                        (string)$student['education_details']['data']['admission_date']['date'],
                        (string)$student['education_details']['data']['admission_date']['timezone']
                    );
                }

                $leavingDate = null;

                if (isset($student['education_details']['data']['leaving_date']['date'])) {
                    $enrolmentDate = $this->findDate(
                        (string)$student['education_details']['data']['leaving_date']['date'],
                        (string)$student['education_details']['data']['leaving_date']['timezone']
                    );
                }

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
                    $studentProfile     = $this->studentProfileMapper->findByExternalId($externalId);
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
                    ->setEnrolmentDate($enrolmentDate)
                    ->setFormName($form)
                    ->setFormTeacherId($formTeacher['id'])
                    ->setFormTeacherName($formTeacher['name'])
                    ->setGender($gender)
                    ->setHeadOfHouseId($housemaster['id'])
                    ->setHeadOfHouseName($housemaster['name'])
                    ->setHouseName($academicHouse)
                    ->setLeavingDate($leavingDate)
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
                 */
                $this->importParentsOfStudent($studentBaseProfile, $student['contacts']);
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($students['meta']['pagination'])) {
                $nextUrl = $students['meta']['pagination']['next'] ?? false;
            }
        }
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
     * @param string $timezone
     * @return Alaress_Date_Format|null
     */
    public function findDate(string $date, string $timezone): ?Alaress_Date_Format
    {
        if ($date) {
            $datePart = explode('.', $date)[0];

            /* You can pass $timezone as empty string if you don't want to use a timezone
             * Wonde dates are typically going to come with date and timezone
             */
            if ($timezone) {
                return Alaress_Date_Format::newDatabaseDate($datePart, new DateTimeZone($timezone));
            }

            return Alaress_Date_Format::newDatabaseDate($datePart);
        }

        return null;
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param array $studentContacts
     * @return array
     */
    public function findEmergencyContacts(array $studentContacts): array
    {
        $contacts = [];
        $n = 0;

        foreach ($studentContacts['data'] as $contact) {
            /* If $contact is not an emergency contact, skip over them
             */
            if (!$contact['relationship']['emergency_contact']) {
                continue;
            }

            /* Otherwise process them as usual
             */
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                $fullname = "{$contact['forename']} {$contact['surname']}";
                $relation = $contact['relationship']['relationship'];

                /* Find something meaningful eventually
                 */
                $phone = (new FirstMeaningfulValue())->find([
                    $contact['contact_details']['data']['phones']['mobile'],
                    $contact['contact_details']['data']['phones']['work'],
                    $contact['contact_details']['data']['phones']['home'],
                    $contact['contact_details']['data']['phones']['phone'],
                ]);

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
     * @param array $studentHouse
     * @return array
     */
    private function findHousemaster(array $studentHouse): array
    {
        /* This is an assumption that Wonde can't abide at the moment
         * I'm not certain when they'll map housemasters but this was
         * one way we could potentially do it
         */
//        /* If this house has any employees, dig out the first one
//         */
//        if (isset($studentHouse['data']) && \count($studentHouse['data']['employees']['data'])) {
//            /* "Head of House" meta.role is not hardcoded into wonde
//             *  For now just grab the first human off the stack
//             */
//            $housemaster = $studentHouse['data']['employees']['data'][0];
//
//            return [
//                'id' => trim((string)$housemaster['mis_id']),
//                'name' => trim(sprintf(
//                    '%s %s %s',
//                    trim((string)$housemaster['title']),
//                    trim((string)$housemaster['forename']),
//                    trim((string)$housemaster['surname'])
//                ))
//            ];
//        }

        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find legal alerts
     *
     * @param array $extendedDetails
     * @return string|null
     */
    private function findLegal(array $extendedDetails): ?string
    {
        /* Somewhere in Extended Details, there's a thing
         * custody_alert, custody_details
         */
        if (isset($extendedDetails['custody_alert']) && $extendedDetails['custody_alert']) {
            return $extendedDetails['custody_details'];
        }

        return null;
    }


    /**
     * Find medical data for student
     *
     * @param array $medicalConditions
     * @return string|null
     */
    private function findMedicalAlert(array $medicalConditions): ?string
    {
        if (\count($medicalConditions['data'])) {
            return 'This student has medical conditions.';
        }

        return null;
    }


    /**
     * Find out if this student has special needs
     *
     * @param array $studentSpecialNeeds
     * @return bool
     */
    private function findSpecialNeeds(array $studentSpecialNeeds): bool
    {
        return isset($studentSpecialNeeds['data']) && \count($studentSpecialNeeds['data']);
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
     * @param array             $parents
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(
        UserProfile\Model $studentBaseProfile,
        array $parents
    ): void {

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents['data'] as $parent) {
            /* If $parent is an emergency contact of the student,
             * just skip over them - we're processing those elsewhere
             */
            if ($parent['relationship']['emergency_contact']) {
                continue;
            }

            $externalId = trim((string)$parent['mis_id']);

            /* Note that this node contains the keys 'email, primary, home, work'
             * There are no definitions for the 'email' and 'primary' usages
             * I assume that they kind of alias each other somehow, based on wording
             */
            $email = trim((string)$parent['contact_details']['data']['emails']['email']);

            $firstname = trim((string)$parent['forename']);
            $lastname = trim((string)$parent['surname']);
            $phoneHome = trim((string)$parent['contact_details']['data']['phones']['home']);
            $phoneMobile = trim((string)$parent['contact_details']['data']['phones']['mobile']);
            $phoneWork = trim((string)$parent['contact_details']['data']['phones']['work']);
            $relationship = trim((string)$parent['relationship']['relationship']);
            $title = trim((string)$parent['contact_details']['data']['salutation']);
            $username = $externalId;

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
        $nextUrl = sprintf(
            'employees?page=%s&per_page=%s&include=%s',
            1,
            self::NUM_PER_PAGE,
            implode(',', [
                'contact_details',
                'employment_details',
                'groups',
                'identifiers'
            ])
        );

        /* Collate a list of staff role codes for debug
         * Can't tell if this is a configurable lookup
         *
         * $seenRoleCodes = [];
         */
        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $staffs = $this->api->retrieve('GET', $nextUrl, []);

            /* Wonde apparently only gives us active current staff so we don't need to
             * do much to filter out this result set:
             *
             * From Colin 8/3/22
             * Wonde will only provide the active staff at the school. The only consideration needed
             * is to periodically check if what you have stored is still present in the Wonde API
             * calls and if not remove them.
             */
            foreach ($staffs['data'] as $staff) {
                $externalId = trim((string)$staff['mis_id']);

                /* I think legal_forename would be the most formal
                 * so it goes into $firstname, and forename goes into preferred_name
                 */
                $firstname = trim((string)$staff['legal_forename']);
                $firstnamePreferred = trim((string)$staff['forename']);

                $lastname = trim((string)$staff['surname']);
                $building = '';
                $campusName = '';

                /* This is a csv of role_codes found in the roles array here
                 * It used to check [current, teaching_staff] nodes to infer
                 * whether someone was teaching vs non-teaching staff so if
                 * you need to resurrect that idea, check previous commit
                 * to this one
                 *
                 * $category manifests as meta.category.type in code
                 * which corresponds to Category Type in the UPS ui
                 */
                $category = self::EMPLOYEE;

                if ($staff['employment_details']['data']['teaching_staff']) {
                    $category = self::TEACHER;
                }

                /* Departments are a kind of Wonde Group
                 */
                $department = $this->findGroupName($staff['groups'], self::STAFF_DEPARTMENT_TYPE);

                /* Follows the same precedence scheme as student emails
                 */
                $email = (new FirstMeaningfulValue())->find([
                    $staff['contact_details']['data']['emails']['work'],
                    $staff['contact_details']['data']['emails']['email'],
                ]);

                /* FIXME Controversial - maybe never include phone numbers at all?
                 */
                $phone = trim((string)$staff['contact_details']['data']['phones']['work']);

                $positionTitle = trim((string)$staff['employment_details']['data']['role_text']);
                $room = '';
                $title = trim((string)$staff['contact_details']['data']['salutation']);

                /* Potentially use a third fallback option:
                 * $staff['employment_details']['data']['staff_code']
                 */
                $username = (new FirstMeaningfulValue())->find([
                    $staff['identifiers']['data']['active_directory_username'],
                    $staff['identifiers']['data']['username'],
                ]);

                /* A shortcut for convenience in logging
                 * (Sometimes people don't have all the parts of their name; odd but ok)
                 */
                $fullname = trim("{$firstname} {$lastname}");

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
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($staffs['meta']['pagination'])) {
                $nextUrl = $staffs['meta']['pagination']['next'] ?? false;
            }
        }

        /* Diagnose the lookup
         * print_r($seenRoleCodes);
         */
    }


    /**
     * Takes an array of roles, collects all their role_code values and returns them as a csv
     * The csv will be trimmed, deduped, stringified, sorted
     *
     * UPS will then need to take this collapsed set of values and work its magic on them
     *
     * @param array $roles
     * @return string
     */
    private function findRoleCodes(array $roles): string
    {
        $stack = [];

        foreach ($roles['data'] as $role) {
            $val = trim((string)$role['role_code']);

            $stack[$val] = $val;
        }

        /* Ditch the keys
         */
        $stack = array_values($stack);

        /* Filter out all the empty strings, null values
         * Use a custom callback so 0 is a valid outcome
         */
        $stack = array_filter($stack, static function ($val) {
            return '' !== $val;
        });

        /* That was a partial reimplementation of FirstMeaningfulValue
         */
        sort($stack);

        return implode(', ', $stack);
    }


    /**
     * Find the name of a Wonde Group, corresponding to a given $groupType
     *
     * (Departments are a kind of Wonde Group, for example)
     *
     * @param array  $groups
     * @param string $groupType
     * @return string|null
     */
    private function findGroupName(array $groups, string $groupType): ?string
    {
        foreach ($groups['data'] as $group) {
            if ($groupType === $group['type']) {
                return $group['name'];
            }
        }

        return null;
    }
}
