<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile;

use Alaress_Date_Format;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * Used in the profile category field to identify teacher or employee users coming from TASS into the staff table
     */
    public const EMPLOYEE = 'employee';
    public const TEACHER = 'teacher';


    /**
     * Value of the school_details.boarder field corresponding to a Boarder
     */
    private const BOARDER = 'Yes';


    /**
     * Value of the other_school_details.learning_support field corresponding to Special Needs
     * Tass seems to supply this one of "Y" or "N"
     */
    private const SPECIAL_NEEDS = 'Y';


    /**
     * One of these five fields will appear in general_details area depending on your tass installation
     */
    private const STUDIES_CODE_POSSIBILITIES = ['lui_number', 'bos_number', 'vsn_number', 'tqa_number', 'wa_student_number'];


    /**
     * An arbitrary string used to prefix Employees' user_code. This field is usually numeric
     * and can directly conflict with student external ids, so you end up with employees' profiles
     * overwriting student profiles. To prevent them matching up like that, we chuck a letter in
     */
    private const EMPLOYEE_PREFIX = 'E';


    /**
     * Default TASS Communication Rule
     */
    private const DEFAULT_COMMUNICATION_RULE = 'all';


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
     * @var array
     */
    private array $api;


    /**
     * @var array
     */
    private array $watchlist;


    /**
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private string $spousePrefix;


    /**
     * TASS communication rules type codes (in comma separated format) for all guardian relationships
     * that can be associated with students
     *
     * Communication Rules ("commrules") decide who can actually be a parent of a student. In TASS
     * there's about a dozen tickboxes which can be turned on or off, for a given parent. Some schools
     * use TKCO tickbox to decide who can be a parent; other schools use another tickbox. This is
     * configurable in admin under "TASS Communication Rules Type Code Filter."
     *
     * The tass api getParents endpoint gives you a list of people who are allegedly parents of students.
     * However, this list does not care about commrules. The getCommunicationRulesDetails endpoint
     * tells you, though - so to decide who is meant to come out of suds as a parent of a student,
     * you need to:
     *
     * a) get the list of potential parents from getParents endpoint
     * b) get the list of commrules for those potential parents, from the getCommunicationRulesDetails endpoint
     * c) if a parent has a commrule found in (b), then they can be a parent as listed in (a)
     *    otherwise, they should be unlisted from (a)
     *
     * $communicationTypes uses "tkco" as a default, if empty in admin
     * Otherwise, use the csv string provided there
     *
     * @var string
     */
    private string $communicationTypes;


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
     * @var bool
     */
    private bool $includePhoto;


    /**
     * @var StorageMapper
     */
    private StorageMapper $storageMapper;


    /**
     * Profile constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param array                           $api
     * @param array                           $watchlist
     * @param bool                            $includePhoto
     * @param string                          $spousePrefix
     * @param string                          $communicationTypes
     * @param Mapper                          $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper                   $storageMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        array $watchlist,
        bool $includePhoto,
        string $spousePrefix,
        string $communicationTypes,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        StorageMapper $storageMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->watchlist = $watchlist;
        $this->includePhoto = $includePhoto;
        $this->spousePrefix = $spousePrefix;

        /* Decide default communication rule identifiers
         */
        $this->communicationTypes = '' === $communicationTypes ? self::DEFAULT_COMMUNICATION_RULE : $communicationTypes;

        /* Various mappers
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
        $this->storageMapper = $storageMapper;
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Import students' profile data
         * Import their specific parents' profile data
         */
//        $this->log->info('Processing students and their parents.');
//        $this->importStudents();

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
        /* Build some lookups to save on traversal and api banging
         */
        $this->log->debug(sprintf(
            'Fetching data using TASS Communication Rules: %s',
            $this->communicationTypes
        ));

        /* If the watchlist const is empty, preload all commrules
         *
         * Otherwise, pass null and let importParentsOfStudent() fetch
         * the comm rules on-demand (this saves you billions of little api
         * calls when you import normally - when you use a watchlist, you'll
         * end up getting comm rules just for those guys on the watchlist)
         */
//        $familiesLookup = null;
        $commRulesLookup = null;

        if (!count($this->watchlist)) {
//            /* Grab all the parents' data in one big request
//             * We need this for the sfa_num for each identifier
//             */
//            $this->log->info('Downloading parent data (this might take a while)');
//
//            $familiesLookup = (new Lookup\Parents($this->api))->generate();

            $this->log->info('Downloading all student commrules (this might take a while)');

            $commRulesLookup = (new Lookup\CommRules($this->api, $this->communicationTypes))->generate();
        } else {
//            $this->log->debug('Downloading parent data for people on the watchlist');
            $this->log->debug('Downloading student commrules for people on the watchlist');
        }

        /* Process each current student, and their parents too.
           The includephoto parameter is available for both:
              https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration/blob/master/getStudents.md
              https://github.com/TheAlphaSchoolSystemPTYLTD/student-details/blob/master/getStudentsDetails.md
           Using getStudents for now, so that we don't have to make a call for each student.
         */
        $this->log->info('Downloading student data (this might take a while)');

        $students = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudents', [
            'currentstatus' => 'current',
            'includephoto'  => $this->includePhoto
        ]);

        foreach ($students['students'] as $student) {
            $externalId = trim((string)$student['general_details']['student_code']);
            $academicHouse = trim((string)$student['school_details']['house']);

            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                continue;
            }

            /* TASS API sometimes won't have this field (optional in its return payload)
             * Disgusting
             */
            $boardingHouseName = trim((string)($student['other_school_details']['residential_house'] ?? ''));

            $boardingStatus = trim((string)$student['school_details']['boarder']) === self::BOARDER;
            $campusName = trim((string)$student['school_details']['campus']);
            $dob = trim((string)$student['general_details']['date_of_birth']);
            $email = trim((string)$student['school_details']['email_address']);

            $firstname = trim((string)$student['general_details']['first_name']);
            $firstnamePreferred = trim((string)$student['general_details']['preferred_name']);

            $form = trim((string)$student['school_details']['form_class']);
            $gender = trim((string)$student['general_details']['gender']);

            /* Some students have a preference in their surname
             * If preferred_surname has a meaningful value, use that in preference to the usual
             */
            $lastname = (new FirstMeaningfulValue())->find([
                $student['general_details']['preferred_surname'],
                $student['general_details']['surname']
            ]);

            $medicalAlert = $this->findMedicalAlert($externalId);
            $mobile = trim((string)$student['general_details']['mobile_phone']);
            $specialNeeds = trim($student['other_school_details']['learning_support'] ?? '') === self::SPECIAL_NEEDS;
            $studiesCode = $this->findStudiesCode($student);
            $title = '';
            $tutorGroup = trim((string)$student['school_details']['pc_tutor_group']);

            /* Usernames are found in portal_user_code or, if that's empty,
             * try looking in alternate_id because that's what we used to do
             */
            $username = (new FirstMeaningfulValue())->find([
                $student['general_details']['portal_user_code'],
                $student['general_details']['alternate_id'],
            ]);

            $yearLevel = trim((string)$student['school_details']['year_group']);

            /* Dates
             */
            $enrolment = $this->findDate(trim((string)$student['general_details']['date_of_entry']));
            $leaving = $this->findDate(trim((string)$student['general_details']['date_of_leaving']));

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

            /* Import profile pictures
             */
            $this->importPortrait($studentBaseProfile, $student['general_details']['student_photo']['file_info'] ?? []);

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
                ->setAlertMedical($medicalAlert)
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
                ->setTutorTeacherId(null)
                ->setTutorTeacherName(null)
                ->setYearLevel($yearLevel)
                ->setYearLevelCoordinatorId(null)
                ->setYearLevelCoordinatorName(null)
                ->setBoardingHouseName($boardingHouseName)

                /**
                 * Flags
                 */

                ->setBoardingStatus($boardingStatus)
                ->setEsl(null)
                ->setIb(null)
                ->setIndividualLearningPlans(null)

                /* Special Needs
                 */
                ->setSpecialNeeds($specialNeeds);

            /* Date of birth requires special treatment as ADF doesn't deal with nulls well
             */
            if ($dob) {
                $studentProfile
                    ->setDateOfBirth($this->findDate($dob));
            }

            /* Find emergency contacts and manually shank them into Emergency Contacts 1, 2, 3
             */
            $emergencyContacts = $this->findEmergencyContacts($externalId);

            /**
             * Emergency Contacts
             * ...because we didn't normalise it, I'm hardcoding these
             */
            if (\count($emergencyContacts)) {
                if (isset($emergencyContacts[0])) {
                    $studentProfile
                        ->setEmergencyContactName1($emergencyContacts[0]['name'])
                        ->setEmergencyContactPhone1($emergencyContacts[0]['phone'])
                        ->setEmergencyContactPhoneAlt1($emergencyContacts[0]['alt']);
                }

                if (isset($emergencyContacts[1])) {
                    $studentProfile
                        ->setEmergencyContactName2($emergencyContacts[1]['name'])
                        ->setEmergencyContactPhone2($emergencyContacts[1]['phone'])
                        ->setEmergencyContactPhoneAlt2($emergencyContacts[1]['alt']);
                }

                if (isset($emergencyContacts[2])) {
                    $studentProfile
                        ->setEmergencyContactName3($emergencyContacts[2]['name'])
                        ->setEmergencyContactPhone3($emergencyContacts[2]['phone'])
                        ->setEmergencyContactPhoneAlt3($emergencyContacts[2]['alt']);
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

            $this->importParentsOfStudent($studentBaseProfile, $commRulesLookup, null);
        }
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string|null $date
     * @return Alaress_Date_Format|null
     * @throws InvalidArgumentException
     */
    public function findDate(?string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return new \Alaress_Date_Format($date, 'd/m/Y');
        }

        return null;
    }


    /**
     * Find all the meaningful emergency contacts for a student
     * We have to hit another api endpoint to find this data
     *
     * @param string $studentCode
     * @return array
     * @throws \JsonException
     */
    public function findEmergencyContacts(string $studentCode): array
    {
        try {
            $emergencyContacts = $this->api[Codec\DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
                'currentstatus' => 'current',
                'commtype' => 'ec',
                'code' => $studentCode
            ]);
        } catch (RuntimeException $e) {
            $this->log->warning(sprintf("Skipping emergency contacts data for student %s.", $studentCode));
            $this->log->warning($e->getMessage());
            return [];
        }

        /* For some reason, everyone spawns in the 0th element here - not sure what else
         * appears in commrules[1] or commrules[2] etc.
         */
        $stack = [];

        if (\count($emergencyContacts['commrules'])) {
            foreach ($emergencyContacts['commrules'][0]['addresses'] as $emergencyContact) {
                /* Use addressee instead of m_ and f_ fields
                 */
                $name = (string)$emergencyContact['addressee'];

                /* + Assumption: Indeterminant phone numbers
                 * Phone numbers are indeterminant; we can't tell to whom they belong
                 * because this is a family record - because Tass. This could be
                 * a significant hazard for split families.
                 */
                $homePhone = $emergencyContact['home_phone'];

                /* Find first non-empty mobile phone number (null !== '')
                 */
                $mobilePhone = (new FirstMeaningfulValue())->find([
                    $emergencyContact['mobile_phone_1'],
                    $emergencyContact['mobile_phone_2']
                ]);

                /* Dedupe anybody being listed twice with the same name
                 * If you have two Tim Smiths who are different humans, they'll be deduped
                 *
                 * We can relax the deduping at the risk of showing the same guy twice,
                 * if we want
                 */
                $stack[$name] = [
                    'name' => $name,
                    'phone' => (string)$homePhone,
                    'alt' => (string)$mobilePhone
                ];
            }
        }

        return array_values($stack);
    }


    /**
     * Studies Code is one of five possible fields that could exist
     *
     * Each of the five possible fields depends on which state you're in
     * so I guess it depends on each tass installation
     *
     * Try each of the five possibilities until one exists
     *
     * @param array $student
     * @return string
     */
    private function findStudiesCode(array $student): string
    {
        $studiesCode = '';

        foreach (self::STUDIES_CODE_POSSIBILITIES as $studiesCodePossibility) {
            if (isset($student['general_details'][$studiesCodePossibility])) {
                $studiesCode = trim((string)$student['general_details'][$studiesCodePossibility]);
                break;
            }
        }

        return $studiesCode;
    }


    /**
     * Find medical data for student
     *
     * @param string $studentCode
     * @return string|null
     * @throws \JsonException
     */
    private function findMedicalAlert(string $studentCode): ?string
    {
        /* Specifically handle a 401 Unauthorized which occurred unexpectedly at shalom
         */
        try {
            $medicalConditions = $this->api[Codec\DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getMedicalConditions', [
                'studcode' => $studentCode,
            ]);
        } catch (\RuntimeException $e) {
            $this->log->warning(sprintf(
                "Skipping medical data for student %s.",
                $studentCode
            ));

            $this->log->warning($e->getMessage());

            return null;
        }

        /* Count the number of medical conditions that afflict this student
         *
         * If there are any, just leave a message about that, but don't list
         * the specific details in case there are too many, or there are
         * sensitive topics that we shouldn't reveal
         *
         * + Assumption: 'data' field contains list of ailments
         */
        if (\count($medicalConditions['data'])) {
            return 'This student has medical conditions.';
        }

        return null;
    }


    /**
     * Import parent profile data for a given student
     *
     * @param Model      $studentBaseProfile
     * @param array|null $commRulesLookup
     * @param array|null $familiesLookup
     * @throws DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile, ?array $commRulesLookup, ?array $familiesLookup): void
    {
        $studentId = $studentBaseProfile->getExternalId();
        $studentFullname = trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}");

        /* Time for importing parents
         */
        $family = (new Lookup\CommRuleFamily($this->api, $this->spousePrefix, $this->communicationTypes))->find($studentId, $commRulesLookup, $familiesLookup);

        /* No parents? No trabajo
         */
        if (!\count($family)) {
            $this->log->warning(sprintf(
                'No parents found for student %s (%s)',
                $studentFullname,
                $studentId
            ));

            return;
        }

        /* Parents that were preloaded for this student will exist in the lookup array
         * Otherwise the student doesn't have any parents in tass, for whatever reason
         */
        foreach ($family as $externalId => $parent) {
            /* Each parent in the family
             */
            $email = trim((string)$parent['email']);
            $firstname = trim((string)$parent['firstname']);
            $firstnamePreferred = trim((string)$parent['firstnamePreferred']);
            $lastname = trim((string)$parent['surname']);
            $phoneHome = trim((string)$parent['home']);
            $phoneMobile = trim((string)$parent['mobile']);
            $phoneWork = trim((string)$parent['work']);
            $relationship = trim((string)$parent['relationship']);
            $title = '';

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
                ->setEmail($email);

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
                'Adding relation between %s (preferred: %s) (%s), the %s of %s (%s)',
                $fullname,
                $firstnamePreferred,
                $externalId,
                $relationship,
                $studentFullname,
                $studentId
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
     * Staff members actually consist of two populations: Teachers and Non-Teachers
     *
     * Non-Teachers include IT Admin, Librarians and other support staff in a school
     * who need user accounts but don't teach students
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \JsonException
     */
    private function findStaff(): array
    {
        /* Get a list of teaching staff
           The includephoto parameter is available for both:
             https://github.com/TheAlphaSchoolSystemPTYLTD/lms-integration/blob/master/getTeachers.md
             https://github.com/TheAlphaSchoolSystemPTYLTD/employee-hr/blob/master/getEmployeesDetails.md
           It is not available for
             https://github.com/TheAlphaSchoolSystemPTYLTD/IdM/blob/master/GetEmployees.md
         */
        $teachers = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeachers', [
            'currentstatus' => 'current',
            'includephoto'  => $this->includePhoto,
        ]);

        /* Get a list of non-teaching staff
         */
        $employees = $this->api[Codec\DSN::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getEmployees', [
            'user_code' => 'all'
        ]);

        /* For every teacher, find out if they have a non-teaching record
         * If so, disregard the non-teaching record and remove it from its set
         */
        $employeeSetDedupe = [];

        foreach ($employees['employees'] as $employee) {
            $value = trim($employee['end_date']);

            /* getEmployees gives you *everyone* that ever existed as an employee
             * even employees who are no longer active - so we have to filter them
             * away based on their end_date field. Anyone who ended in the past
             * should be skipped
             *
             * Empty string assumed to mean they are still active, they haven't ended yet
             */
            if ('' !== $value) {
                /* Trim away microseconds
                 */
                $endDate = new \Alaress_Date_Format(substr($employee['end_date'], 0, 19), \Alaress_Date_Format::DATABASE_FORMAT);

                /* A date in the past means this guy's inactive now; skip him
                 *
                 * It might be possible to set an end date in the future when a person
                 * is scheduled to end their tenure
                 */
                if ($endDate->isPast()) {
                    continue;
                }
            }

            /* Otherwise you're assumed to be current
             */
            $employeeSetDedupe[$employee['user_code']] = $employee;
        }

        /* Unite the two data sets; teaching records get precedence
         * The result is: employeeSetDedupe = Teaching Staff XOR Non-Teaching Staff
         */
        foreach ($teachers['teachers'] as &$teacher) {
            $teacher['category'] = self::TEACHER;

            if ('' !== (string)$teacher['employee_code']) {
                unset($employeeSetDedupe[$teacher['employee_code']]);

                $this->log->debug(sprintf(
                    "Removed teaching staff %s (%s) from non-teaching set",
                    $teacher['employee_code'],
                    $teacher['teacher_code']
                ));
            }
        }

        unset($teacher);

        /* Decide whether we should try to compile profile pictures for Employees too
         */
        $employeeHREnabled = isset($this->api[Codec\DSN::MODULE_EMPLOYEE_HR]);

        /* Reformat the Employee struct to be similar to teaching struct
         */
        $employeeSetReformatted = [];

        /* Add a prefix to distinguish them when namespaces collide
         */
        $employeeCodec = new Codec\EmployeeExternalId();

        foreach ($employeeSetDedupe as $employee) {
             /* Decide salutation
              * These fields could exist, but be null or empty strings
              * Find the first meaningful value
              */
             $salutation = (new FirstMeaningfulValue())->find([
                 $employee['salutation'],
                 $employee['title']
            ]);

            /* Init a position and photo struct
             */
            $position = '';
            $teacherPhoto = [];

            /* Apparently there's both title and salutation for some bizarre reason
             */
            $employeeSetReformatted[] = [
                'employee_code' => $employee['user_code'],
                'teacher_code' => $employeeCodec->encode((string)$employee['user_code']),
                'salutation' => $salutation,
                'first_name' => $employee['first_name'],
                'preferred_name' => $employee['preferred_name'],
                'surname' => $employee['surname'],
                'school_email_address' => $employee['school_email'],
                'email_address' => $employee['e_mail'],
                'category' => self::EMPLOYEE,
                'department' => $employee['departments'],
                'portal_user_code' => $employee['username'],
                'active_flag' => null,
                'teacher_photo' => $teacherPhoto,
                'position' => $position
            ];
        }

        /* Combine the two sets; this contains all the staff members
         * Both teaching and non-teaching, though you can't distinguish
         * between them in UPS except by the contents of teacher_code
         */
        $combinedSet = array_merge($teachers['teachers'], $employeeSetReformatted);

        /* Obtain profile pictures and position title info from this Employee HR place if enabled
         */
        if ($employeeHREnabled) {
            /* Note: it's possible that the employee's user_code is invalid in this endpoint
             * (It can happen if you put a staff member's alphanumeric identifier here
             * instead of an employee's numerical one)
             *
             * Not sure that it actually happens in the wild though
             */
            $details = $this->api[Codec\DSN::MODULE_EMPLOYEE_HR]->retrieveDocument('getEmployeesDetails', [
                'date' => date('d/m/Y'),
                'includephoto' => true,
            ], Api::VERSION_2);

            $employeeLookup = [];

            foreach ($details['employees'] as $employeeDetail) {
                $employeeLookup[$employeeDetail['general']['employee_code']] = $employeeDetail;
            }

            /* Decorate the $combinedSet with the new parts we found elsewhere
             */
            foreach ($combinedSet as &$employeeDetail) {
                $employeeCode = $employeeDetail['employee_code'];

                /* Get the position for every guy
                 */
                $employeeDetail['position'] = $employeeLookup[$employeeCode]['general']['position_title'];

                /* Fetch each Employee's picture if Load Photos From SIS is enabled
                 * Find the piece that closely resembles teacher_photo
                 *
                 * Only need to reassign Employee pictures if enabled;
                 * we got the staff ones separately and don't need to assign them again
                 *
                 * For employees, this seems to be the only way to get photo
                 */
                if ($this->includePhoto && self::EMPLOYEE === $employeeDetail['category']) {
                    $this->log->debug(sprintf(
                        'Fetching picture for employee %s %s (%s)',
                        $employeeDetail['first_name'],
                        $employeeDetail['surname'],
                        $employeeDetail['teacher_code']
                    ));

                    $employeeDetail['teacher_photo'] = $employeeLookup[$employeeCode]['general']['employee_photo'];
                }
            }

            unset($employeeDetail);
        }

        return $combinedSet;
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        $this->log->info('Downloading staff data (this might take a while)');

        $staffs = $this->findStaff();

        foreach ($staffs as $staff) {
            $externalId = trim((string)$staff['teacher_code']);
            $building = '';
            $campusName = '';
            $category = $staff['category'];
            $department = trim((string)$staff['department']);

            /* Find the first non-empty email address for staff; it seems that there's a business
             * email address and a home email address concept going on here
             */
            $email = (new FirstMeaningfulValue())->find([
                $staff['school_email_address'],
                $staff['email_address']
            ]);

            /* For some reason, first_name and preferred_name might not actually be in the payload
                    Array
                    (
                        [surname] => Appleton
                        [active_flag] => Y
                        [email_address] => JAppleton@tassweb.com.au
                        [salutation] => Mr
                        [department] => MAT
                        [portal_user_code] =>
                        [school_email_address] => DHogg@tassweb.com.au
                        [employee_code] => 1000001
                        [teacher_code] => DJH
                        [given_names] => John
                        [category] => teacher
                    )
             *
             * so protect ourselves from this crazy as best we can
             * I think they did something here and broke things in the process
             *
             * Order of precedence:
             *
             * first_name, if it exists
             * given_names, if it exists
             * default to empty string
             */
            $firstname = (new FirstMeaningfulValue())->find([
                $staff['first_name'] ?? null,
                $staff['given_names'] ?? null
            ]);

            /* This sometimes isn't here
             */
            $firstnamePreferred = trim((string)($staff['preferred_name'] ?? null));

            $lastname = trim((string)$staff['surname']);
            $phone = '';
            $position = trim((string)$staff['position']);
            $room = '';
            $title = trim((string)$staff['salutation']);
            $username = trim((string)$staff['portal_user_code']);

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
            $this->importPortrait($staffBaseProfile, $staff['teacher_photo']['file_info'] ?? []);

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
                ->setPositionTitle($position)
                ->setRoom($room)
                ->setUsername($username);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding staff profile for %s (preferred: %s) (%s)',
                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}"),
                $firstnamePreferred,
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
     * @param UserProfile\Model $baseProfile
     * @param array             $fileInfo
     * @return void
     */
    private function importPortrait(UserProfile\Model $baseProfile, array $fileInfo): void
    {
        /* Check if config setting allows photos from sis
         * If it's enabled, continue importing pics
         */
        if (!$this->includePhoto) {
            return;
        }

        /* Start validating the contents of $fileInfo to ensure we have what we need
         *
         * file_info node might not exist; if it doesn't, skip it
         */
        if (!isset($fileInfo['image'])) {
            $this->log->debug(sprintf(
                'Profile picture: no data found for %s %s (%s)',
                $baseProfile->getFirstname(),
                $baseProfile->getLastname(),
                $baseProfile->getExternalId()
            ));

            return;
        }

        /* Try decoding the encoded data
         * If you can't, leave quietly
         */
        $image = base64_decode($fileInfo['image']);

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
         *
         * Not using $fileInfo['file_name'] and $fileInfo['mime_type'] for now
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
}
