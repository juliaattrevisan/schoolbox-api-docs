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
     * @param array     $files
     * @param IConnector $database
     */
    public function __construct(array $files, IConnector $database)
    {
        $this->files = $files;

        $this->baseProfileMapper = new Profile\Mapper();
        $this->parentProfileMapper = new Profile\Parent\Mapper();
        $this->studentProfileMapper = new Profile\Student\Mapper();
        $this->staffProfileMapper = new Profile\Staff\Mapper();
        $this->relationMapper = new Profile\Relation\Mapper();

        $this->database = $database;
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
        /* Sort the files so that the relation one comes last
         */
        $profileFiles = [];
        $relationFiles = [];

        foreach ($this->files as $file) {
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

        $sortedFiles = array_merge($profileFiles, $relationFiles);

        /* Process all of these files in the required order
         */
        foreach ($sortedFiles as $file) {
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
        /* Find the last inserted id and remember it
         */
        $lastId = $this->database->executeQuery('
            SELECT GREATEST(MAX(child_profile_id), MAX(user_profile_id)) AS lastId
            FROM sis_user_profile_relation
        ')->fetch()['lastId'];

        $n = 0;

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

        /* Process each relation
         */
        foreach ($reader as $i => $row) {
            $data = array_combine(self::IMPORT_COLUMNS_RELATION, $row);

            /* Check that both relations exist. They might not exist because the
             * relation csv may contain data that wasn't in the other three files
             * (probably from human error but who knows)
             */
            if (!$cache[$data['Relation ID']]) {
                continue;
            }

            if (!$cache[$data['Student ID']]) {
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

            $n++;
        }

        /* Delete everything prior to the last inserted id
         */
        $removal = $this->database->prepareQuery('
            DELETE FROM sis_user_profile_relation
            WHERE child_profile_id <= :lastId
            OR user_profile_id <= :lastId
        ');

        $removal->execute([
            'lastId' => $lastId
        ]);

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

        /* Find the last inserted id and remember it
         */
        $lastId = $this->database->executeQuery('
            SELECT MAX(sis_user_profile_id) AS lastId
            FROM sis_user_profile_student
        ')->fetch()['lastId'];

        $n = 0;

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

            $n++;
        }

        /* Delete everything prior to the last inserted id
         */
        $removal = $this->database->prepareQuery('
            DELETE FROM sis_user_profile_student
            WHERE sis_user_profile_id <= :lastId
        ');

        $removal->execute([
            'lastId' => $lastId
        ]);

        return $n;
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
        /* Find the last inserted id and remember it
         */
        $lastId = $this->database->executeQuery('
            SELECT MAX(sis_user_profile_id) AS lastId
            FROM sis_user_profile_staff
        ')->fetch()['lastId'];

        $n = 0;

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

            $n++;
        }

        /* Delete everything prior to the last inserted id
         */
        $removal = $this->database->prepareQuery('
            DELETE FROM sis_user_profile_staff
            WHERE sis_user_profile_id <= :lastId
        ');

        $removal->execute([
            'lastId' => $lastId
        ]);

        return $n;
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

        /* Find the last inserted id and remember it
         */
        $lastId = $this->database->executeQuery('
            SELECT MAX(sis_user_profile_id) AS lastId
            FROM sis_user_profile_parent
        ')->fetch()['lastId'];

        $n = 0;

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

            $n++;
        }

        /* Delete everything prior to the last inserted id
         */
        $removal = $this->database->prepareQuery('
            DELETE FROM sis_user_profile_parent
            WHERE sis_user_profile_id <= :lastId
        ');

        $removal->execute([
            'lastId' => $lastId
        ]);

        return $n;
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
