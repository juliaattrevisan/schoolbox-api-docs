<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Csv\Reader;
use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Profile;

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
            $reader = new Reader($file->readStream());
            $reader->setLoadFromFirst(false);

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
                $this->log[] = "Starting processing from {$file->getBasename()}";
                $n = $this->processOne($file->readStream());
                $this->log[] = "Processed {$n} rows from {$file->getBasename()}";
            } catch (\InvalidArgumentException $e) {
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
        $reader = new Reader($contents);
        $reader->setLoadFromFirst(false);

        /* Load the first row
         */
        $reader->next();

        /* Get the columns out and from here, work out what kind of file it is
         */
        $header = $reader->current();

        if ($this->isRelation($header)) {

            /* Process the Relations csv
             */
            return $this->processRelation($reader);
        } elseif ($this->isStudentProfile($header)) {

            /* Process the Student Profile csv
             */
            return $this->processStudentProfile($reader);
        } elseif ($this->isStaffProfile($header)) {

            /* Process the Staff Profile csv
             */
            return $this->processStaffProfile($reader);
        } elseif ($this->isParentProfile($header)) {

            /* Process the Parent Profile csv
             */
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
            SELECT id, external_id
            FROM sis_user_profile
            ORDER BY id
        ');

        $statement->execute();

        /* Set hash lookup of external_id => sis_user_profile.id
         * so we can quickly identify profile ids from their external ids
         */
        $cache = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $cache[$row['external_id']] = (int)$row['id'];
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

        foreach ($reader as $i => $row) {
            $data = array_combine(self::IMPORT_COLUMNS_RELATION, $row);

            /* Check that both relations exist. They might not exist because the
             * relation csv may contain data that wasn't in the other three files
             * (probably from human error but who knows)
             */
            if (!$cache[$data['Relation ID']]) {
                $this->log[] = "Skipped relation between {$data['Relation ID']} and {$data['Student ID']}; {$data['Relation ID']} doesn't exist";
                continue;
            }

            if (!$cache[$data['Student ID']]) {
                $this->log[] = "Skipped relation between {$data['Relation ID']} and {$data['Student ID']}; {$data['Student ID']} doesn't exist";
                continue;
            }

            /* Otherwise both exist; build relation
             */
            $relation = $this->relationMapper->createModel()
                ->setChildProfileId($cache[$data['Relation ID']])
                ->setUserProfileId($cache[$data['Student ID']])
                ->setType($data['Type'])
                ->setEmergencyContact((bool)$data['Emergency Contact']);

            $this->relationMapper->save($relation);

            $this->log[] = "Created relation between {$data['Relation ID']} and {$data['Student ID']}";

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

        foreach ($reader as $i => $row) {
            $data = array_combine(self::IMPORT_COLUMNS_STUDENT, $row);

            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($data['External ID'])
                ->setTitle($data['Title'])
                ->setFirstname($data['Firstname'])
                ->setLastname($data['Surname']);

            $studentProfile = $this->studentProfileMapper->createModel()
                ->setDateOfBirth(\Alaress_Date_Format::now())
                ->setUsername($data['Username'])
                ->setEmail($data['Email'])
                ->setGender($data['Gender'])
                ->setHeadOfHouseId($data['Head of House ID'])
                ->setHeadOfHouseName($data['Head of House Name'])
                ->setHouseName($data['House'])
                ->setFormName($data['Form'])
                ->setFormTeacherId($data['Form Teacher ID'])
                ->setFormTeacherName($data['Form Teacher Name'])
                ->setTutorGroup($data['Tutor Group'])
                ->setTutorTeacherId($data['Tutor Teacher ID'])
                ->setTutorTeacherName($data['Tutor Teacher Name'])
                ->setYearLevel($data['Year Level'])
                ->setYearLevelCoordinatorId($data['Year Level Coordinator ID'])
                ->setYearLevelCoordinatorName($data['Year Level Coordinator Name'])
                ->setMobile($data['Mobile'])
                ->setStudiesCode($data['Studies Code'])
                ->setAlertMedical($data['Medical Alert'])
                ->setAlertComment($data['Comment'])
                ->setAlertMessage($data['Contact Alert'])
                ->setAlertLegal($data['Legal Alert'])
                ->setEnrolmentDate($data['Enrolment Date'] ? \Alaress_Date_Format::newFromTimestamp(strtotime($data['Enrolment Date'])) : null)
                ->setLeavingDate($data['Leaving Date'] ? \Alaress_Date_Format::newFromTimestamp(strtotime($data['Leaving Date'])) : null)
                ->setIb((bool)$data['IB'])
                ->setIndividualLearningPlans((bool)$data['ILP'])
                ->setEsl((bool)$data['ESL'])
                ->setSpecialNeeds((bool)$data['Special Needs'])
                ->setBoardingStatus((bool)$data['Boarding Status'])
                ->setBaseProfile($baseProfile);

            $this->baseProfileMapper->save($baseProfile);
            $this->studentProfileMapper->save($studentProfile);

            $this->log[] = "Created student profile for {$data['Firstname']} {$data['Surname']}";

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

        foreach ($reader as $i => $row) {
            $data = array_combine(self::IMPORT_COLUMNS_STAFF, $row);

            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($data['External ID'])
                ->setTitle($data['Title'])
                ->setFirstname($data['Firstname'])
                ->setLastname($data['Surname']);

            $staffProfile = $this->staffProfileMapper->createModel()
                ->setUsername($data['Username'])
                ->setEmail($data['Email'])
                ->setCategory($data['Staff Category'])
                ->setPositionTitle($data['Position Title'])
                ->setDepartment($data['Department'])
                ->setPhone($data['Work Phone'])
                ->setRoom($data['Room'])
                ->setBuilding($data['Building'])
                ->setCampusName($data['Campus'])
                ->setBaseProfile($baseProfile);

            $this->baseProfileMapper->save($baseProfile);
            $this->staffProfileMapper->save($staffProfile);

            $this->log[] = "Created staff profile for {$data['Firstname']} {$data['Surname']}";

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

        foreach ($reader as $i => $row) {
            $data = array_combine(self::IMPORT_COLUMNS_PARENT, $row);

            $baseProfile = $this->baseProfileMapper->createModel()
                ->setExternalId($data['External ID'])
                ->setTitle($data['Title'])
                ->setFirstname($data['Firstname'])
                ->setLastname($data['Surname']);

            $parentProfile = $this->parentProfileMapper->createModel()
                ->setMobile($data['Mobile'])
                ->setPhoneHome($data['Home Phone'])
                ->setPhoneWork($data['Work Phone'])
                ->setUsername($data['Username'])
                ->setEmail($data['Email'])
                ->setBaseProfile($baseProfile);

            $this->baseProfileMapper->save($baseProfile);
            $this->parentProfileMapper->save($parentProfile);

            $this->log[] = "Created parent profile for {$data['Firstname']} {$data['Surname']}";

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
     * Get log for what happened during this import
     *
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
