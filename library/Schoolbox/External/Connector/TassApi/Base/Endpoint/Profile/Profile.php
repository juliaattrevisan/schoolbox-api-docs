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
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
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
     * @param Api                             $api
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
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
        $students = $this->api->retrieveDocument('getStudents', [
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
            $mobile = trim((string)$student['general_details']['mobile_phone']);
            $specialNeeds = trim((string)$student['other_school_details']['learning_support']) === self::SPECIAL_NEEDS;
            $studiesCode = trim((string)$student['general_details']['usi']);
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

//            /* Fish out a staff member via their user code
//             */
//            $tutor = $this->findTutor((string)$student->Tutor);
//
//            /* Fish out a staff member via their user code
//             */
//            $housemaster = $this->findHousemaster($academicHouse);
//
//            /* Find out a staff member via their user code
//             */
//            $formTeacher = $this->findFormTeacher($form);
//
//            /* Find out a staff member via their user code
//             */
//            $yearLevelCoordinator = $this->findYearLevelCoordinator($yearLevel);

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment(null)
                ->setAlertLegal(null)
                ->setAlertMedical(null)
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
            $this->importParentsOfStudent($studentBaseProfile);
        }
    }

//    /**
//     * Find comments info for a given student
//     *
//     * @return string
//     */
//    private function findComments(): string
//    {
//        /* TODO Fill in comments
//          */
//        return '';
//    }


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
     */
    public function findEmergencyContacts(string $studentCode): array
    {

        /* TODO: Apparently Emergency Contacts come out of a not-yet-implemented endpoint getCommunicationRulesDetails
         *  with the commtype = 'ec'...but that endpoint is 501 Not Implemented.
         */
//        $data = $this->api->retrieveDocument('getCommunicationRulesDetails', [
//            'currentstatus' => 'current',
//            'commtype' => 'ec',
//            'code' => $studentCode
//        ]);

        return [];
    }

//
//    /**
//     * Find a form teacher from their form code
//     *
//     * @return array
//     */
//    private function findFormTeacher(): array
//    {
//        /* TODO Fill in these form teacher vars
//         */
//        return [
//            'id' => null,
//            'name' => null
//        ];
//    }
//
//
//    /**
//     * Find a housemaster from their house name
//     *
//     * @return array
//     */
//    private function findHousemaster(): array
//    {
//        /* TODO Fill in these housemaster vars
//         */
//        return [
//            'id' => null,
//            'name' => null
//        ];
//    }
//
//
//    /**
//     * Find legal alerts
//     *
//     * @return string|null
//     */
//    private function findLegal(): ?string
//    {
//        /* TODO Fill in these legal alert vars
//         */
//        return null;
//    }
//
//
//    /**
//     * Find medical data for student
//     *
//     * @param array|null $medical
//     * @return string|null
//     */
//    private function findMedicalAlert(?array $medical): ?string
//    {
//        /* TODO Fill in medical data
//         */
//        return null;
//    }
//
//
//    /**
//     * Find out if this student has special needs
//     *
//     * @return bool
//     */
//    private function findSpecialNeeds(): bool
//    {
//        /* TODO Fill in these special needs vars
//         */
//        return false;
//    }
//
//
//    /**
//     * Find a tutor from their id
//     *
//     * @return array
//     */
//    private function findTutor(): array
//    {
//        /* TODO Fill in these tutor vars
//         */
//        return [
//            'id' => null,
//            'name' => null
//        ];
//    }
//
//
//    /**
//     * Find a year level coordinator from their year level
//     *
//     * @return array
//     */
//    private function findYearLevelCoordinator(): array
//    {
//        /* TODO Fill in these ylc vars
//         */
//        return [
//            'id' => null,
//            'name' => null
//        ];
//    }


    /**
     * Import parent profile data for a given student
     *
     * @param UserProfile\Model $studentBaseProfile
     */
    private function importParentsOfStudent(UserProfile\Model $studentBaseProfile): void
    {

        $data = $this->api->retrieveDocument('getParents', [
            'user_code' => 'all'
        ]);
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {

    }
}
