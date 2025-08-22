<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\Connector\Example\Base\Endpoint\IApi;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
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
 * @package Schoolbox\External\Connector\Example\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


    /**
     * Profile constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param Watchlist $watcher
     * @param array $watchlist
     * @param UserProfile\Mapper $baseProfileMapper
     * @param UserProfile\Student\Mapper $studentProfileMapper
     * @param UserProfile\Parent\Mapper $parentProfileMapper
     * @param UserProfile\Staff\Mapper $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private Watchlist $watcher,
        private array $watchlist,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper
    ) {
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
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
     */
    private function importStudents(): void
    {
        /* TODO Get a set of students that we need to process
         */
        $students = [];

        foreach ($students as $student) {
            /* TODO Fill in these basic student vars
             */
            $externalId = '';

            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!$this->watcher->isOnWatchlist($externalId, $this->watchlist)) {
                continue;
            }

            /* TODO Fill in these basic student vars
             */
            $academicHouse = '';
            $campusName = '';

            $dob = '';
            $email = '';
            $firstname = '';
            $firstnamePreferred = '';

            /* TODO Sometimes these are together, sometimes not
             */
            $form = '';
            $formTeacher = $this->findFormTeacher();

            $gender = '';
            $housemaster = $this->findHousemaster();
            $lastname = '';
            $mobile = '';
            $photoRedacted = false;
            $studiesCode = '';
            $title = '';

            /* TODO Sometimes these are together, sometimes not
             */
            $tutor = $this->findTutor();
            $tutor = '';
            $tutorGroup = '';

            $username = '';
            $yearLevel = '';
            $yearLevelCoordinator = $this->findYearLevelCoordinator();

            /* TODO Fill in these dates
             */
            $enrolmentDate = '';
            $leavingDate = '';

            /* Flags
             * TODO Fill in these flag vars
             */
            $boardingStatus = false;
            $esl = false;
            $ib = false;
            $ilp = false;

            /* Alerts
             * TODO Define the mechanism to find these alert contents
             */
            $comments = $this->findComments();
            $legal = $this->findLegal();
            $medical = $this->findMedicalAlert();
            $messages = '';
            $specialNeeds = $this->findSpecialNeeds();

            /* Emergency Contacts
             */
            $emergencyContacts = $this->findEmergencyContacts();

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
                ->setEnrolmentDate($enrolment)
                ->setFormName($form)
                ->setFormTeacherId($formTeacher['id'])
                ->setFormTeacherName($formTeacher['name'])
                ->setGender($gender)
                ->setHeadOfHouseId($housemaster['id'])
                ->setHeadOfHouseName($housemaster['name'])
                ->setHouseName($academicHouse)
                ->setLeavingDate($leaving)
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
            } else {
                /* This student doesn't have any emergency contacts anymore
                 * Clear whatever was there
                 */
                $studentProfile
                    ->setEmergencyContactName1(null)
                    ->setEmergencyContactPhone1(null)
                    ->setEmergencyContactName2(null)
                    ->setEmergencyContactPhone2(null)
                    ->setEmergencyContactName3(null)
                    ->setEmergencyContactPhone3(null);
            }

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding student profile for %s (%s)',
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->studentProfileMapper->save($studentProfile);
            }

            /* Add their parents' profile data
             */
            $this->importParentsOfStudent($externalId, $studentBaseProfile);
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
     * @param string|null $date
     * @return Alaress_Date_Format|null
     */
    public function findDate(?string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @return array
     */
    public function findEmergencyContacts(): array
    {
        $emergencyContacts = [];
        $contacts = [];
        $n = 0;

        foreach ($emergencyContacts as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                /* TODO Fill in these emergency contact vars
                 */
                $fullname = '';
                $relation = '';
                $phone = '';

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
     * @return array
     */
    private function findHousemaster(): array
    {
        /* TODO Fill in these housemaster vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find legal alerts
     *
     * @return string|null
     */
    private function findLegal(): ?string
    {
        /* TODO Fill in these legal alert vars
         */
        return null;
    }


    /**
     * Find medical data for student
     *
     * @return string|null
     */
    private function findMedicalAlert(): ?string
    {
        /* TODO Fill in these medical alert vars
         */
        return null;
    }


    /**
     * Find out if this student has special needs
     *
     * @return bool
     */
    private function findSpecialNeeds(): bool
    {
        /* TODO Fill in these special needs vars
         */
        return false;
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
     * @param string            $studentExternalId
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile): void
    {
        $parents = [];

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {
            /* TODO Fill in these basic parent vars
             */
            $externalId = '';
            $email = '';
            $firstname = '';
            $firstnamePreferred = '';
            $lastname = '';
            $phoneHome = '';
            $phoneMobile = '';
            $phoneWork = '';
            $relationship = '';
            $title = '';
            $username = '';

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
        $staffs = [];

        foreach ($staffs as $staff) {
            /* TODO Fill in basic staff vars
             */
            $externalId = '';

            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!$this->watcher->isOnWatchlist($externalId, $this->watchlist)) {
                continue;
            }

            /* TODO Fill in basic staff vars
             */
            $firstname = '';
            $firstnamePreferred = '';
            $lastname = '';
            $building = '';
            $campusName = '';
            $category = '';
            $department = '';
            $email = '';
            $phone = '';
            $positionTitle = '';
            $room = '';
            $title = '';
            $username = '';

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
