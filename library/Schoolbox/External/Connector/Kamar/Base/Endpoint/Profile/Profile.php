<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

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
     * Profile constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param Batchxml                        $batchxml
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
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;

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
     * @throws InvalidUriPartException
     */
    public function import(): void
    {

        /* Purge old relations before re-inserting them
         */
        if ($this->perform) {
            $this->relationshipMapper->truncate();
        }

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

        if (!$this->perform) {
            $this->log->info('Planning finished.');
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
     * @throws InvalidUriPartException
     */
    private function importStudents(): void
    {
        $students = $this->batchxml->fetchXml('/SMSDirectoryData/students/user');

        foreach ($students as $student) {
            $externalId = trim((string)$student->uniqueid);
            $username = trim((string)$student->username);
            $title = '';
            $firstname = trim((string)$student->firstname);
            $lastname = trim((string)$student->lastname);
            $dob = trim((string)$student->datebirth);
            $email = trim((string)$student->email);
            $enrolmentDate = trim((string)$student->startingdate);
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
            $specialNeeds = (bool)trim((string)$student->flags->specialneeds);

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
//
//            /* Add their parents' profile data
//             * Messages come from parents' nodes
//             */
//            $messages = $this->importParentsOfStudent($externalId, $studentBaseProfile);

            /* Dates
             */
            $enrolment = $this->traverser->findEnrolmentDate($enrolmentDate);
            $leaving = $this->traverser->findLeavingDate($leavingDate);


            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
//                ->setAlertComment($this->findComments($studentId))
//                ->setAlertLegal(null)
//                ->setAlertMedical($this->findMedicalAlert($studentId))
//                ->setAlertMessage($messages)
//                ->setCampusName($this->findCampusName($campusMapping, (int)$student->NCYear))

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
                    ->setDateOfBirth(\Alaress_Date_Format::newFromTimestamp(strtotime($dob)));
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
        }
    }


    /**
     * Import parent profile data for a given student
     *
     * $studentExternalId came from a node in the CurrentPupils node so I make the assumption that
     * we're only getting parents of current pupils and therefore only getting current parents
     *
     * @param string            $studentExternalId
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile)
    {
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
    }
}
