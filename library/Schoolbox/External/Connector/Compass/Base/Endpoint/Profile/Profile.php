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
     */
    public function import(): void
    {

        /* Import students' profile data
         * Import their specific parents' profile data
         */
        $this->log->info('Processing students and their parents.');

        $this->importStudents();
//
//        /* Import staff profile data
//         */
//        $this->log->info('Processing staff.');
//
//        $this->importStaff();
    }


    /**
     * Import students' profile data
     *
     * @throws \InvalidArgumentException
     */
    private function importStudents(): void
    {

        /* Shank all the students active today
         */
        $students = $this->api->retrieveDocument('person/v1/GetStudents', [
            'lBound' => \Alaress_Date_Format::now()->startOfDay()->getUsingFormat('c'),
            'uBound' => \Alaress_Date_Format::now()->endOfDay()->getUsingFormat('c')
        ]);

        foreach ($students['d'] as $student) {
            $externalId = $this->findExternalId($student['importIdentifiers']);
            $academicHouse = $this->findHouse($student['organisationMilestones']);
            $boardingStatus = $this->findBoardingStatus($student['studentDetails']);
            $campusName = $this->findCampusName($student['organisationMilestones']);
            $comments = '';
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

            $legal = '';
            $messages = '';
            $mobile = '';

            /* Not sure where a studies code equivalent is, but have taken
             * a guess that it's one of govtCode* fields
             */
            $studiesCode = (new FirstMeaningfulValue())->find([
                $student['govtCode1'],
                $student['govtCode2']
            ]);

            print_r("$externalId $firstname $lastname $studiesCode\n");

//            $title = '';
//            $tutor = '';
//            $tutorGroup = '';
//            $username = '';
//            $yearLevel = '';
//
//            /* TODO Fill in these dates
//             */
//            $enrolmentDate = '';
//            $leavingDate = '';
//
//            /* Flags
//             */
//            /* TODO Fill in these flag vars
//             */
//            $esl = false;
//            $ib = false;
//            $ilp = false;
//            $specialNeeds = false;
//
//            /* Find out if this externalId already has a student profile
//             * If one already exists, load it, update it, then save it
//             * Otherwise make a new one for the given id
//             */
//            try {
//                $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
//                $studentBaseProfile = $studentProfile->getBaseProfile();
//            } catch (Exception $e) {
//                $studentProfile = $this->studentProfileMapper->createModel();
//                $studentBaseProfile = $this->baseProfileMapper->createModel();
//
//                $studentProfile->setBaseProfile($studentBaseProfile);
//            }
//
//            $studentBaseProfile
//                ->setExternalId($externalId)
//                ->setTitle($title)
//                ->setFirstname($firstname)
//                ->setLastname($lastname);
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding base profile for %s',
//                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->baseProfileMapper->save($studentBaseProfile);
//            }
//
//            /* TODO Customise these find* methods to suit your implementation
//             *  Maintain the methods' return signature for easiest resolution
//             *  though you are free to customise it as you see fit
//             */
//            /* Fish out alert comments
//             */
//            $alertComments = $this->findComments();
//
//            /* Fish out legal alerts
//             */
//            $alertLegal = $this->findLegal();
//
//            /* Fish out medical alerts
//             */
//            $alertMedical = $this->findMedicalAlert();
//
//            /* Emergency Contacts
//             */
//            $emergencyContacts = $this->findEmergencyContacts();
//
//            /* Find out a staff member via their user code
//             */
//            $formTeacher = $this->findFormTeacher();
//
//            /* Fish out a staff member via their user code
//             */
//            $housemaster = $this->findHousemaster();
//
//            /* Fish out special needs
//             */
//            $specialNeeds = $this->findSpecialNeeds();
//
//            /* Fish out a staff member via their user code
//             */
//            $tutor = $this->findTutor();
//
//            /* Find out a staff member via their user code
//             */
//            $yearLevelCoordinator = $this->findYearLevelCoordinator();
//
//            /* Dates
//             */
//            $enrolment = $this->findDate($enrolmentDate);
//            $leaving = $this->findDate($leavingDate);
//
//            /* Smoosh it all together
//             */
//            $studentProfile
//                ->setUsername($username)
//                ->setAlertComment($alertComments)
//                ->setAlertLegal($alertLegal)
//                ->setAlertMedical($alertMedical)
//                ->setAlertMessage($messages)
//                ->setCampusName($campusName)
//                ->setEmail($email)
//                ->setEnrolmentDate($enrolment)
//                ->setFormName($form)
//                ->setFormTeacherId($formTeacher['id'])
//                ->setFormTeacherName($formTeacher['name'])
//                ->setGender($gender)
//                ->setHeadOfHouseId($housemaster['id'])
//                ->setHeadOfHouseName($housemaster['name'])
//                ->setHouseName($academicHouse)
//                ->setLeavingDate($leaving)
//                ->setMobile($mobile)
//                ->setStudiesCode($studiesCode)
//                ->setTutorGroup($tutorGroup)
//                ->setTutorTeacherId($tutor['id'])
//                ->setTutorTeacherName($tutor['name'])
//                ->setYearLevel($yearLevel)
//                ->setYearLevelCoordinatorId($yearLevelCoordinator['id'])
//                ->setYearLevelCoordinatorName($yearLevelCoordinator['name'])
//
//                /**
//                 * Flags
//                 */
//                ->setBoardingStatus($boardingStatus)
//                ->setEsl($esl)
//                ->setIb($ib)
//                ->setIndividualLearningPlans($ilp)
//                ->setSpecialNeeds($specialNeeds);
//
//            /* Date of birth requires special treatment as ADF doesn't deal with nulls well
//             */
//            if ($dob) {
//                $studentProfile
//                    ->setDateOfBirth(new Alaress_Date_Format($dob, 'Ymd'));
//            }
//
//            /**
//             * Emergency Contacts
//             * ...because we didn't normalise it, I'm hardcoding these
//             */
//            if (\count($emergencyContacts)) {
//                if (isset($emergencyContacts[0])) {
//                    $studentProfile
//                        ->setEmergencyContactName1($emergencyContacts[0]['name'])
//                        ->setEmergencyContactPhone1($emergencyContacts[0]['phone']);
//                }
//
//                if (isset($emergencyContacts[1])) {
//                    $studentProfile
//                        ->setEmergencyContactName2($emergencyContacts[1]['name'])
//                        ->setEmergencyContactPhone2($emergencyContacts[1]['phone']);
//                }
//
//                if (isset($emergencyContacts[2])) {
//                    $studentProfile
//                        ->setEmergencyContactName3($emergencyContacts[2]['name'])
//                        ->setEmergencyContactPhone3($emergencyContacts[2]['phone']);
//                }
//            }
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding student profile for %s',
//                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->studentProfileMapper->save($studentProfile);
//            }
//
//            /* Add their parents' profile data
//             */
//            $this->importParentsOfStudent($externalId, $studentBaseProfile);
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
            $studentSchool = explode('.', $importIdentifier['school'])[0];

            if (strpos($this->api->getHost(), $studentSchool) > -1) {
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

        foreach ($studentDetails as $studentDetail) {
            $studentSchool = explode('.', $studentDetail['organisationName'])[0];

            if (strpos($this->api->getHost(), $studentSchool) > -1) {
                if ('Active' === $studentDetail['status']) {
                    if (in_array($studentDetail['boarderType'], $boarders, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @param array $organisationMilestones
     * @return string
     */
    private function findCampusName(array $organisationMilestones): string
    {
        /* TODO homeCampus is just a guid - I don't know where the lookup is located
         *  so for now, just provide the first active guid corresponding to the current host
         *  (UPS can assign arbitrarily based on this guid)
         */
        foreach ($organisationMilestones as $organisationMilestone) {
            $studentSchool = explode('.', $organisationMilestone['organisationName'])[0];

            if ((int)$organisationMilestone['isActive'] && (int)$organisationMilestone['isCurrent']) {
                if (strpos($this->api->getHost(), $studentSchool) > -1) {
                    return $organisationMilestone['homeCampus'];
                }
            }
        }

        return '';
    }


//    /**
//     * Find comments info for a given student
//     *
//     * @return string
//     */
//    private function findComments(): string
//    {
//        return '';
//    }
//
//
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
     * @return string
     */
    private function findEmail(array $studentDetails): string
    {
        foreach ($studentDetails as $studentDetail) {
            $studentSchool = explode('.', $studentDetail['organisationName'])[0];

            if (strpos($this->api->getHost(), $studentSchool) > -1) {
                if ('Active' === $studentDetail['status']) {
                    return $studentDetail['organisationUserEmail'];
                }
            }
        }

        return '';
    }


//    /**
//     * Find all the meaningful emergency contacts for a student
//     *
//     * @return array
//     */
//    public function findEmergencyContacts(): array
//    {
//        $emergencyContacts = [];
//        $contacts = [];
//        $n = 0;
//
//        foreach ($emergencyContacts as $emergencyContact) {
//            if ($n < self::MAX_EMERGENCY_CONTACTS) {
//                /* TODO Fill in these emergency contact vars
//                 */
//                $fullname = '';
//                $relation = '';
//                $phone = '';
//
//                /* Suffix the $relation if provided
//                 */
//                if ('' !== $relation) {
//                    $fullname .= " ({$relation})";
//                }
//
//                /* Aggregate them if we have a name
//                 */
//                if ('' !== $fullname) {
//                    $contacts[] = [
//                        'name' => $fullname,
//                        'phone' => $phone
//                    ];
//                }
//            }
//
//            $n++;
//        }
//
//        return $contacts;
//    }

    /**
     * A student's Form is "typically groupA" in the payload
     * This manifests as "House" in the ui, so despite its arbitrary name it seems predictable
     *
     * Find the first organisationMilestone that specified a meaningful groupA value
     *
     * @param array $organisationMilestones
     * @return string
     */
    private function findForm(array $organisationMilestones): string
    {
        foreach ($organisationMilestones as $organisationMilestone) {
            if ((int)$organisationMilestone['isActive'] && (int)$organisationMilestone['isCurrent']) {
                if ('' !== trim((string)$organisationMilestone['groupA'])) {
                    return $organisationMilestone['groupA'];
                }
            }
        }

        return '';
    }


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

    /**
     * A student's house is "typically groupC" in the payload
     * This manifests as "House" in the ui, so despite its arbitrary name it seems predictable
     *
     * Find the first organisationMilestone that specified a meaningful groupC value
     *
     * @param array $organisationMilestones
     * @return string
     */
    private function findHouse(array $organisationMilestones): string
    {
        foreach ($organisationMilestones as $organisationMilestone) {
            if ((int)$organisationMilestone['isActive'] && (int)$organisationMilestone['isCurrent']) {
                if ('' !== trim((string)$organisationMilestone['groupC'])) {
                    return $organisationMilestone['groupC'];
                }
            }
        }

        return '';
    }


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
//     * @return string|null
//     */
//    private function findMedicalAlert(): ?string
//    {
//        /* TODO Fill in these medical alert vars
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
//
//
//    /**
//     * Import parent profile data for a given student
//     *
//     * @param string            $studentExternalId
//     * @param UserProfile\Model $studentBaseProfile
//     * @throws Exception
//     * @throws Mapper\DefinitionException
//     * @throws PDOException
//     * @throws RuntimeException
//     * @throws UnexpectedValueException
//     */
//    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile): void
//    {
//        $parents = [];
//
//        /* For every associated parent, find out if they have a parent profile
//         * If one already exists, load it, update it, then save it
//         * Otherwise make a new one for the given id
//         */
//        foreach ($parents as $parent) {
//            /* TODO Fill in these basic parent vars
//             */
//            $externalId = '';
//            $email = '';
//            $firstname = '';
//            $lastname = '';
//            $phoneHome = '';
//            $phoneMobile = '';
//            $phoneWork = '';
//            $relationship = '';
//            $title = '';
//            $username = '';
//
//            try {
//                $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
//                $parentBaseProfile = $parentProfile->getBaseProfile();
//            } catch (Exception $e) {
//                $parentProfile = $this->parentProfileMapper->createModel();
//                $parentBaseProfile = $this->baseProfileMapper->createModel();
//
//                $parentProfile->setBaseProfile($parentBaseProfile);
//            }
//
//            $parentBaseProfile
//                ->setExternalId($externalId)
//                ->setTitle($title)
//                ->setFirstname($firstname)
//                ->setLastname($lastname);
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding base profile for %s',
//                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->baseProfileMapper->save($parentBaseProfile);
//            }
//
//            $parentProfile
//                ->setPhoneHome($phoneHome)
//                ->setPhoneWork($phoneWork)
//                ->setMobile($phoneMobile)
//                ->setEmail($email)
//                ->setUsername($username);
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding parent profile for %s',
//                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->parentProfileMapper->save($parentProfile);
//            }
//
//            /* Manufacture relations
//             */
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding relation between %s, the %s of %s',
//                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
//                trim($relationship),
//                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $relationship = $this->relationshipMapper->createModel()
//                    ->setUserProfile($parentBaseProfile)
//                    ->setChildProfile($studentBaseProfile)
//                    ->setType($relationship);
//
//                $this->relationshipMapper->save($relationship);
//            }
//        }
//    }
//
//
//    /**
//     * Import staff profile data
//     */
//    private function importStaff(): void
//    {
//        $staffs = [];
//
//        foreach ($staffs as $staff) {
//            /* TODO Fill in basic staff vars
//             */
//            $externalId = '';
//
//            $firstname = '';
//            $lastname = '';
//            $building = '';
//            $campusName = '';
//            $category = '';
//            $department = '';
//            $email = '';
//            $phone = '';
//            $positionTitle = '';
//            $room = '';
//            $title = '';
//            $username = '';
//
//            /* Find out if this externalId already has a staff profile
//             * If one already exists, load it, update it, then save it
//             * Otherwise make a new one for the given id
//             */
//            try {
//                $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
//                $staffBaseProfile = $staffProfile->getBaseProfile();
//            } catch (Exception $e) {
//                $staffProfile = $this->staffProfileMapper->createModel();
//                $staffBaseProfile = $this->baseProfileMapper->createModel();
//
//                $staffProfile->setBaseProfile($staffBaseProfile);
//            }
//
//            $staffBaseProfile
//                ->setExternalId($externalId)
//                ->setTitle($title)
//                ->setFirstname($firstname)
//                ->setLastname($lastname);
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding base profile for %s',
//                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->baseProfileMapper->save($staffBaseProfile);
//            }
//
//            $staffProfile
//                ->setBuilding($building)
//                ->setCampusName($campusName)
//                ->setCategory($category)
//                ->setDepartment($department)
//                ->setEmail($email)
//                ->setPhone($phone)
//                ->setPositionTitle($positionTitle)
//                ->setRoom($room)
//                ->setUsername($username);
//
//            /* Log it
//             */
//            $this->log->info(sprintf(
//                'Adding staff profile for %s',
//                trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->staffProfileMapper->save($staffProfile);
//            }
//        }
//    }
}
