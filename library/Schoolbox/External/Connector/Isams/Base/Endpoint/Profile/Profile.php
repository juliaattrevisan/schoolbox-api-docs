<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile;

use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;
use Laminas\Uri\Exception\InvalidUriPartException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data from ISAMS xml feed
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * Maximum number of chars that a position_title can be
     * In iSAMS this is an aggregation of all the Roles assigned
     * to a guy. We had precedent for a school giving a dummy
     * account all the roles, which is a lot of chars to concat
     * SA-14272
     *
     * This is utterly unrealistic but we've seen it crash imports
     * when schools are first setting up
     */
    private const MAX_POSITION_LENGTH = 255;


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
     * @var Batchxml
     */
    private Batchxml $batchxml;


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
     * @throws \Laminas\Uri\Exception\InvalidArgumentException
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
     * @throws \Laminas\Uri\Exception\InvalidArgumentException
     * @throws InvalidUriPartException
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

        /* $student is a stdclass derived from xml data,
         * it's not a Normalised Data Structure object here
         */
        foreach ($students as $student) {
            $externalId = trim((string)$student->SchoolId);
            $academicHouse = trim((string)$student->AcademicHouse);
            $boardingHouseName = '';
            $dob = strtotime(trim((string)$student->DOB));
            $firstname = trim((string)$student->Forename);
            $firstnamePreferred = trim((string)$student->PreferredName);
            $form = trim((string)$student->Form);
            $lastname = trim((string)$student->Surname);
            $studentId = trim((string)$student->xpath('@Id')[0]);
            $username = trim((string)$student->UserName);
            $yearLevel = trim((string)$student->NCYear);

            /* A shortcut for convenience in logging
             * (Sometimes people don't have all the parts of their name; odd but ok)
             */
            $fullname = trim("{$firstname} {$lastname}");

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
                ->setTitle(trim((string)$student->Title))
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

            /* Then save it
             */
            if ($this->perform) {
                $this->baseProfileMapper->save($studentBaseProfile);
            }

            /* Fish out a staff member via their user code
             */
            $tutor = $this->findTutor((string)$student->Tutor);

            $this->log->debug(sprintf(
                'Tutor %s (%s) discovered.',
                $tutor['name'],
                $tutor['id']
            ));

            /* Fish out a staff member via their user code
             */
            $housemaster = $this->findHousemaster($academicHouse);

            $this->log->debug(sprintf(
                'Housemaster %s (%s) discovered.',
                $tutor['name'],
                $tutor['id']
            ));

            /* Find out a staff member via their user code
             */
            $formTeacher = $this->findFormTeacher($form);

            $this->log->debug(sprintf(
                'Form Teacher %s (%s) discovered.',
                $tutor['name'],
                $tutor['id']
            ));

            /* Find out a staff member via their user code
             */
            $yearLevelCoordinator = $this->findYearLevelCoordinator($yearLevel);

            $this->log->debug(sprintf(
                'Year Level Coordinator %s (%s) discovered.',
                $tutor['name'],
                $tutor['id']
            ));

            /* Add their parents' profile data
             * Messages come from parents' nodes
             */
            $messages = $this->importParentsOfStudent($externalId, $studentBaseProfile);

            /* Smoosh it all together
             */
            $studentProfile
                ->setUsername($username)
                ->setAlertComment($this->findComments($studentId))
                ->setAlertLegal(null)
                ->setAlertMedical($this->findMedicalAlert($studentId))
                ->setAlertMessage($messages)
                ->setCampusName($this->findCampusName($campusMapping, (int)$student->NCYear))
                ->setEmail(trim((string)$student->EmailAddress))
                ->setEnrolmentDate($this->findEnrolmentDate(trim((string)$student->EnrolmentDate)))
                ->setFormName($form)
                ->setFormTeacherId($formTeacher['id'])
                ->setFormTeacherName($formTeacher['name'])
                ->setGender(trim((string)$student->Gender))
                ->setHeadOfHouseId($housemaster['id'])
                ->setHeadOfHouseName($housemaster['name'])
                ->setHouseName($academicHouse)
                ->setLeavingDate(null)
                ->setMobile(trim((string)$student->MobileNumber))
                ->setStudiesCode(trim((string)$student->UPN))
                ->setTutorGroup(null)
                ->setTutorTeacherId($tutor['id'])
                ->setTutorTeacherName($tutor['name'])
                ->setYearLevel($yearLevel)
                ->setYearLevelCoordinatorId($yearLevelCoordinator['id'])
                ->setYearLevelCoordinatorName($yearLevelCoordinator['name'])
                ->setBoardingHouseName($boardingHouseName)

                /**
                 * Flags
                 */

                /* Boarding Status comes from ISAMS as a client-configurable value
                 *
                 * We can't check for this effectively unless we predetermine what these
                 * statuses could contain and what those statuses mean
                 */
                ->setBoardingStatus(false)
                ->setEsl(null)
                ->setIb(null)
                ->setIndividualLearningPlans(null)

                /* SEN (Special Education Needs)
                 */
                ->setSpecialNeeds($this->findSpecialNeeds($studentId));

            /* Date of birth requires special treatment as ADF doesn't deal with nulls well
             */
            if ($dob) {
                $studentProfile
                    ->setDateOfBirth(new \Alaress_Date_Format($dob, \Alaress_Date_Format::TIMESTAMP));
            }

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding student profile for %s (preferred: %s) (%s)',
                $fullname,
                $studentBaseProfile->getFirstnamePreferred(),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->studentProfileMapper->save($studentProfile);
            }
        }
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @param array $campusMapping
     * @param int   $yearLevel
     * @return string|null
     */
    private function findCampusName(array $campusMapping, int $yearLevel): ?string
    {
        if (isset($campusMapping[$yearLevel])) {
            return $campusMapping[$yearLevel]->getName();
        }

        return null;
    }


    /**
     * Find the enrolment date for student
     *
     * @param string $date
     * @return \Alaress_Date_Format|null
     */
    private function findEnrolmentDate(string $date): ?\Alaress_Date_Format
    {
        $enrolmentDate = null;

        if ($date) {
            $enrolmentDate = \Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return $enrolmentDate;
    }


    /**
     * Find comments info for a given student
     *
     * @param string $studentId
     * @return string
     * @throws RuntimeException
     */
    private function findComments(string $studentId): string
    {
        $nodes = $this->batchxml->fetchXml("/iSAMS/SENManager/Requirements/Type[@PupilId='{$studentId}']");

        $comments = [];

        if ($nodes) {
            foreach ($nodes as $node) {
                $comments[] = trim((string)$node->Type);
            }
        }

        /* Glue together
         */
        return implode("\n", $comments);
    }


    /**
     * Find a tutor from their id
     *
     * @param string $id
     * @return array
     * @throws RuntimeException
     */
    private function findTutor(string $id): array
    {
        return $this->findStaffMemberById(trim($id));
    }


    /**
     * Find a housemaster from their house name
     *
     * @param string $houseName
     * @return array
     * @throws RuntimeException
     */
    private function findHousemaster(string $houseName): array
    {
        $houseName = trim($houseName);
        $nodes = $this->batchxml->fetchXml("/iSAMS/SchoolManager/AcademicHouses/House[Name[text()='{$houseName}']]");

        if ($nodes) {
            return $this->findStaffMemberById(trim((string)$nodes[0]->HouseMaster));
        }

        return [
            'id' => null,
            'name' => null,
        ];
    }


    /**
     * Find a form teacher from their form code
     *
     * @param string $form
     * @return array
     * @throws RuntimeException
     */
    private function findFormTeacher(string $form): array
    {
        $form = trim($form);
        $nodes = $this->batchxml->fetchXml("/iSAMS/SchoolManager/Forms/Form[@Id='{$form}']");

        if ($nodes) {
            return $this->findStaffMemberById(trim((string)$nodes[0]->Tutor));
        }

        return [
            'id' => null,
            'name' => null,
        ];
    }


    /**
     * Find a year level coordinator from their year level
     *
     * @param string $year
     * @return array
     * @throws RuntimeException
     */
    private function findYearLevelCoordinator(string $year): array
    {
        $year = trim($year);
        $nodes = $this->batchxml->fetchXml("/iSAMS/SchoolManager/Years/Year[Code[text()='{$year}']]");

        if ($nodes) {
            return $this->findStaffMemberById(trim((string)$nodes[0]->Tutor));
        }

        return [
            'id' => null,
            'name' => null,
        ];
    }


    /**
     * Find a staff member's node based on identifier $id
     *
     * @param string $id
     * @return array
     * @throws RuntimeException
     */
    private function findStaffMemberById(string $id): array
    {
        $defaultStaff = [
            'id' => $id,
            'name' => ''
        ];

        /* $id is empty; there wasn't a specified Staff;
         * return Unknown Staff as default case
         */
        if ('' === $id) {
            return $defaultStaff;
        }

        /* Otherwise, go hunting for them
         */
        $node = $this->batchxml->fetchXml("/iSAMS/HRManager/CurrentStaff/StaffMember[UserCode[text()='{$id}']]");

        /* Unknown staff: can't find it via its UserCode
         */
        if (empty($node)) {
            return $defaultStaff;
        }

        $staffMember = $node[0];

        return [
            'id' => $id,
            'name' => trim((string)$staffMember->FullName),
        ];
    }


    /**
     * Find medical data for student id $studentId
     * This isn't their external id. It's their isams internal PupilId
     *
     * @param string $studentId
     * @return string|null
     * @throws RuntimeException
     */
    private function findMedicalAlert(string $studentId): ?string
    {
        $node = $this->batchxml->fetchXml("/iSAMS/PupilManager/CurrentPupilsHealth/Pupil[@Id='{$studentId}']");

        /* TODO: HealthNotes DentalNotes HearingNotes OpthalmicNotes could denote Medical Alerts
         * We keep it simple for now
         */
        if ($node && $node[0]->HealthNotes) {
            return trim((string)$node[0]->HealthNotes);
        }

        return null;
    }


    /**
     * Find out if $studentId is on the SEN (Special Education Needs) Registry
     * and that their RegisterFlag > 0
     *
     * @param string $studentId
     * @return bool
     * @throws RuntimeException
     */
    private function findSpecialNeeds(string $studentId): bool
    {
        $node = $this->batchxml->fetchXml("/iSAMS/SENManager/SEN/Register[@PupilId='{$studentId}']/RegisterFlag[. > 0]");

        if ($node) {
            return true;
        }

        return false;
    }


    /**
     * Mine $nodes for Campus elements and the NCYear levels to which they relate
     *
     * @return array
     * @throws RuntimeException
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
     * @return string
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile): string
    {
        /* Find the parents/guardians of this student
         * To do it, we need a specific id value for $student
         * ($student contains multiple ids, some of which seem internal to isams)
         */
        $path = "/iSAMS/PupilManager/CurrentPupils/Pupil[contains(SchoolId, '{$studentExternalId}')]";
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

        /* As per Duane's conclusion:
         * A contact's aggregated SOS field equates to Message Alerts for their student
         */
        $messages = [];

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         *
         * $parent is a stdclass derived from xml data,
         * it's not a Normalised Data Structure object here
         */
        foreach ($parents as $parent) {
            /* Parents/Concats don't have a preferred firstname available
             * Just use their regular one
             */
            $firstname = trim((string)$parent->Forename);
            $firstnamePreferred = $firstname;
            $lastname = trim((string)$parent->Surname);

            /* A shortcut for convenience in logging
             * (Sometimes people don't have all the parts of their name; odd but ok)
             */
            $fullname = trim("{$firstname} {$lastname}");

            /* Inspect the xpath for attributes
             *
             * If you haven't got any attributes, you can't really continue with this $parent
             * as we need their attributes
             */
            $attributes = $parent->xpath('@PersonId');

            if (!\count($attributes)) {
                $this->log->warning(sprintf(
                    'Parent %s missing personId attribute; skipping',
                    $fullname
                ));

                continue;
            }

            /* externalId is derived from PersonId attribute in the xml
             * which might not exist for unknown reasons
             */
            $externalId = trim((string)$attributes[0]);

            /* Assign parents' usernames in UPS
             */
            $username = '';

            $relationship = trim((string)$parent->RelationshipRaw);

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
                ->setTitle(trim((string)$parent->Title))
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

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
                $fullname
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
                $fullname,
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

            /* Accumulate messages
             */
            $sos = trim((string)$parent->SOS);
            if ('' !== $sos) {
                $messages[] = $sos;
            }
        }

        return implode("\n", $messages);
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        $staffs = $this->batchxml->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember');

        /* $staff is a stdclass derived from xml data,
         * it's not a Normalised Data Structure object here
         */
        foreach ($staffs as $staff) {
            $externalId = trim((string)$staff->xpath('@PersonId')[0]->PersonId);
            $username = trim((string)$staff->UserName);
            $firstname = trim((string)$staff->Forename);
            $firstnamePreferred = trim((string)$staff->PreferredName);
            $lastname = trim((string)$staff->Surname);

            /* A shortcut for convenience in logging
             * (Sometimes people don't have all the parts of their name; odd but ok)
             */
            $fullname = trim("{$firstname} {$lastname}");

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
                ->setTitle(trim((string)$staff->Title))
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

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

            $positionTitle = implode(', ', $positions);

            /* Throw a warning about accounts that have an insane configuration
             */
            if (strlen($positionTitle) >= self::MAX_POSITION_LENGTH) {
                $this->log->warning(sprintf(
                    'Staff member %s (preferred: %s) (%s) has a truncated position_title',
                    $fullname,
                    $firstnamePreferred,
                    $externalId
                ));
            }

            /* MAYBE have to use mb_substr but I can't tell if that's needed,
             * or even if isams lets that kind of input occur Anyway, arbitrarily
             * cut at MAX_POSITION_LENGTH chars
             */
            $positionTitle = mb_substr($positionTitle, 0, self::MAX_POSITION_LENGTH, 'UTF-8');

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
                ->setPositionTitle($positionTitle)
                ->setRoom(null)
                ->setUsername($username);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding staff profile for %s (preferred: %s) (%s)',
                $fullname,
                $firstnamePreferred,
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
