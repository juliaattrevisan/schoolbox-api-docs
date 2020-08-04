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
use Schoolbox\External\Connector\TassApi\Base\Endpoint\DSNCodec;
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


//    /**
//     * Parents records are actually Family records; Family records contain exactly two nodes,
//     * one for each parent, plus some miscellaneous stuff
//     */
//    private const PARENT_PERSON_NODES = ['person2', 'person1'];
//

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
        /* Build some lookups to save on traversal and api banging
         */
        $families = (new Lookup\Family(
            $this->api[DSNCodec::MODULE_IDENTITY_MANAGEMENT],
            $this->spousePrefix,
            (new Phone($this->api[DSNCodec::MODULE_STUDENT_DETAILS]))->generate()
        ))->generate();

        /* Also alert any broken tass api payloads that we detected in lookups
         */
        foreach ($families['malformed'] as $malformed) {
            $this->log->critical(sprintf(
                'Parent records for family %s malformed.',
                $malformed
            ));
        }

        /* Haven't encounterd any malformed data in this place yet
         */
        $phones = (new Phone($this->api[DSNCodec::MODULE_STUDENT_DETAILS]))->generate();

        /* Process each current student, and their parents too
         */
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
            $this->importParentsOfStudent($studentBaseProfile, $families, $phones);
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
     * Import parent profile data for a given student
     *
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $families
     * @param array             $phones
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile, array $families, array $phones): void
    {
        $studentId = $studentBaseProfile->getExternalId();

        /* Parents that were preloaded for this student will exist in the lookup array
         * Otherwise the student doesn't have any parents in tass, for whatever reason
         */
        if (isset($families['lookup'][$studentId])) {
            foreach ($families['lookup'][$studentId] as $parentId => $value) {
                $this->log->info("Adding parent {$parentId} for student {$studentId} {$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}");

                /* The Family Record contains both parents' data, in adjacent nodes,
                 * with the keys 'person1' and 'person2'. person2 seems to be the father's data
                 * and comes first (mhmm)  and person1 is the mother's. I haven't seen any further
                 * parents in the dev data from Tass, but I presume there to be more.
                 */
                $familyRecord = $families['index'][$parentId];

                /* Each parent in the family
                 */
                foreach (self::PARENT_PERSON_NODES as $node) {
                    $identifier = trim((string)$familyRecord['username']);

//                    $externalId = $this->deriveExternalId($node, $identifier, $familyRecord['sfa_num']);
                    $email = trim((string)$familyRecord[$node]['e_mail']);
                    $firstname = trim((string)$familyRecord[$node]['preferred_name']);
                    $lastname = trim((string)$familyRecord[$node]['surname']);
                    $phoneHome = $phones[$parentId]['homePhone'] ?? '';
                    $phoneMobile = $phones[$parentId]['mobile'] ?? '';
                    $phoneWork = $phones[$parentId]['workPhone'] ?? '';
                    $relationship = trim((string)$familyRecord[$node]['description']);
                    $title = trim((string)$familyRecord[$node]['title']);

                    /* $username is the result of that $externalId calculation
                     * but you can override this in UPS anyway
                     */
                    $username = $externalId;

                    try {
                        $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
                        $parentBaseProfile = $parentProfile->getBaseProfile();
                    } catch (Exception $e) {
                        $parentProfile = $this->parentProfileMapper->createModel();
                        $parentBaseProfile = $this->baseProfileMapper->createModel();

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
        } else {
            $this->log->warning(sprintf(
                'No parents found for student %s %s',
                $studentId,
                "{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"
            ));
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {

    }
}
