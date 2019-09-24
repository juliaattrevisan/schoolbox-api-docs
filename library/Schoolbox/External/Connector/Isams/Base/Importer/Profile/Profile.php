<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use Schoolbox\External\Connector\Isams\Base\Importer\IStrategy;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data from ISAMS xml feed
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer
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
     * @var UserProfile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * Profile constructor.
     *
     * @param LoggerInterface             $log
     * @param bool                        $perform
     * @param Batchxml                    $batchxml
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
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relation\Mapper $relationMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationMapper = $relationMapper;
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
        $this->log->info('Processing staff.');

        $this->importStaff();

        /* Remove any orphaned profiles
         */
        if ($this->perform) {
            $orphans = (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper
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
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function importStudents(): void
    {
        /* Prefetch (NCYear, Campus) relations as a lookup
         *
         * Campuses are stored separately from Students and are joined on NCYear
         * so create a lookup instead of traversing every time
         */
        $campusMapping = $this->prefetchCampuses();

        $students = $this->batchxml->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil');

        /** @var Student $student */
        foreach ($students as $student) {
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

            if (isset($campusMapping[(int)$student->NCYear])) {
                $campusName = $campusMapping[(int)$student->NCYear]->getName();
            }

            /* Fish out a staff member via their user code
             */
            $tutor = $this->findTutor((string)$student->Tutor);

            /* Fish out a staff member via their user code
             */
            $housemaster = $this->findHousemaster((string)$student->AcademicHouse);

            /* Find out enrolment date if it exists
             */
            $enrolmentDate = null;

            if (trim((string)$student->EnrolmentDate)) {
                $enrolmentDate = \Alaress_Date_Format::newFromTimestamp(strtotime(trim((string)$student->EnrolmentDate)));
            }

            /* Find out a staff member via their user code
             */
            $formTeacher = $this->findFormTeacher(trim((string)$student->Form));

            /* Find out any medical alerts
             */
            $medicalAlert = $this->findMedialAlert(trim((string)$student->xpath('@Id')[0]));

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment(null)
                ->setAlertLegal(null)
                ->setAlertMedical($medicalAlert)
                ->setAlertMessage(null)
                ->setCampusName($campusName)
                ->setDateOfBirth(\Alaress_Date_Format::newFromTimestamp(strtotime(trim((string)$student->DOB))))
                ->setEmail(trim((string)$student->EmailAddress))
                ->setEnrolmentDate($enrolmentDate)
                ->setFormName(trim((string)$student->Form))
                ->setFormTeacherId($formTeacher['formTeacherId'])
                ->setFormTeacherName($formTeacher['formTeacherName'])
                ->setGender(trim((string)$student->Gender))
                ->setHeadOfHouseId($housemaster['housemasterId'])
                ->setHeadOfHouseName($housemaster['housemasterName'])
                ->setHouseName(trim((string)$student->AcademicHouse))
                ->setLeavingDate(null)
                ->setMobile(trim((string)$student->MobileNumber))
                ->setStudiesCode(null)
                ->setTutorGroup(null)
                ->setTutorTeacherId($tutor['tutorId'])
                ->setTutorTeacherName($tutor['tutorName'])
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

            /* Add their parents' profile data
             */
            $this->importParentsOfStudent($externalId, $studentBaseProfile);
        }
    }


    /**
     * Find a tutor from their id
     *
     * @param string $tutorId
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findTutor(string $tutorId): array
    {
        $tutorName = null;

        $tutorId = trim($tutorId);
        $node = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[contains(UserCode, '{$tutorId}')]");

        if ($node) {
            $tutor = $node[0];
            $tutorName = trim((string)$tutor->FullName);
        }

        return [
            'tutorId' => $tutorId,
            'tutorName' => $tutorName,
        ];
    }


    /**
     * Find a housemaster from their house name
     *
     * @param string $houseName
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findHousemaster(string $houseName): array
    {
        $housemasterId = null;
        $housemasterName = null;

        $houseName = trim($houseName);
        $node = $this->batchxml->fetchXml("/iSAMS/SchoolManager/AcademicHouses/House[contains(Name, '{$houseName}')]");

        if ($node) {
            $house = $node[0];

            $housemasterId = trim((string)$house->HouseMaster);
            $node = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[contains(UserCode, '{$housemasterId}')]");

            if ($node) {
                $housemaster = $node[0];
                $housemasterName = trim((string)$housemaster->FullName);
            }
        }

        return [
            'housemasterId' => $housemasterId,
            'housemasterName' => $housemasterName
        ];
    }


    /**
     * Find a form teacher from their form code
     *
     * @param string $form
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findFormTeacher(string $form): array
    {
        $formTeacherId = null;
        $formTeacherName = null;

        $form = trim($form);
        $node = $this->batchxml->fetchXml("/iSAMS/SchoolManager/Forms/Form[@Id='{$form}']");

        if ($node) {
            $form = $node[0];

            $formTeacherId = trim((string)$form->Tutor);
            $node = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[contains(UserCode, '{$formTeacherId}')]");

            if ($node) {
                $formTeacher = $node[0];
                $formTeacherName = trim((string)$formTeacher->FullName);
            }
        }

        return [
            'formTeacherId' => $formTeacherId,
            'formTeacherName' => $formTeacherName
        ];
    }


    /**
     * Find medical data for student id $studentId
     * This isn't their external id. It's their isams internal PupilId
     *
     * @param string $studentId
     * @return string|null
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findMedialAlert(string $studentId): ?string
    {
        $node = $this->batchxml->fetchXml("/iSAMS/PupilManager/CurrentPupilsHealth/Pupil[@Id='{$studentId}']");

        if ($node && $node[0]->HealthNotes) {
            return trim((string)$node[0]->HealthNotes);
        }

        return null;
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
     * $studentExternalId came from a node in the CurrentPupils node so I make the assumption that
     * we're only getting parents of current pupils and therefore only getting current parents
     *
     * @param string            $studentExternalId
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile): void
    {
        /* Find the parents/guardians of this student
         * To do it, we need a specific id value for $student
         * ($student contains multiple ids, some of which seem internal to isams)
         */
        $path = "/iSAMS/PupilManager/CurrentPupils/Pupil[contains(SchoolCode, '{$studentExternalId}')]";
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

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {
            $externalId = trim((string)$parent->xpath('@Id')[0]);
            $username = trim((string)$parent->EmailAddress);
            $firstname = trim((string)$parent->Forename);
            $lastname = trim((string)$parent->Surname);
            $relationship = trim((string)$parent->RelationshipRaw);

            /* Parents without usernames are skipped
             */
            if (!\strlen($username)) {
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
                ->setTitle(trim((string)$parent->Title))
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
                ->setPhoneHome(trim((string)$parent->Telephone))
                ->setPhoneWork(trim((string)$parent->Telephone))
                ->setMobile(trim((string)$parent->Mobile))
                ->setEmail(trim((string)$parent->EmailAddress))
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
                $relation = $this->relationMapper->createModel()
                    ->setUserProfileId($parentBaseProfile->getId())
                    ->setChildProfileId($studentBaseProfile->getId())
                    ->setEmergencyContact(false)
                    ->setType($relationship);

                $this->relationMapper->save($relation);
            }
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
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
