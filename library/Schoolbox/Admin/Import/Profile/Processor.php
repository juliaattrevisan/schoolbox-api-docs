<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Profile;
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
     * Status string for successful row processing
     */
    private const LOG_OK = 'OK';


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
        'Gender',
        'Email',
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
        'Role Type',
        'Relation ID',
        'Student ID',
        'Type',
        'Emergency Contact',
    ];


    /**
     * @var array
     */
    private $files;


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
     * Processor constructor.
     *
     * @param array      $files
     * @param IConnector $database
     * @throws \RuntimeException
     */
    public function __construct(array $files, IConnector $database)
    {
        $this->files = $this->presortFiles($files);

        $this->baseProfileMapper = new Profile\Mapper();
        $this->parentProfileMapper = new Profile\Parent\Mapper();
        $this->studentProfileMapper = new Profile\Student\Mapper();
        $this->staffProfileMapper = new Profile\Staff\Mapper();
        $this->relationMapper = new Profile\Relation\Mapper();

        $this->database = $database;
    }


    /**
     * Sort the files so that the relation one comes last
     *
     * @param array $files
     * @return array
     * @throws \RuntimeException
     */
    private function presortFiles(array $files): array
    {
        $profileFiles = [];
        $relationFiles = [];

        foreach($files as $file) {
            $reader = new Reader($file->readStream(), true);

            /* Load the first row
             */
            $reader->next();

            /* Get the columns out and from here, work out what kind of file it is
             */
            $header = $reader->current();

            if ($this->isRelation($header)) {
                $relationFiles[] = $file;
            } else {
                $profileFiles[] = $file;
            }
        }

        /* Process all of these files in the required order
         */
        return array_merge($profileFiles, $relationFiles);
    }


    /**
     * Process each of the supplied files in a specific order
     * Relationship csvs must come last
     *
     * Override this class for different data sources when needed
     *
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    public function process(): Processor
    {
        foreach ($this->files as $file) {
            try {
                $this->log("Starting processing {$file->getBasename()}", '', self::LOG_OK);

                $n = $this->processOne($file->readStream());

                $this->log("Processing {$file->getBasename()} complete", '', "{$n} rows");
            } catch (\InvalidArgumentException $e) {

                $this->log($e->getMessage(), '', self::LOG_ERROR);
                $this->log[] = $e->getMessage();
            }
        }

        return $this;
    }


    /**
     * Process the contents of a supplied file
     *
     * @param resource $contents
     *
     * @return int
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    private function processOne($contents): int
    {
        $reader = new Reader($contents, true);

        /* Load the first row
         */
        $reader->next();

        /* Get the columns out and from here, work out what kind of file it is
         */
        $header = $reader->current();

        if ($this->isRelation($header)) {

            /* Process the Relations csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_RELATION);

            return $this->processRelation($reader);
        } elseif ($this->isStudentProfile($header)) {

            /* Process the Student Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_STUDENT);

            return $this->processStudentProfile($reader);
        } elseif ($this->isStaffProfile($header)) {

            /* Process the Staff Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_STAFF);

            return $this->processStaffProfile($reader);
        } elseif ($this->isParentProfile($header)) {

            /* Process the Parent Profile csv
             */
            $reader->setExpectedColumns(self::IMPORT_COLUMNS_PARENT);

            return $this->processParentProfile($reader);
        }

        throw new \InvalidArgumentException('Unknown header contents' . implode(', ', $header));
    }


    /**
     * $row is the same as the expected columns of a Relations csv
     *
     * @param array $row
     * @return bool
     */
    private function isRelation(array $row): bool
    {
        return $this->matches($row, self::IMPORT_COLUMNS_RELATION);
    }


    /**
     * Process the Relations csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \Alaress\Database\Exception
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
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
        $statement = $this->database->prepareQuery('
            SELECT id, external_id AS externalId, firstname, lastname, "' . Role::PARENT .'" AS roleType 
            FROM schoolbox.sis_user_profile profile
            INNER JOIN sis_user_profile_parent parent ON profile.id = parent.sis_user_profile_id
            
            UNION
            
            SELECT id, external_id AS externalId, firstname, lastname, "' . Role::STUDENT .'" AS roleType 
            FROM schoolbox.sis_user_profile profile
            INNER JOIN sis_user_profile_student student ON profile.id = student.sis_user_profile_id
            
            UNION
            
            SELECT id, external_id AS externalId, firstname, lastname, "' . Role::STAFF .'" AS roleType
            FROM schoolbox.sis_user_profile profile
            INNER JOIN sis_user_profile_staff staff ON profile.id = staff.sis_user_profile_id
            
            ORDER BY id
        ');

        $statement->execute();

        /* Set hash lookup of (external_id, type) => sis_user_profile.id
         * so we can quickly identify profile ids from their external ids
         */
        $cache = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $roleType = $row['roleType'];
            $externalId = $row['externalId'];
            $profileId = (int)$row['id'];

            $cache[$roleType][$externalId] = $profileId;
        }

        /* Delete previous relations
         * Without a primary key you just can't tell where one set ends and where another begins
         */
        $this->database->executeQuery('
            DELETE FROM sis_user_profile_relation
        ');

        /* Process each relation
         */
        $n = 0;

        foreach ($reader as $row) {

            /* Check that both relations exist. They might not exist because the
             * relation csv may contain data that wasn't in the other three files
             * (probably from human error but who knows)
             */
            if (!$cache[$row['Role Type']][$row['Relation ID']]) {
                $this->log("Relation between {$row['Role Type']} {$row['Relation ID']} and " . Role::STUDENT . " {$row['Student ID']}", '', "Skipped: Relation ID {$row['Relation ID']} doesn't exist");

                continue;
            }

            if (!$cache[Role::STUDENT][$row['Student ID']]) {
                $this->log("Relation between {$row['Role Type']} {$row['Relation ID']} and " . Role::STUDENT . " {$row['Student ID']}", '', "Skipped: Student ID {$row['Student ID']} doesn't exist");

                continue;
            }

            /* Otherwise both exist; build relation
             */
            $relation = $this->relationMapper->createModel()
                ->setChildProfileId($cache[Role::STUDENT][$row['Student ID']])
                ->setUserProfileId($cache[$row['Role Type']][$row['Relation ID']])
                ->setType($row['Type'])
                ->setEmergencyContact((bool)$row['Emergency Contact']);

            $this->relationMapper->save($relation);

            $this->log("Created relation between {$row['Role Type']} {$row['Relation ID']} and " . Role::STUDENT . " {$row['Student ID']}", '', self::LOG_OK);

            /* These two humans have relations
             */
            $n++;
        }

        return $n;
    }


    /**
     * $row is the same as the expected columns of a Student Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isStudentProfile(array $row): bool
    {
        return $this->matches($row, self::IMPORT_COLUMNS_STUDENT);
    }


    /**
     * Delete everything in $previousIds
     *
     * @param string $table
     * @param array $newIds
     * @throws \Alaress\Database\Exception
     */
    private function removePreviousProfileIds(string $table, array $newIds): void
    {
        $ids = implode(', ', $newIds);

        if (null !== $ids) {

            /* Find the ids outside of $newIds
             *
             * These specific ids need to be removed from the collective set in sis_user_profile
             * which may contain a whole bunch of other ids that should remain valid
             *
             * Remove these specific ids from $table
             */
            $previousIds = $this->database->executeQuery("
                SELECT GROUP_CONCAT(sis_user_profile_id) AS previousIds 
                FROM {$table}
                WHERE sis_user_profile_id NOT IN ({$ids})
            ")->fetch()['previousIds'];

            /* If there were previous ids, remove 'em
             */
            if (null !== $previousIds) {

                /* Remove from extension tables
                 */
                $this->database->executeQuery("
                    DELETE FROM {$table}
                    WHERE sis_user_profile_id IN ({$previousIds});
                ");

                /* Remove from relation table
                 */
                $this->database->executeQuery("
                    DELETE FROM sis_user_profile_relation
                    WHERE child_profile_id IN ({$previousIds})
                    OR user_profile_id IN ({$previousIds});
                ");

                /* Remove from base profile table
                 */
                $this->database->executeQuery("
                    DELETE FROM sis_user_profile
                    WHERE id IN ({$previousIds});
                ");
            }
        }
    }


    /**
     * Process student profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    private function processStudentProfile(Reader $reader): int
    {
        /* Find the previously inserted ids; these need to be removed from both
         * this table, the relationship table, and the sis_user_profile table
         *
         * Each file can be uploaded independently of the others which means we can't
         * make very many assumptions/presumptions about the db's contents
         */
        $newIds = [];

        foreach ($reader as $row) {
            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $studentProfile = $this->studentProfileMapper->createModel()
                ->setDateOfBirth(\Alaress_Date_Format::now())
                ->setUsername($row['Username'])
                ->setEmail($row['Email'])
                ->setGender($row['Gender'])
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

            $this->baseProfileMapper->save($baseProfile);
            $this->studentProfileMapper->save($studentProfile);

            $this->log('Created student profile', "{$row['Firstname']} {$row['Surname']}", self::LOG_OK);

            $newIds[] = $baseProfile->getId();
        }

        $this->removePreviousProfileIds($this->studentProfileMapper->getTable(), $newIds);

        return \count($newIds);
    }


    /**
     * $row is the same as the expected columns of a Staff Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isStaffProfile(array $row): bool
    {
        return $this->matches($row, self::IMPORT_COLUMNS_STAFF);
    }


    /**
     * Process staff profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    private function processStaffProfile(Reader $reader): int
    {
        /* Find the previously inserted ids; these need to be removed from both
         * this table, the relationship table, and the sis_user_profile table
         *
         * Each file can be uploaded independently of the others which means we can't
         * make very many assumptions/presumptions about the db's contents
         */
        $newIds = [];

        foreach ($reader as $row) {
            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $staffProfile = $this->staffProfileMapper->createModel()
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

            $this->baseProfileMapper->save($baseProfile);
            $this->staffProfileMapper->save($staffProfile);

            $this->log('Created staff profile', "{$row['Firstname']} {$row['Surname']}", self::LOG_OK);

            $newIds[] = $baseProfile->getId();
        }

        $this->removePreviousProfileIds($this->staffProfileMapper->getTable(), $newIds);

        return \count($newIds);
    }


    /**
     * $row is the same as the expected columns of a Parent Profile csv
     *
     * @param array $row
     * @return bool
     */
    private function isParentProfile(array $row): bool
    {
        return $this->matches($row, self::IMPORT_COLUMNS_PARENT);
    }


    /**
     * Process parent profile csv
     *
     * @param Reader $reader
     * @return int number of rows processed
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     */
    private function processParentProfile(Reader $reader): int
    {
        /* Find the previously inserted ids; these need to be removed from both
         * this table, the relationship table, and the sis_user_profile table
         *
         * Each file can be uploaded independently of the others which means we can't
         * make very many assumptions/presumptions about the db's contents
         */
        $newIds = [];

        foreach ($reader as $row) {
            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($row['External ID'])
                ->setTitle($row['Title'])
                ->setFirstname($row['Firstname'])
                ->setLastname($row['Surname']);

            $parentProfile = $this->parentProfileMapper->createModel()
                ->setMobile($row['Mobile'])
                ->setPhoneHome($row['Home Phone'])
                ->setPhoneWork($row['Work Phone'])
                ->setUsername($row['Username'])
                ->setEmail($row['Email'])
                ->setBaseProfile($baseProfile);

            $this->baseProfileMapper->save($baseProfile);
            $this->parentProfileMapper->save($parentProfile);

            $this->log('Created parent profile', "{$row['Firstname']} {$row['Surname']}", self::LOG_OK);

            $newIds[] = $baseProfile->getId();
        }

        $this->removePreviousProfileIds($this->parentProfileMapper->getTable(), $newIds);

        return \count($newIds);
    }


    /**
     * The elements of $row match $expected exactly
     *
     * @param array $row
     * @param array $expected
     * @return bool
     */
    private function matches(array $row, array $expected): bool
    {
        return !\count(array_diff($row, $expected));
    }


    /**
     * Log four pieces of data
     *
     * @param string $message
     * @param string $person
     * @param string $status
     */
    private function log(string $message, string $person, string $status)
    {
        $this->log[] = [
            'timestamp' => \Alaress_Date_Format::now()->getTimeOnly(),
            'message' => $message,
            'person' => $person,
            'status' => $status
        ];
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
