<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile;

use Alaress_Date_Format;
use DomainException;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup\Phone;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper\Exception;
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
     * Used in the profile category field to identify teacher or employee users coming from TASS into the staff table
     */
    public const EMPLOYEE = 'employee';
    public const TEACHER = 'teacher';

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
     * Set in Admin. This var deconflicts shared usernames by putting a letter in front,
     * because tass doesn't treat parents independently of families.
     *
     * @var string
     */
    private $spousePrefix;


    /**
     * Set in admin, corresponds to external_tass_addnum_father
     *
     * @var int
     */
    private $addnumFather;


    /**
     * Set in admin, corresponds to external_tass_addnum_mother
     *
     * @var int
     */
    private $addnumMother;


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
     * @param Api[]                           $api
     * @param string                          $spousePrefix
     * @param int                             $addnumFather
     * @param int                             $addnumMother
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        string $spousePrefix,
        int $addnumFather,
        int $addnumMother,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->spousePrefix = $spousePrefix;
        $this->addnumFather = $addnumFather;
        $this->addnumMother = $addnumMother;
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
        $this->log->info('Downloading student and parent data (this might take a while)');

        $this->importStudents();

        /* Import staff profile data
         */
        $this->log->info('Processing staff.');
        $this->log->info('Downloading staff data (this might take a while)');

        $this->importStaff();
    }


    /**
     * Import students' profile data
     */
    private function importStudents(): void
    {
        /* Build some lookups to save on traversal and api banging
         */
        $families = (new Lookup\Family(
            $this->api[Codec\DSN::MODULE_IDENTITY_MANAGEMENT],
            $this->spousePrefix,
            $this->addnumFather,
            $this->addnumMother,
            (new Phone($this->api[Codec\DSN::MODULE_STUDENT_DETAILS]))->generate()
        ))->generate();

        /* Also alert any broken tass api payloads that we detected in lookups
         */
        foreach ($families['unknown'] as $unknown) {
            $this->log->warning(sprintf(
                'Cannot identify father/mother records for family record %s',
                $unknown
            ));
        }

        /* Process each current student, and their parents too
         */
        $students = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudents', [
            'currentstatus' => 'current'
        ]);

        foreach ($students['students'] as $student) {
            $externalId = trim((string)$student['general_details']['student_code']);
            $academicHouse = trim((string)$student['school_details']['house']);
            $boardingStatus = trim((string)$student['school_details']['boarder']) === self::BOARDER;
            $campusName = trim((string)$student['school_details']['campus']);
            $dob = trim((string)$student['general_details']['date_of_birth']);
            $email = trim((string)$student['school_details']['email_address']);
            $firstname = trim((string)$student['general_details']['first_name']);
            $form = trim((string)$student['school_details']['form_class']);
            $gender = trim((string)$student['general_details']['gender']);
            $lastname = trim((string)$student['general_details']['surname']);
            $medicalAlert = $this->findMedicalAlert($externalId);
            $mobile = trim((string)$student['general_details']['mobile_phone']);
            $preferredName = trim((string)$student['general_details']['preferred_name']);
            $specialNeeds = trim($student['other_school_details']['learning_support'] ?? '') === self::SPECIAL_NEEDS;
            $studiesCode = $this->findStudiesCode($student);
            $title = '';
            $tutorGroup = trim((string)$student['school_details']['pc_tutor_group']);
            $username = trim((string)$student['general_details']['portal_user_code']);
            $yearLevel = trim((string)$student['school_details']['year_group']);

            /* Dates
             */
            $enrolment = $this->findDate(trim((string)$student['general_details']['date_of_entry']));
            $leaving = $this->findDate(trim((string)$student['general_details']['date_of_leaving']));

            /* Preferred Name (if it contains a truthy value) takes precedence over First Name
             */
            if ('' !== $preferredName) {
                $firstname = $preferredName;
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
                'Adding student profile for %s',
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->studentProfileMapper->save($studentProfile);
            }

            /* Time for importing parents
             */
            $this->importParentsOfStudent($studentBaseProfile, $families);
        }

        /* Show some basic diagnostics
         */
        $this->log->info(sprintf(
            'Nuclear families: %s. Split families: %s',
            $families['count']['nuclear'],
            $families['count']['split']
        ));

        if ($families['count']['unknown']) {
            $this->log->warning(sprintf(
                'Unidentifiable data: %s (%s)',
                $families['count']['unknown'],
                implode(', ', $families['unknown'])
            ));
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
     * @throws RuntimeException
     * @throws DomainException
     */
    public function findEmergencyContacts(string $studentCode): array
    {
        $emergencyContacts = $this->api[Codec\DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
            'currentstatus' => 'current',
            'commtype' => 'ec',
            'code' => $studentCode
        ]);

        /* For some reason, everyone spawns in the 0th element here - not sure what else
         * appears in commrules[1] or commrules[2] etc.
         */
        $stack = [];

        if (\count($emergencyContacts['commrules'])) {
            foreach ($emergencyContacts['commrules'][0]['addresses'] as $emergencyContact) {
                /* Use addressee instead of m_ and f_ fields
                 */
                $name = $emergencyContact['addressee'];

                /* + Assumption: Indeterminant phone numbers
                 * Phone numbers are indeterminant; we can't tell to whom they belong
                 * because this is a family record - because Tass. This could be
                 * a significant hazard for split families.
                 */
                $homePhone = $emergencyContact['home_phone'];

                /* Find first non-empty mobile phone number (null !== '')
                 */
                $mobilePhone = array_values(array_filter([$emergencyContact['mobile_phone_1'], $emergencyContact['mobile_phone_2']]))[0] ?? '';

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
     * @throws DomainException
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
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $families
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile, array $families): void
    {
        $studentId = $studentBaseProfile->getExternalId();

        /* Parents that were preloaded for this student will exist in the lookup array
         * Otherwise the student doesn't have any parents in tass, for whatever reason
         */
        if (isset($families['lookup'][$studentId])) {
            foreach ($families['lookup'][$studentId] as $externalId => $parent) {
                $this->log->info("Adding parent {$externalId} for student {$studentId} {$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}");

                /* Each parent in the family
                 */
                $email = trim((string)$parent['email']);
                $firstname = trim((string)$parent['firstname']);
                $lastname = trim((string)$parent['surname']);
                $phoneHome = trim((string)$parent['home']);
                $phoneMobile = trim((string)$parent['mobile']);
                $phoneWork = trim((string)$parent['work']);
                $relationship = trim((string)$parent['relationship']);
                $title = '';

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
                    ->setEmail($email);

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
        } else {
            $this->log->warning(sprintf(
                'No parents found for student %s %s',
                $studentId,
                "{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"
            ));
        }
    }


    /**
     * Staff members actually consist of two populations: Teachers and Non-Teachers
     *
     * Non-Teachers include IT Admin, Librarians and other support staff in a school
     * who need user accounts but don't teach students
     *
     * @throws DomainException
     * @throws RuntimeException|InvalidArgumentException
     */
    private function findStaff()
    {

        /* Get a list of teaching staff
         */
        $teachers = $this->api[Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeachers', [
            'currentstatus' => 'current'
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

        /* Reformat the Employee struct to be similar to teaching struct
         */
        $employeeSetReformatted = [];

        /* Add a prefix to distinguish them when namespaces collide
         */
        $employeeCodec = new Codec\EmployeeExternalId();

        foreach ($employeeSetDedupe as $employee) {
            /* Apparently there's both title and salutation for some bizarre reason
             */
            $employeeSetReformatted[] = [
                'teacher_code' => $employeeCodec->encode((string)$employee['user_code']),
                'salutation' => $employee['salutation'] ?: $employee['title'],
                'first_name' => $employee['first_name'],
                'preferred_name' => $employee['preferred_name'],
                'surname' => $employee['surname'],
                'school_email_address' => $employee['school_email'],
                'email_address' => $employee['e_mail'],
                'category' => self::EMPLOYEE,
                'department' => $employee['departments'],
                'portal_user_code' => $employee['username'],
                'active_flag' => null,
            ];
        }

        /* Combine the two sets; this contains all the staff members
         * Both teaching and non-teaching, though you can't distinguish
         * between them in UPS except by the contents of teacher_code
         */
        return array_merge($teachers['teachers'], $employeeSetReformatted);
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
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
            $email = trim((string) ($staff['school_email_address'] ?: $staff['email_address']));

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
             * preferred_name, if it exists
             * first_name, if it exists
             * given_names, if it exists
             * default to empty string
             */
            if (isset($staff['preferred_name']) && '' !== (string)$staff['preferred_name']) {
                $firstname = trim((string)$staff['preferred_name']);
            } elseif (isset($staff['first_name']) && '' !== (string)$staff['first_name']) {
                $firstname = trim((string)$staff['first_name']);
            } elseif (isset($staff['given_names']) && '' !== (string)$staff['given_names']) {
                $firstname = trim((string)$staff['given_names']);
            } else {
                $firstname = '';
            }

            $lastname = trim((string)$staff['surname']);
            $phone = '';
            $position = '';
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
                ->setLastname($lastname);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding base profile for %s (user %s)',
                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}"),
                $externalId
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
                ->setPositionTitle($position)
                ->setRoom($room)
                ->setUsername($username);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding staff profile for %s (user %s)',
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
