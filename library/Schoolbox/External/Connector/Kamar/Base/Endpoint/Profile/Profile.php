<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use SimpleXMLElement;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data from Kamar xml feed
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{

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
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var Registry
     */
    private $registry;


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
     * @var Traverser
     */
    private $traverser;


    /**
     * @var string
     */
    private $campus;


    /**
     * Remember a list of siblingLinks which are vaguely synonymous with a family id
     *
     * Parents' external ids are derived from their students' external ids but this design
     * is flawed when multiple children have the same parents. You can't rely on kamar
     * xml files being processed in a particular order so one set of parents might
     * precede another, and be associated with one student instead of another (or both)
     *
     * To prevent spurious spawning of what are essentially the same parents, remember
     * the siblingLinks so we know that we've processed parents from this particular
     * family already.
     *
     * @var array
     */
    private $siblingLinks = [];


    /**
     * Profile constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param Batchxml                        $batchxml
     * @param Registry                        $registry
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        Registry $registry,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->registry = $registry;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        $this->traverser = new Traverser($batchxml);
    }


    /**
     * Import user profile data into sis_user_profile*
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function import(): void
    {

        /* Remember the one and only campus identifier
         */
        $this->campus = (string)$this->batchxml->fetchXml('/SMSDirectoryData/schools/school')[0]->xpath('@index')[0];

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
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    private function importStudents(): void
    {
        $students = $this->batchxml->fetchXml('/SMSDirectoryData/students/user');

        foreach ($students as $student) {
            $siblingLink = trim((string)$student->siblinglink);
            $externalId = trim((string)$student->xpath('@id')[0]);
            $username = trim((string)$student->username);
            $title = '';
            $firstname = trim((string)$student->firstname);
            $lastname = trim((string)$student->lastname);
            $campus = $this->campus;
            $dob = trim((string)$student->datebirth);
            $email = trim((string)$student->email);
            $enrolmentDate = trim((string)$student->enrolmentdate);
            $form = '';
            $gender = trim((string)$student->genderpreferred);
            $academicHouse = trim((string)$student->house);
            $leavingDate = trim((string)$student->leavingdate);
            $mobile = trim((string)$student->mobile);
            $nsn = trim((string)$student->nsn);
            $tutorGroup = trim((string)$student->tutor);
            $yearLevel = trim((string)$student->yearlevel);

            /* Flags
             */
            $boardingStatus = trim((string)$student->boarder) === 'Full';
            $esl = (bool)trim((string)$student->esol);
            $medicalAlert = trim((string)$student->flags->conditions);
            $specialNeeds = (bool)trim((string)$student->flags->specialneeds);
            $comments = trim((string)$student->flags->notes);

            /* Students without usernames are skipped
             */
            if ('' === $username) {
                $this->log->warning(sprintf(
                    'Skipping base profile for student %s - no username detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Find out if this externalId already has a student profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                $studentBaseProfile = $studentProfile->getBaseProfile();
            } catch (Exception $e) {
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

            /* Fish out a staff member via their user code
             */
            $tutor = $this->traverser->findTutor((string)$student->Tutor);

            /* Fish out a staff member via their user code
             */
            $housemaster = $this->traverser->findHousemaster($academicHouse);

            /* Find out a staff member via their user code
             */
            $formTeacher = $this->traverser->findFormTeacher($form);

            /* Find out a staff member via their user code
             */
            $yearLevelCoordinator = $this->traverser->findYearLevelCoordinator($yearLevel);

            /* Dates
             */
            $enrolment = $this->traverser->findDate($enrolmentDate);
            $leaving = $this->traverser->findDate($leavingDate);

            /* Emergency Contacts
             */
            $emergencyContacts = $this->traverser->findEmergencyContacts($student);

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment($comments)
                ->setAlertLegal(null)
                ->setAlertMedical($medicalAlert)
                ->setAlertMessage(null)
                ->setCampusName($campus)
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
                ->setStudiesCode($nsn)
                ->setTutorGroup($tutorGroup)
                ->setTutorTeacherId($tutor['id'])
                ->setTutorTeacherName($tutor['name'])
                ->setYearLevel($yearLevel)
                ->setYearLevelCoordinatorId($yearLevelCoordinator['id'])
                ->setYearLevelCoordinatorName($yearLevelCoordinator['name'])
                ;

            /* Date of birth requires special treatment as ADF doesn't deal with nulls well
             */
            if ($dob) {
                $studentProfile
                    ->setDateOfBirth(new \Alaress_Date_Format($dob, 'Ymd'));
            }

            /**
             * Flags
             */
            $studentProfile
                ->setBoardingStatus($boardingStatus)
                ->setEsl($esl)
                ->setIb(null)
                ->setIndividualLearningPlans(null)
                ->setSpecialNeeds($specialNeeds)
                ;

            /**
             * Emergency Contacts
             * ...because we didn't normalise it, I'm hardcoding these
             */
            if (\count($emergencyContacts) && isset($emergencyContacts[0])) {
                $studentProfile
                    ->setEmergencyContactName1($emergencyContacts[0]['name'])
                    ->setEmergencyContactPhone1($emergencyContacts[0]['phone']);

                if (isset($emergencyContacts[1])) {
                    $studentProfile
                        ->setEmergencyContactName2($emergencyContacts[1]['name'])
                        ->setEmergencyContactPhone2($emergencyContacts[1]['phone']);

                    if (isset($emergencyContacts[2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts[2]['name'])
                            ->setEmergencyContactPhone3($emergencyContacts[2]['phone']);
                    }
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

            /* Add their parents + relationships too, but only if we haven't already seen them
             */
            $this->importParentsOfStudent($student, $studentBaseProfile);

            /* Remember this person's details
             */
            $this->registry->remember($externalId, $externalId, $title, $firstname, $lastname, $username);
        }
    }


    /**
     * Import parent profile data for a given student
     *
     * @param SimpleXMLElement  $student
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(SimpleXMLElement $student, UserProfile\Model $studentBaseProfile): void
    {
        $siblingLink = trim((string)$student->siblinglink);

        /** @var SimpleXMLElement $parent */
        foreach ($student->caregivers->caregiver as $parent) {

            /* Parents don't have an external unique identifier; therefore we manufacture them
             * Parents also don't have separate first/last names, just a fullname
             * Reassign an existing parent id for a sibling if we encountered them previously
             */
            $caregiverIndex = (string)$parent->xpath('@index')[0];

            if (isset($this->siblingLinks[$siblingLink][$caregiverIndex])) {
                $externalId = $this->siblingLinks[$siblingLink][$caregiverIndex];

                $this->log->info(sprintf(
                    'Reusing parent external id %s for %s',
                    $externalId,
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));
            } else {
                if ('' !== $siblingLink) {

                    /* Derive externalId from siblinglink value
                     *
                     * siblingLink comes in the format "201800016039" where the first four digits
                     * are the year the oldest sibling was spawned; the next chars supply space for
                     * an autoincrementing primary key integer; 16039, in this case
                     *
                     * We only care for the "16039" part, so strip the first for digits,
                     * then ltrim away any useless leading zeroes, then suffix the caregiver index
                     *
                     * Then remember this for other siblings,
                     * for students who have siblings but their parents haven't been seen yet:
                     */
                    $externalId = ltrim(substr($siblingLink, 4), '0') . '-' . $caregiverIndex;

                    $this->siblingLinks[$siblingLink][$caregiverIndex] = $externalId;
                } else {

                    /* Derive externalId from the student external id value
                     * For students who don't have siblings, their siblingLink value will be empty:
                     */
                    $externalId = trim((string)$student->xpath('@id')[0]) . '-' . $caregiverIndex;
                }

                $this->log->info(sprintf(
                    'Creating parent external id %s for %s',
                    $externalId,
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));
            }

            /* Parents' names are all crammed together so arbitrarily split them
             * Sorry to people with multiple strings in their name like Grant van der Kruk;
             * your name might not display as intended. Blame Kamar for that.
             */
            $parentName = explode(' ', trim((string)$parent->name), 2);

            $username = $email = trim((string)$parent->email);
            $firstname = $parentName[0] ?? '';
            $lastname = $parentName[1] ?? '';
            $phoneHome = '';
            $phoneWork = '';
            $phoneMobile = trim((string)$parent->mobile);
            $relationship = trim((string)$parent->relationship);
            $title = '';

            /* Parents without usernames are skipped
             */
            if ('' === $username) {
                $this->log->warning(sprintf(
                    'Skipping base profile for parent %s - no username detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

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

            /* Remember this person's details
             */
            $this->registry->remember($externalId, $externalId, $title, $firstname, $lastname, $username);
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        $staffs = $this->batchxml->fetchXml('/SMSDirectoryData/staff/user');

        foreach ($staffs as $staff) {
            $externalId = trim((string)$staff->xpath('@id')[0]);
            $username = trim((string)$staff->username);
            $title = trim((string)$staff->title);
            $firstname = trim((string)$staff->firstname);
            $lastname = trim((string)$staff->lastname);
            $campus = $this->campus;
            $category = trim((string)$staff->classification);
            $email = trim((string)$staff->email);
            $phone = trim((string)$staff->mobile);
            $position = trim((string)$staff->position);

            /* Staff without usernames are skipped
             */
            if ('' === $username) {
                $this->log->warning(sprintf(
                    'Skipping base profile for staff %s - no username detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Find out if this externalId already has a staff profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
                $staffBaseProfile = $staffProfile->getBaseProfile();
            } catch (Exception $e) {
                $staffProfile = $this->staffProfileMapper->createModel();
                $staffBaseProfile = $this->baseProfileMapper->createModel();

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

            /* Concat all the Department titles, I guess
             */
            $departments = [];

            if ($staff->groups) {
                foreach ($staff->groups->department as $currentDepartment) {
                    $departments[] = trim((string)$currentDepartment->name);
                }
            }

            $staffProfile
                ->setBuilding(null)
                ->setCampusName($campus)
                ->setCategory($category)
                ->setDepartment(implode(', ', $departments))
                ->setEmail($email)
                ->setPhone($phone)
                ->setPositionTitle($position)
                ->setRoom(null)
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

            /* Remember this person's details
             */
            $this->registry->remember($externalId, $externalId, $title, $firstname, $lastname, $username);
        }
    }
}
