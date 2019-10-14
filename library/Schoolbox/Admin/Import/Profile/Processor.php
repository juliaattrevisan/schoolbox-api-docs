<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Profile;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Role;

/**
 * Class Processor
 *
 * Determine which Profile Import files were passed, based on header contents,
 * and process them iteratively
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Processor
{

    /**
     * Strings that denote a csv file
     */
    public const CSV_STUDENT = Role::STUDENT;
    public const CSV_STAFF = Role::STAFF;
    public const CSV_PARENT = Role::PARENT;
    public const CSV_RELATION = 'relation';


    /**
     * Status string for successful row processing
     */
    private const LOG_OK = 'OK';


    /**
     * Status string for a skipped row
     */
    private const LOG_SKIP = 'Skipped';


    /**
     * Status string for some kind of error during processing
     */
    private const LOG_ERROR = 'Error';


    /**
     * Columns in a Parent CSV
     */
    private const IMPORT_COLUMNS_PARENT = [
        'External ID',
        'Type',
        'Username',
        'Title',
        'Firstname',
        'Surname',
        'Email',
        'Mobile',
        'Home Phone',
        'Work Phone'
    ];


    /**
     * Columns in a Student csv
     */
    private const IMPORT_COLUMNS_STUDENT = [
        'External ID',
        'Type',
        'Username',
        'Title',
        'Firstname',
        'Surname',
        'Email',
        'Gender',
        'Campus',
        'Emergency Contact Name 1',
        'Emergency Contact Phone 1',
        'Emergency Contact Phone Alt 1',
        'Emergency Contact Name 2',
        'Emergency Contact Phone 2',
        'Emergency Contact Phone Alt 2',
        'Emergency Contact Name 3',
        'Emergency Contact Phone 3',
        'Emergency Contact Phone Alt 3',
        'Mobile',
        'Date of Birth',
        'House',
        'Head of House ID',
        'Head of House Name',
        'Form',
        'Form Teacher ID',
        'Form Teacher Name',
        'Tutor Group',
        'Tutor Teacher ID',
        'Tutor Teacher Name',
        'Year Level',
        'Year Level Coordinator ID',
        'Year Level Coordinator Name',
        'Studies Code',
        'Medical Alert',
        'Comment',
        'Contact Alert',
        'Legal Alert',
        'Enrolment Date',
        'Leaving Date',
        'IB',
        'ILP',
        'ESL',
        'Special Needs',
        'Boarding Status'
    ];


    /**
     * Columns in a Staff csv
     */
    private const IMPORT_COLUMNS_STAFF = [
        'External ID',
        'Type',
        'Username',
        'Title',
        'Firstname',
        'Surname',
        'Email',
        'Work Phone',
        'Staff Category',
        'Position Title',
        'Department',
        'Building',
        'Room',
        'Campus'
    ];


    /**
     * Columns in a Relation csv
     */
    private const IMPORT_COLUMNS_RELATION = [
        'Student ID',
        'Guardian ID',
        'Type',

    ];


    /**
     * Upload type
     * One of [student, staff, parent, relation]
     *
     * @var string
     */
    private $type;


    /**
     * @var IStorage
     */
    private $file;


    /**
     * @var Profile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var Profile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var Profile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var Profile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var Profile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * @var IConnector
     */
    private $database;


    /**
     * An array of log messages
     *
     * @var array
     */
    private $log = [];


    /**
     * Perform changes
     *
     * @var bool
     */
    private $perform = false;

    /**
     * The log object to which log entries are written
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Processor constructor.
     *
     * @param string     $type
     * @param IStorage   $file
     * @param IConnector $database
     * @param LoggerInterface|null $logger
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, IStorage $file, IConnector $database, LoggerInterface $logger = null)
    {
        if (!\in_array($type, [self::CSV_STUDENT, self::CSV_STAFF, self::CSV_PARENT, self::CSV_RELATION], true)) {
            throw new \InvalidArgumentException('Unknown csv type.');
        }

        $this->baseProfileMapper = new Profile\Mapper();
        $this->parentProfileMapper = new Profile\Parent\Mapper();
        $this->studentProfileMapper = new Profile\Student\Mapper();
        $this->staffProfileMapper = new Profile\Staff\Mapper();
        $this->relationMapper = new Profile\Relation\Mapper();
        $this->database = $database;
        $this->logger = $logger;

        $this->type = $type;
        $this->file = $file;
    }


    /**
     * Execute changes
     *
     * @param bool $perform
     * @return $this
     */
    public function setPerform(bool $perform): self
    {
        $this->perform = $perform;
        return $this;
    }


    /**
     * Process the supplied file
     *
     * Override this class for different data sources when needed
     *
     * @throws \RuntimeException
     * @throws Exception
     * @throws DefinitionException
     */
    public function process(): Processor
    {
        try {
            $this->log("Starting processing {$this->file->getBasename()}", self::LOG_OK);

            $n = $this->processOne(new Reader($this->file->readStream(), true));

            $this->log("Processing {$this->file->getBasename()} containing {$n} rows complete", self::LOG_OK);
        } catch (\InvalidArgumentException $e) {
            $this->log($e->getMessage(), self::LOG_ERROR);
        }

        return $this;
    }


    /**
     * Process the contents of a supplied file
     *
     * @param Reader $reader
     * @return int
     * @throws \Alaress\Database\Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     * @throws DefinitionException
     */
    private function processOne(Reader $reader): int
    {
        if ($this->isStudentProfile()) {

            /* Process the Student Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_STUDENT);

            return $this->processStudentProfile($reader);
        } elseif ($this->isStaffProfile()) {

            /* Process the Staff Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_STAFF);

            return $this->processStaffProfile($reader);
        } elseif ($this->isParentProfile()) {

            /* Process the Parent Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_PARENT);

            return $this->processParentProfile($reader);
        } elseif ($this->isRelation()) {

            /* Process the Relations csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_RELATION);

            return $this->processRelation($reader);
        }

        throw new \InvalidArgumentException('Unrecognised csv containing header row ' . implode(', ', $reader->readHeaderRow()));
    }


    /**
     * The type that was chosen in the dropdown declares this to be a Student csv
     *
     * @return bool
     */
    private function isStudentProfile(): bool
    {
        return $this->type === self::CSV_STUDENT;
    }


    /**
     * Process student profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     * @throws DefinitionException
     * @throws \Alaress\Database\Exception
     */
    private function processStudentProfile(Reader $reader): int
    {
        /* This many rows were affected
         */
        $affectedProfileIds = [];

        /* Row counter
         */
        $i = 1;

        foreach ($reader as $row) {
            $i++;

            /* External ID is required
             */
            if ('' === trim($row['External ID'])) {
                $this->log("External ID not specified in row {$i}", self::LOG_SKIP);
                continue;
            }

            /* Every row comes with a type; use this as extra surety
             * If it doesn't match, log it and continue
             */
            if (strcasecmp($row['Type'], self::CSV_STUDENT)) {
                $this->log("Profile for {$row['Firstname']} {$row['Surname']}: this is a {$this->type} csv file, but row {$i}  is for {$row['Type']}", self::LOG_SKIP);
                continue;
            }

            $create = true;

            /* Try to find a baseProfile that matches the externalId
             */
            try {
                $studentProfile = $this->studentProfileMapper->findByExternalId($row['External ID']);
                $baseProfile = $studentProfile->getBaseProfile();

                $create = false;
            } catch (Exception $e) {
                $studentProfile = $this->studentProfileMapper->createModel();
                $baseProfile = $this->baseProfileMapper->createModel();
            }

            $baseProfile
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $studentProfile
                ->setDateOfBirth(\Alaress_Date_Format::now())
                ->setUsername($row['Username'])
                ->setEmail($row['Email'])
                ->setGender($row['Gender'])
                ->setCampusName($row['Campus'])
                ->setEmergencyContactName1($row['Emergency Contact Name 1'])
                ->setEmergencyContactPhone1($row['Emergency Contact Phone 1'])
                ->setEmergencyContactPhoneAlt1($row['Emergency Contact Phone Alt 1'])
                ->setEmergencyContactName2($row['Emergency Contact Name 2'])
                ->setEmergencyContactPhone2($row['Emergency Contact Phone 2'])
                ->setEmergencyContactPhoneAlt2($row['Emergency Contact Phone Alt 2'])
                ->setEmergencyContactName3($row['Emergency Contact Name 3'])
                ->setEmergencyContactPhone3($row['Emergency Contact Phone 3'])
                ->setEmergencyContactPhoneAlt3($row['Emergency Contact Phone Alt 3'])
                ->setHeadOfHouseId($row['Head of House ID'])
                ->setHeadOfHouseName($row['Head of House Name'])
                ->setHouseName($row['House'])
                ->setFormName($row['Form'])
                ->setFormTeacherId($row['Form Teacher ID'])
                ->setFormTeacherName($row['Form Teacher Name'])
                ->setTutorGroup($row['Tutor Group'])
                ->setTutorTeacherId($row['Tutor Teacher ID'])
                ->setTutorTeacherName($row['Tutor Teacher Name'])
                ->setYearLevel($row['Year Level'])
                ->setYearLevelCoordinatorId($row['Year Level Coordinator ID'])
                ->setYearLevelCoordinatorName($row['Year Level Coordinator Name'])
                ->setMobile($row['Mobile'])
                ->setStudiesCode($row['Studies Code'])
                ->setAlertMedical($row['Medical Alert'])
                ->setAlertComment($row['Comment'])
                ->setAlertMessage($row['Contact Alert'])
                ->setAlertLegal($row['Legal Alert'])
                ->setEnrolmentDate($row['Enrolment Date'] ? \Alaress_Date_Format::newFromTimestamp(strtotime($row['Enrolment Date'])) : null)
                ->setLeavingDate($row['Leaving Date'] ? \Alaress_Date_Format::newFromTimestamp(strtotime($row['Leaving Date'])) : null)
                ->setIb((bool)$row['IB'])
                ->setIndividualLearningPlans((bool)$row['ILP'])
                ->setEsl((bool)$row['ESL'])
                ->setSpecialNeeds((bool)$row['Special Needs'])
                ->setBoardingStatus((bool)$row['Boarding Status'])
                ->setBaseProfile($baseProfile);

            if ($create) {
                $this->log("Creating new student profile for {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            } else {
                $this->log("Updating existing student profile for {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            }

            if ($this->perform) {
                $this->baseProfileMapper->save($baseProfile);
                $this->studentProfileMapper->save($studentProfile);
            }

            /* Remember the ids that were affected; we remove everyone that wasn't added/updated
             * Do this outside $this->perform for Dry Run logging purposes
             */
            $affectedProfileIds[] = $baseProfile->getId();
        }

        /* Delete everyone we didn't created/update
         */
        $this->log("Removing profiles that weren't created/updated, and their relations too", self::LOG_OK);

        if ($this->perform && \count($affectedProfileIds)) {
            $this->removeUnaffectedProfiles($affectedProfileIds, $this->studentProfileMapper);
        }

        return \count($affectedProfileIds);
    }


    /**
     * Removes unaffected profiles in $mapper's table
     *
     * Unaffected profiles are records that weren't created/updated
     *
     * @param array             $affectedProfileIds
     * @param \Schoolbox\Mapper $mapper
     * @throws \Alaress\Database\Exception
     */
    private function removeUnaffectedProfiles(array $affectedProfileIds, \Schoolbox\Mapper $mapper): void
    {
        $ids = implode(',', $affectedProfileIds);

        /* Find student profiles that weren't created/updated
         */
        $statement = $this->database->executeQuery("
            SELECT GROUP_CONCAT(sis_user_profile_id) AS deletions
            FROM {$mapper->getTable()} 
            WHERE sis_user_profile_id NOT IN ({$ids}) 
        ");

        /* Remove those from both tables
         */
        $deletions = $statement->fetch(\PDO::FETCH_ASSOC)['deletions'];

        if ($deletions) {

            /* Remove relations that they once held
             */
            $this->database->executeQuery("
                DELETE FROM {$this->relationMapper->getTable()}
                WHERE child_profile_id IN ({$deletions})
                OR user_profile_id IN ({$deletions})
            ");

            /* Remove their student profile
             */
            $this->database->executeQuery("
                DELETE FROM {$mapper->getTable()}
                WHERE sis_user_profile_id IN ({$deletions})
            ");
        }

        /* Remove their base profile
         * ...but only if it isn't connected to any other profiles
         */
        $orphans = $this->removeOrphanedProfiles();

        $this->log("Removed {$orphans} orphaned profiles and their associated relations", self::LOG_OK);
    }


    /**
     * Remove any base profiles that are no longer connected to an extension profile
     * student, staff or parent
     *
     * @return int
     */
    private function removeOrphanedProfiles(): int
    {
        return (new OrphanRemover(
            $this->baseProfileMapper,
            $this->studentProfileMapper,
            $this->parentProfileMapper,
            $this->staffProfileMapper,
            $this->relationMapper
        ))->remove();
    }


    /**
     * The type that was chosen in the dropdown declares this to be a Staff csv
     *
     * @return bool
     */
    private function isStaffProfile(): bool
    {
        return $this->type === self::CSV_STAFF;
    }


    /**
     * Process staff profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     * @throws DefinitionException
     * @throws \Alaress\Database\Exception
     */
    private function processStaffProfile(Reader $reader): int
    {
        /* This many rows were affected
         */
        $affectedProfileIds = [];

        /* Row counter
         */
        $i = 1;

        foreach ($reader as $row) {
            $i++;

            /* External ID is required
             */
            if ('' === trim($row['External ID'])) {
                $this->log("External ID not specified in row {$i}", self::LOG_SKIP);
                continue;
            }

            /* Every row comes with a type; use this as extra surety
             * If it doesn't match, log it and continue
             */
            if (strcasecmp($row['Type'], self::CSV_STAFF)) {
                $this->log("Profile for {$row['Firstname']} {$row['Surname']}: this is a {$this->type} csv file, but row {$i}  is for {$row['Type']}", self::LOG_SKIP);
                continue;
            }

            $create = true;

            /* Try to find a baseProfile that matches the externalId
             */
            try {
                $staffProfile = $this->staffProfileMapper->findByExternalId($row['External ID']);
                $baseProfile = $staffProfile->getBaseProfile();

                $create = false;
            } catch (Exception $e) {
                $staffProfile = $this->staffProfileMapper->createModel();
                $baseProfile = $this->baseProfileMapper->createModel();
            }

            $baseProfile
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $staffProfile
                ->setUsername($row['Username'])
                ->setEmail($row['Email'])
                ->setCategory($row['Staff Category'])
                ->setPositionTitle($row['Position Title'])
                ->setDepartment($row['Department'])
                ->setPhone($row['Work Phone'])
                ->setRoom($row['Room'])
                ->setBuilding($row['Building'])
                ->setCampusName($row['Campus'])
                ->setBaseProfile($baseProfile);

            if ($create) {
                $this->log("Creating new staff profile for {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            } else {
                $this->log("Updating existing staff profile {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            }

            if ($this->perform) {
                $this->baseProfileMapper->save($baseProfile);
                $this->staffProfileMapper->save($staffProfile);
            }

            /* Remember the ids that were affected; we remove everyone that wasn't added/updated
             * Do this outside $this->perform for Dry Run logging purposes
             */
            $affectedProfileIds[] = $baseProfile->getId();
        }

        /* Delete everyone we didn't created/update
         */
        $this->log("Removing profiles that weren't created/updated, and their relations too", self::LOG_OK);

        if ($this->perform && \count($affectedProfileIds)) {
            $this->removeUnaffectedProfiles($affectedProfileIds, $this->staffProfileMapper);
        }

        return \count($affectedProfileIds);
    }


    /**
     * The type that was chosen in the dropdown declares this to be a Parent csv
     *
     * @return bool
     */
    private function isParentProfile(): bool
    {
        return $this->type === self::CSV_PARENT;
    }


    /**
     * Process parent profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     * @throws DefinitionException
     * @throws \Alaress\Database\Exception
     */
    private function processParentProfile(Reader $reader): int
    {
        /* This many rows were affected
         */
        $affectedProfileIds = [];

        /* Row counter
         */
        $i = 1;

        foreach ($reader as $row) {
            $i++;

            /* External ID is required
             */
            if ('' === trim($row['External ID'])) {
                $this->log("External ID not specified in row {$i}", self::LOG_SKIP);
                continue;
            }

            /* Every row comes with a type; use this as extra surety
             * If it doesn't match, log it and continue
             */
            if (strcasecmp($row['Type'], self::CSV_PARENT)) {
                $this->log("Profile for {$row['Firstname']} {$row['Surname']}: this is a {$this->type} csv file, but row {$i} is for {$row['Type']}", self::LOG_SKIP);
                continue;
            }

            $create = true;

            /* Try to find a baseProfile that matches the externalId
             */
            try {
                $parentProfile = $this->parentProfileMapper->findByExternalId($row['External ID']);
                $baseProfile = $parentProfile->getBaseProfile();

                $create = false;
            } catch (Exception $e) {
                $parentProfile = $this->parentProfileMapper->createModel();
                $baseProfile = $this->baseProfileMapper->createModel();
            }

            $baseProfile
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $parentProfile
                ->setMobile($row['Mobile'])
                ->setPhoneHome($row['Home Phone'])
                ->setPhoneWork($row['Work Phone'])
                ->setUsername($row['Username'])
                ->setEmail($row['Email'])
                ->setBaseProfile($baseProfile);

            if ($create) {
                $this->log("Creating new parent profile for {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            } else {
                $this->log("Updating existing parent profile for {$row['Firstname']} {$row['Surname']}", self::LOG_OK);
            }

            if ($this->perform) {
                $this->baseProfileMapper->save($baseProfile);
                $this->parentProfileMapper->save($parentProfile);
            }

            /* Remember the ids that were affected; we remove everyone that wasn't added/updated
             * Do this outside $this->perform for Dry Run logging purposes
             */
            $affectedProfileIds[] = $baseProfile->getId();
        }

        /* Delete everyone we didn't created/update
         */
        $this->log("Removing profiles that weren't created/updated, and their relations too", self::LOG_OK);

        if ($this->perform && \count($affectedProfileIds)) {
            $this->removeUnaffectedProfiles($affectedProfileIds, $this->parentProfileMapper);
        }

        return \count($affectedProfileIds);
    }


    /**
     * The type that was chosen in the dropdown declares this to be a Relation csv
     *
     * @return bool
     */
    private function isRelation(): bool
    {
        return $this->type === self::CSV_RELATION;
    }


    /**
     * Process the Relations csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \Alaress\Database\Exception
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function processRelation(Reader $reader): int
    {
        /* Remember a set of (profile_id, external_id) tuples to save on db pounding
         * while trying to derive relations between external_ids from csv
         *
         * Our schema creates relations between profile_ids, not external_ids
         * so we might as well cache these to save on performance penalty
         */
        $statement = $this->database->prepareQuery("
            SELECT id, external_id AS externalId, firstname, lastname, \"" . Role::PARENT . "\" AS roleType
            FROM {$this->baseProfileMapper->getTable()} profile
            INNER JOIN sis_user_profile_parent parent ON profile.id = parent.sis_user_profile_id

            UNION

            SELECT id, external_id AS externalId, firstname, lastname, \"" . Role::STUDENT . "\" AS roleType
            FROM {$this->baseProfileMapper->getTable()} profile
            INNER JOIN sis_user_profile_student student ON profile.id = student.sis_user_profile_id


            ORDER BY id
        ");

        $statement->execute();

        /* Set hash lookup of (externalId, roleType) => sis_user_profile.id
         * so we can quickly identify profile ids from their external ids
         */
        $cache = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $roleType = $row['roleType'];
            $externalId = $row['externalId'];
            $profileId = (int)$row['id'];

            $cache[$roleType][$externalId] = $profileId;
        }

        /* Purge old relations before re-inserting them
         */
        if ($this->perform) {
            $this->relationMapper->truncate();
        }

        $this->log('Removing old relations', self::LOG_OK);

        /* Process each relation
         */
        $n = 0;

        foreach ($reader as $row) {

            /* Check that both relations exist. They might not exist because the
             * relation csv may contain data that wasn't in the other three files
             * (probably from human error but who knows)
             */
            if (!$cache[Role::PARENT ][$row['Guardian ID']]) {
                $this->log("Relation between guardian {$row['Guardian ID']} and student {$row['Student ID']}: guardian {$row['Guardian ID']} doesn't exist", self::LOG_SKIP);

                continue;
            }

            if (!$cache[Role::STUDENT ][$row['Student ID']]) {
                $this->log("Relation between guardian {$row['Guardian ID']} and student {$row['Student ID']}: student {$row['Student ID']} doesn't exist", self::LOG_SKIP);

                continue;
            }

            /* Otherwise both exist; build relation
             */
            $relation = $this->relationMapper->createModel()
                ->setUserProfileId($cache[Role::PARENT ][$row['Guardian ID']])
                ->setType($row['Type'])
                ->setChildProfileId($cache[Role::STUDENT ][$row['Student ID']]);

            if ($this->perform) {
                $this->relationMapper->save($relation);
            }

            $this->log("Created relation called \"{$row['Type']}\" between guardian {$row['Guardian ID']} and student {$row['Student ID']}", self::LOG_OK);

            /* These two humans have relations
             */
            $n++;
        }

        return $n;
    }


    /**
     * Log four pieces of data
     *
     * @param string $message
     * @param string $status
     */
    private function log(string $message, string $status): void
    {
        $this->log[] = [
            'timestamp' => \Alaress_Date_Format::now()->getTimeOnly(),
            'message' => $message,
            'status' => $status
        ];
        if ($status !== self::LOG_OK && $this->logger !== null) {
            $this->logger->warning($message);
        }
    }


    /**
     * Get log for what happened during this import
     *
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
