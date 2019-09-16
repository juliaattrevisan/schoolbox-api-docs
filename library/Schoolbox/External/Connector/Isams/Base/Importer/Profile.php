<?php
/**
 * Profile
 *
 * Imports user_profile data from ISAMS xml feed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Profile implements ImporterInterface
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
     * @var Usermapper
     */
    private $userMapper;


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
     * @var UserProfile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * A mapping between (NCYear, Campus) instances
     *
     * Campuses are stored separately from Students and are joined on NCYear
     * so create a lookup instead of traversing every time
     *
     * @var array
     */
    private $campusMapping = [];


    /**
     * Profile constructor.
     *
     * @param LoggerInterface             $log
     * @param bool                        $perform
     * @param Batchxml                    $batchxml
     * @param UserMapper                  $userMapper
     * @param UserProfile\Mapper          $baseProfileMapper
     * @param UserProfile\Student\Mapper  $studentProfileMapper
     * @param UserProfile\Parent\Mapper   $parentProfileMapper
     * @param UserProfile\Staff\Mapper    $staffProfileMapper
     * @param UserProfile\Relation\Mapper $relationMapper
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        UserMapper $userMapper,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relation\Mapper $relationMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->userMapper = $userMapper;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationMapper = $relationMapper;

        /* Prefetch (Campus, NCYear) relations as a lookup
         */
        $this->campusMapping = $this->prefetchCampuses();
    }


    /**
     * Import user profile data into sis_user_profile*
     *
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function import(): void
    {
        /* Purge old relations before re-inserting them
         */
        if ($this->perform) {
            $this->relationMapper->getDatabase()->query("
                DELETE FROM {$this->relationMapper->getTable()}
            ");
        }

        /* Import students' profile data
         * Import their specific parents' profile data
         */
        $this->log->info('Processing students and their parents.');

        $this->importStudents();

        /* Import staff profile data
         */
//        $this->log->info('Processing staff.');
//
//        $this->importStaff();

        /* Remove any orphaned profiles
         */
        if ($this->perform) {
            $this->log->info('Removing orphaned profiles.');

            (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper
            ))->remove();
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
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function importStudents()
    {
        $students = $this->batchxml->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil');

        /** @var Student $student */
        foreach ($students as $student) {
            print_r($student);
            $externalId = trim((string)$student->SchoolCode);
            $username = trim((string)$student->SchoolCode);
            $firstname = trim((string)$student->Preferredname);
            $lastname = trim((string)$student->Surname);

            /* Students without usernames are skipped
             */
            if (!\strlen($username)) {
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
                ->setTitle(trim((string)$student->Title))
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

            /* Assign campuses if they exist in the mapping
             * Otherwise quietly disregard bad data
             */
            $campusName = null;

            if (isset($this->campusMapping[(int)$student->NCYear])) {
                $campusName = $this->campusMapping[(int)$student->NCYear]->getName();
            }

            $studentProfile
                ->setUsername($username)
                ->setAlertComment(null)
                ->setAlertLegal(null)
                ->setAlertMedical(null)
                ->setAlertMessage(null)
                ->setCampusName($campusName)
                ->setDateOfBirth(\Alaress_Date_Format::newFromTimestamp(strtotime(trim((string)$student->DOB))))
                ->setEmail(trim((string)$student->EmailAddress))
                ->setEnrolmentDate(null)
                ->setFormName(trim((string)$student->Form))
                ->setFormTeacherId(null)
                ->setFormTeacherName(null)
                ->setGender(trim((string)$student->Gender))
                ->setHeadOfHouseId(null)
                ->setHeadOfHouseName(null)
                ->setHouseName(trim((string)$student->AcademicHouse))
                ->setLeavingDate(null)
                ->setMobile(trim((string)$student->MobileNumber))
                ->setStudiesCode(null)
                ->setTutorGroup(null)
                ->setTutorTeacherId(trim((string)$student->Tutor))
                ->setTutorTeacherName(null)
                ->setYearLevel((string)$student->NCYear)
                ->setYearLevelCoordinatorId(null)
                ->setYearLevelCoordinatorName(null)

                /* Flags
                 */
                ->setBoardingStatus(false)
                ->setEsl(null)
                ->setIb(null)
                ->setIndividualLearningPlans(null)
                ->setSpecialNeeds(false);

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

//            /* Add their parents' profile data
//             */
//            $this->importParentsOfStudent($student, $studentBaseProfile);
        }
    }


    /**
     * Mine $nodes for Campus elements and the NCYear levels to which they relate
     *
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function prefetchCampuses(): array
    {
        $campuses = $this->batchxml->fetchXml('/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision');

        $mapping = [];

        foreach ($campuses as $currentResult) {
            $campus = (new Campus())
                ->setId(trim((string)$currentResult->xpath('@Id')[0]))
                ->setCode(trim((string)$currentResult->Code))
                ->setName(trim((string)$currentResult->Name));

            /* Map campuses to their NCYear levels
             */
            $years = $currentResult->xpath('Years/Year[@YearId]');

            foreach ($years as $year) {
                $mapping[(int)$year->xpath('@YearId')[0]->YearId] = $campus;
            }
        }

        return $mapping;
    }

    /**
     * Import parent profile data for a given student
     *
     * @param Student           $student
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function importParentsOfStudent(Student $student, UserProfile\Model $studentBaseProfile)
    {
        /* Find any associated parents
         */
        $parents = $this->findParentsNodes($student);

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {

            /* Parents without usernames are skipped
             */
            if (!\strlen($parent['username'])) {
                $this->log->warning(sprintf(
                    'Skipping base profile for parent %s - no username detected',
                    trim("{$parent['firstname']} {$parent['lastname']}")
                ));

                continue;
            }

            try {
                $parentProfile = $this->parentProfileMapper->findByExternalId($parent['id']);
                $parentBaseProfile = $parentProfile->getBaseProfile();
            } catch (Exception $e) {
                $parentProfile = $this->parentProfileMapper->createModel();
                $parentBaseProfile = $this->baseProfileMapper->createModel();

                $parentProfile->setBaseProfile($parentBaseProfile);
            }

            $parentBaseProfile
                ->setExternalId($parent['id'])
                ->setTitle($parent['title'])
                ->setFirstname($parent['firstname'])
                ->setLastname($parent['lastname']);

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
                ->setPhoneHome($parent['phone']['home'])
                ->setPhoneWork($parent['phone']['work'])
                ->setMobile($parent['phone']['mobile'])
                ->setEmail($parent['email'])
                ->setUsername($parent['username']);

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
                trim($parent['relationship']),
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $relation = $this->relationMapper->createModel()
                    ->setUserProfileId($parentBaseProfile->getId())
                    ->setChildProfileId($studentBaseProfile->getId())
                    ->setEmergencyContact(false)
                    ->setType($parent['relationship']);

                $this->relationMapper->save($relation);
            }
        }
    }




    /**
     * Find the parents of $student
     *
     * @param Student $student
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findParentsNodes(Student $student): array
    {
        /* Find the parents/guardians of this student
         * To do it, we need a specific id value for $student
         * ($student contains multiple ids, some of which seem internal to usams)
         */
        $path = "/iSAMS/PupilManager/CurrentPupils/Pupil[contains(SchoolCode, '{$student->getId()}')]";
        $node = $this->batchxml->fetchXml($path)[0];

        /* This isn't the primary identifier that we use in $student
         * This is some sort of internal relation id in the xml
         */
        $studentId = trim((string)$node->xpath('@Id')[0]);

        /* Use that to find the parents in similar fashion
         * https://stackoverflow.com/questions/3005370/xpath-to-find-nearest-ancestor-element-that-contains-an-element-that-has-an-attr/40333166
         *
         * TODO: I would think there was alternative syntax than "position()=2" but I don't know what it is
         * If isams changes this structure, this will snap (obviously) so yeah, we should pay attention
         */
        $path = "/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil[@Id='{$studentId}']/ancestor::*[position()=2]";
        $parents = $this->batchxml->fetchXml($path);

        $stack = [];

        /* Stack 'em up
         */
        foreach ($parents as $parent) {
            $stack[] = [
                'id' => trim((string)$parent->xpath('@Id')[0]),
                'title' => trim((string)$parent->Title),
                'firstname' => trim((string)$parent->Forename),
                'lastname' => trim((string)$parent->Surname),
                'relationship' => trim((string)$parent->RelationshipRaw),
                'phone' => [
                    'home' =>  trim((string)$parent->Telephone),
                    'work' => trim((string)$parent->Telephone),
                    'mobile' => trim((string)$parent->Mobile),
                ],
                'username' => trim((string)$parent->EmailAddress),
                'email' => trim((string)$parent->EmailAddress),
            ];
        }

        return $stack;
    }


    /**
     * Import staff profile data
     */
    private function importStaff()
    {
        $staffs = $this->batchxml->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember');

        /** @var Staff $staff */
        foreach ($staffs as $staff) {
            $externalId = trim((string)$staff->xpath('@PersonId')[0]->PersonId);
            $username = trim((string)$staff->UserName);
            $firstname = trim((string)$staff->PreferredName);
            $lastname = trim((string)$staff->Surname);

            /* Staff without usernames are skipped
             */
            if (!\strlen($username)) {
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
                ->setTitle(trim((string)$staff->Title))
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

            /* Concat all the position titles, I guess
             */
            $positions = [];

            if ($staff->Roles->Role) {
                foreach ($staff->Roles->Role as $currentRole) {
                    $positions[] = trim((string)$currentRole->Name);
                }
            }

            /* Select the first campus (schoolbox connector only supports one campus per profile)
             */
            $campusName = '';

            if ($staff->Divisions->Division) {
                $campusName = trim((string)$staff->Divisions->Division[0]->Name);
            }

            $staffProfile
                ->setBuilding(null)
                ->setCampusName($campusName)
                ->setCategory(null)
                ->setDepartment(null)
                ->setEmail(trim((string)$staff->SchoolEmailAddress))
                ->setPhone(trim((string)$staff->SchoolMobileNumber))
                ->setPositionTitle(implode(', ', $positions))
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
        }
    }
}
