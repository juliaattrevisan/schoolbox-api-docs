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
 * Class Importer
 *
 * Determine which Profile Import files were passed, based on header contents,
 * and process them iteratively
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Importer
{

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
     * Upload type
     * One of [student, staff, parent, relationship]
     *
     * @var string
     */
    private $type;


    /**
     * @var IStorage
     */
    private $file;


    /**
     * @var Mapping
     */
    private $mapping;


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
     * @var Profile\Relationship\Mapper
     */
    private $relationshipMapper;


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
     * Importer constructor.
     *
     * @param string                      $type
     * @param IStorage                    $file
     * @param IConnector                  $database
     * @param Profile\Mapper              $baseProfileMapper
     * @param Profile\Parent\Mapper       $parentProfileMapper
     * @param Profile\Student\Mapper      $studentProfileMapper
     * @param Profile\Staff\Mapper        $staffProfileMapper
     * @param Profile\Relationship\Mapper $relationshipMapper
     * @param LoggerInterface|null        $logger
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $type,
        IStorage $file,
        IConnector $database,
        Profile\Mapper $baseProfileMapper,
        Profile\Parent\Mapper $parentProfileMapper,
        Profile\Student\Mapper $studentProfileMapper,
        Profile\Staff\Mapper $staffProfileMapper,
        Profile\Relationship\Mapper $relationshipMapper,
        LoggerInterface $logger = null
    ) {
        $this->mapping = new Mapping($type);

        $this->baseProfileMapper = $baseProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

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
    public function process(): Importer
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
        if ($this->mapping->isStudentProfile($this->type)) {

            /* Process the Student Profile csv
             */
            $reader->setExpectedColumns(array_keys(Mapping::IMPORT_COLUMNS_STUDENT));

            return $this->processStudentProfile($reader);
        } elseif ($this->mapping->isStaffProfile($this->type)) {

            /* Process the Staff Profile csv
             */
            $reader->setExpectedColumns(array_keys(Mapping::IMPORT_COLUMNS_STAFF));

            return $this->processStaffProfile($reader);
        } elseif ($this->mapping->isParentProfile($this->type)) {

            /* Process the Parent Profile csv
             */
            $reader->setExpectedColumns(array_keys(Mapping::IMPORT_COLUMNS_PARENT));

            return $this->processParentProfile($reader);
        } elseif ($this->mapping->isRelationship($this->type)) {

            /* Process the Relationships csv
             */
            $reader->setExpectedColumns(array_keys(Mapping::IMPORT_COLUMNS_RELATIONSHIP));

            return $this->processRelationship($reader);
        }

        throw new \InvalidArgumentException('Unrecognised csv containing header row ' . implode(', ', $reader->readHeaderRow()));
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
            if (strcasecmp($row['Type'], Mapping::CSV_STUDENT)) {
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

            /* Date of birth - strtotime doesn't understand empty cells and returns false
             * ADF can't make a new timestamp from a bool
             */
            $dob = null;

            /* TODO: This is throwing a lot of Laravel Trailing Data warnings
             *  but I think the answer is going to be in alaress date format
             */
            if ($row['Date of Birth']) {
                $dob = new \Alaress_Date_Format($row['Date of Birth'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);
            }

            $studentProfile
                ->setDateOfBirth($dob)
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
        $this->log("Removing profiles that weren't created/updated, and their relationships too", self::LOG_OK);

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
         * Remove those from both tables
         *
         * Note that GROUP_CONCAT has a maximum limit of 1024 so if you compile a lot of ids you can
         * expect some of them to be truncated away from the end of the intended string
         */
        $deletionsStatement = $this->database->executeQuery("
            SELECT sis_user_profile_id
            FROM {$mapper->getTable()}
            WHERE sis_user_profile_id NOT IN ({$ids})
        ");

        $deletionIds = [];

        while ($row = $deletionsStatement->fetch(\PDO::FETCH_ASSOC)) {
            $deletionIds[] = $row['sis_user_profile_id'];
        }

        $deletions = implode(',', $deletionIds);

        if ('' !== $deletions) {

            /* Remove relationships that they once held
             */
            $this->database->executeQuery("
                DELETE FROM {$this->relationshipMapper->getTable()}
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

        $this->log("Removed {$orphans} orphaned profiles and their associated relationships", self::LOG_OK);
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
            $this->relationshipMapper
        ))->remove();
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
            if (strcasecmp($row['Type'], Mapping::CSV_STAFF)) {
                $this->log("Profile for {$row['Firstname']} {$row['Surname']}: this is a {$this->type} csv file, but row {$i}  is for {$row['Type']}", self::LOG_SKIP);
                continue;
            }

            $create = true;

            /* Try to find an existing staffProfile that matches the externalId
             * If one already exists, use it; otherwise spawn a new one
             */
            try {
                $staffProfile = $this->staffProfileMapper->findByExternalId($row['External ID']);

                $create = false;
            } catch (Exception $e) {
                $staffProfile = $this->staffProfileMapper->createModel();
            }

            /* Try to find a baseProfile that matches the externalId
             * If one already exists, use it; otherwise spawn a new one
             */
            try {
                $baseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $row['External ID']);
            } catch (Exception $e) {
                $baseProfile = $this->baseProfileMapper->createModel();
            }

            /* Note that baseProfiles can be shared among multiple role types
             * A baseProfile might exist already, but for a different role type
             * Therefore you must check these separately and assemble accordingly
             */
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
        $this->log("Removing profiles that weren't created/updated, and their relationships too", self::LOG_OK);

        if ($this->perform && \count($affectedProfileIds)) {
            $this->removeUnaffectedProfiles($affectedProfileIds, $this->staffProfileMapper);
        }

        return \count($affectedProfileIds);
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
            if (strcasecmp($row['Type'], Mapping::CSV_PARENT)) {
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
        $this->log("Removing profiles that weren't created/updated, and their relationships too", self::LOG_OK);

        if ($this->perform && \count($affectedProfileIds)) {
            $this->removeUnaffectedProfiles($affectedProfileIds, $this->parentProfileMapper);
        }

        return \count($affectedProfileIds);
    }


    /**
     * Process the Relationships csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \Alaress\Database\Exception
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function processRelationship(Reader $reader): int
    {
        /* Remember a set of (profile_id, external_id) tuples to save on db pounding
         * while trying to derive relationships between external_ids from csv
         *
         * Our schema creates relationships between profile_ids, not external_ids
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

        /* Purge old relationships before re-inserting them
         */
        if ($this->perform) {
            $this->relationshipMapper->truncate();
        }

        $this->log('Removing old relationships', self::LOG_OK);

        /* Process each relationship
         */
        $n = 0;

        foreach ($reader as $row) {

            /* Check that both relationships exist. They might not exist because the
             * relationship csv may contain data that wasn't in the other three files
             * (probably from human error but who knows)
             */
            if (!$cache[Role::PARENT ][$row['Guardian ID']]) {
                $this->log("Relationship between guardian {$row['Guardian ID']} and student {$row['Student ID']}: guardian {$row['Guardian ID']} doesn't exist", self::LOG_SKIP);

                continue;
            }

            if (!$cache[Role::STUDENT ][$row['Student ID']]) {
                $this->log("Relationship between guardian {$row['Guardian ID']} and student {$row['Student ID']}: student {$row['Student ID']} doesn't exist", self::LOG_SKIP);

                continue;
            }

            /* Otherwise both exist; build relationship
             */
            $userProfile = $this->parentProfileMapper->findById($cache[Role::PARENT ][$row['Guardian ID']])->getBaseProfile();
            $childProfile = $this->studentProfileMapper->findById($cache[Role::STUDENT ][$row['Student ID']])->getBaseProfile();

            $relationship = $this->relationshipMapper->createModel()
                ->setUserProfile($userProfile)
                ->setType($row['Type'])
                ->setChildProfile($childProfile);

            if ($this->perform) {
                $this->relationshipMapper->save($relationship);
            }

            $this->log("Created relationship called \"{$row['Type']}\" between guardian {$row['Guardian ID']} and student {$row['Student ID']}", self::LOG_OK);

            /* Keep a count of successfully-constructed relationships
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
