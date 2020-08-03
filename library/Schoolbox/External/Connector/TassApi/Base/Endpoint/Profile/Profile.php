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
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

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
     * @param Api[]                           $api
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

        /* Remove any orphaned profiles
         */
        if ($this->perform) {
            $orphans = (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))->remove();

            $this->log->info("Removed {$orphans} orphaned profiles.");
        }
    }


    /**
     * Import students' profile data
     */
    private function importStudents(): void
    {
        $parents = $this->preloadParentLookup();

        $students = $this->api[DSNCodec::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudents', [
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
            $specialNeeds = trim((string)$student['other_school_details']['learning_support']) === self::SPECIAL_NEEDS;
            $studiesCode = $this->findStudiesCode($student);
            $title = '';
            $tutorGroup = trim((string)$student['school_details']['pc_tutor_group']);
            $username = trim((string)$student['general_details']['student_code']);
            $yearLevel = trim((string)$student['school_details']['year_group']);


            /* Dates
             */
            $enrolment = $this->findDate(trim((string)$student['general_details']['date_of_entry']));
            $leaving = $this->findDate(trim((string)$student['general_details']['date_of_leaving']));

            /* Find out if this externalId already has a student profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                $studentBaseProfile = $studentProfile->getBaseProfile();
            } catch (\Schoolbox\Mapper\Exception $e) {
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
            $this->importParentsOfStudent($studentBaseProfile, $parents);
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
        $emergencyContacts = $this->api[DSNCodec::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
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

                /* Sigh mothers and fathers appear in the same record
                 * To prevent duping, designate a key for them
                 */
                $father = "{$emergencyContact['f_preferred_name']} {$emergencyContact['f_surname']}";
                $mother = "{$emergencyContact['m_preferred_name']} {$emergencyContact['m_surname']}";

                /* Phone numbers are indeterminant; we can't tell to whom they belong
                 * because this is a family record - because Tass
                 */
                $homePhone = $emergencyContact['home_phone'];

                /* Find first non-empty mobile phone number (null !== '')
                 */
                $mobilePhones = array_filter([$emergencyContact['mobile_phone_1'], $emergencyContact['mobile_phone_2']]);
                $mobilePhone = \count($mobilePhones) ? $mobilePhones[0] : '';

                /* Father record seems to precede mother record...as per "traditional norms"
                 */
                $stack[$father] = [
                    'name' => $father,
                    'phone' => (string)$homePhone,
                    'alt' => (string)$mobilePhone
                ];

                $stack[$mother] = [
                    'name' => $mother,
                    'phone' => (string)$emergencyContact['home_phone'],
                    'alt' => (string)$mobilePhone
                ];
            }
        }

        return array_values($stack);
    }


    /**
     * Studies Code is one of five possible fields that could exist
     *
     * Each of the filve possible fields depends on which state you're in
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
     * @throws RuntimeException
     * @throws DomainException
     */
    private function findMedicalAlert(string $studentCode): ?string
    {
        $medicalConditions = $this->api[DSNCodec::MODULE_STUDENT_DETAILS]->retrieveDocument('getMedicalConditions', [
            'studcode' => $studentCode,
        ]);

        /* Count the number of medical conditions that afflict this student
         *
         * If there are any, just leave a message about that, but don't list
         * the specific details in case there are too many, or there are
         * sensitive topics that we shouldn't reveal
         */
        if (\count($medicalConditions['data'])) {
            return 'This student has medical conditions.';
        }

        return null;
    }


    /**
     * Preload parents into a lookup
     *
     * This is probably going to be faster if we build a hash lookup of parents
     */
    private function preloadParentLookup(): array
    {
        $parents = $this->api[DSNCodec::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getParents', [
            'user_code' => 'all'
        ]);

        $lookup = [];
        $index = [];

        foreach ($parents['parents'] as $row) {
            foreach ($row as $parentId => $parent) {

                /* Tass generally adheres to $parent['students'] data structure
                 *
                        Array
                        (
                            [web_access] =>
                            [user_code] => 10620
                            [person2] => Array
                            [person1] => Array
                            ...

                 * ...EXCEPT when they've joined onto an email address table somewhere, at which point
                 * their own api structure mutates slightly into something nonstandard,
                 * where $parent becomes a 0-indexed array instead of the usual key-value tuple content.
                 *
                 * no joke. I can't make this stuff up even on a bad day.

                        Array
                        (
                            [0] => Array
                                (
                                    [web_access] => 1
                                    [user_code] => 10119
                                    [person2] => Array
                                    [address] => Array
                                        (
                                            [4] => Array
                                                (
                                                    [email2] =>
                                                    [email1] => jones@nowhere.com
                                                )
                                        )
                                    [person1] => Array
                                )
                            [1] => Array
                                (
                                    [web_access] => 1
                                    [user_code] => 10119
                                    [person2] => Array
                                    [address] => Array
                                        (
                                            [3] => Array
                                                (
                                                    [email2] =>
                                                    [email1] =>
                                                )
                                            [5] => Array
                                                (
                                                    [email2] =>
                                                    [email1] => jones@nowhere.com
                                                )
                                        )
                                    [person1] => Array
                                )
                        )

                 */
                if (isset($parent['students'])) {
                    foreach ($parent['students'] as $studentId) {
                        $lookup[$studentId][$parentId] = $parentId;
                        $index[$parentId] = $parent;
                    }
                } else {
                    $this->log->critical("Parent records for parent {$parentId} malformed.");
                }
            }
        }

        return [
            'lookup' => $lookup,
            'index' => $index
        ];
    }

    /**
     * Import parent profile data for a given student
     *
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $parents
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile, array $parents): void
    {

        /* Parents that were preloaded for this student will exist in the lookup array
         * Otherwise the student doesn't have any parents in tass, for whatever reason
         */
        $studentId = $studentBaseProfile->getExternalId();

        if (isset($parents['lookup'][$studentId])) {
            foreach ($parents['lookup'][$studentId] as $parentId => $value) {
                $this->log->info("Adding parent {$parentId} for student {$studentId} {$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}");

                print_r($parents['index'][$parentId]);

                /* Assign vars as usual
                 */


                /* Save it
                 */
            }
        } else {
            $this->log->info("No parents found for student {$studentId} {$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}");
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {

    }
}
