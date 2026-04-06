<?php

/**
 * CSV Importer for Penola's Semester 2, 2023 data
 *
 * Imports a Semester 2, 2023 csv containing feedbacks that need to get rammed into
 * Penola's system. It consumes a csv they provided and probably can't be readily
 * reused for anyone else.
 *
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lock\LockRefreshException;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Factory;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\IFolder;
use Symfony\Component\Lock\LockInterface;

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
class ImporterTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'VicProgression_PCC_Sem2_2023';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --input
     *
     * Path to csv file
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * Arbitrarily try to make curriculum nodes
     */
    private const OPT_MAKE_CNODES = 'nodes';


    /**
     * Arbitrarily try to make folders
     */
    private const OPT_MAKE_FOLDERS = 'folders';


    /**
     * Arbitrarily try to make assessments
     */
    private const OPT_MAKE_ASSESSMENTS = 'assessments';


    /**
     * Arbitrarily try to make students
     */
    private const OPT_MAKE_STUDENTS = 'students';


    /**
     * Id corresponding to the Victorian Curriculum Progression Points work type
     * and will be in the lu_work_type table
     */
    private const OPT_WORK_TYPE_ID = 'worktype:';


    /**
     * Id corresponding to the Victorian Curriculum Progression Points grade group
     * and will be in the submission_grade_group table
     */
    private const OPT_GRADE_GROUP_ID = 'gradegroup:';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Arbitrarily-spawned folders end up here; in PCC, this corresponds to:
     * Archived Classes > Classes 2023
     */
    private const CONTAINER_FOLDER_ID = 26982;


    /**
     * column 2 values that we care about
     */
    private const ALLOWED_SEMESTERS = ['SEM2', '2023'];


    /**
     * Encode their external id into our description. lol
     */
    private const IMPORT_IDENTIFIER = '2023-import-%s';


    /**
     * We allow import of these numeric feedback marks, specifically
     */
    private const VALID_NUMERIC_DATA = [1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10, 10.5, 11];


    /**
     * A set of Submission State Codes as defined by your instance's admin,
     * combined with VALID_NUMERIC_DATA, to build an overall whitelist of
     * legal values to import (DNPP et al. are vic gov submission states)
     *
     * @var array
     */
    private array $validData = [];


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INPUT,
            self::OPT_MAKE_CNODES,
            self::OPT_MAKE_FOLDERS,
            self::OPT_MAKE_ASSESSMENTS,
            self::OPT_MAKE_STUDENTS,
            self::OPT_WORK_TYPE_ID,
            self::OPT_GRADE_GROUP_ID
        ];
    }


    /**
     * @var \Schoolbox\Curriculum\Node\Mapper
     */
    private \Schoolbox\Curriculum\Node\Mapper $curriculumNodeMapper;


    /**
     * @var AssessmentDataSource
     */
    private AssessmentDataSource $assessmentMapperProxy;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Code\Mapper
     */
    private \Schoolbox\Resource\Folder\Code\Mapper $folderCodeMapper;


    /**
     * @var \Schoolbox\Learning\DueWork\StateMapper
     */
    private \Schoolbox\Learning\DueWork\StateMapper $stateMapper;


    /**
     * @var \Schoolbox\Error\Mapper
     */
    private \Schoolbox\Error\Mapper $errorMapper;


    /**
     * Feedback creation query
     */
    private const QUERY_FEEDBACK = "
        INSERT INTO submission_return
        (box_id, marker, owner, review_route, mark, instructor_mark, resubmission_required, created_at, published_at, published_by, updated_at)
        VALUES
        (:boxId, :marker, :owner, :reviewRoute, :mark, :instructorMark, :resubmissionRequired, :createdAt, :publishedAt, :publishedBy, :updatedAt)
    ";


    /**
     * Arbitrarily choose actual dates...can't think of a better Semester 2 date than N7 Day, heh
     */
    private const ASSESSMENT_SEM2_DATE_PUBLISH = '7/11/2023 12:01am';
    private const ASSESSMENT_SEM2_DATE_OPEN = '7/11/2023 12:02am';
    private const ASSESSMENT_SEM2_DATE_DUE = '7/11/2023 12:03am';
    private const ASSESSMENT_SEM2_DATE_CLOSE = '7/11/2023 12:04am';


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

CSV Importer for Penola's Semester 2, 2023 data

Imports a Semester 2, 2023 csv containing feedbacks that need to get rammed into
Penola's system. It consumes a csv they provided and probably can't be readily
reused for anyone else.

--worktype is mandatory and needs to be obtained either from the admin lists ui
or from the lu_work_type database table.

--gradegroup is mandatory and needs to be obtained either from a Create Due Work
ui or from the submission_grade_group database table.

Usage: import.php --input=path/to/my/file.csv --assessments --worktype=... --gradegroup=... [--perform]


Vagrant Simulation
------------------
The --nodes option forces the script to parse curriculum nodes and make them,
if they don't already exist. Used to simulate a production-ish setup in your vagrants.

The --folders option forces the script to parse folder codes and make them,
if they don't already exist. Used to simulate a production-ish setup in your vagrants.

The --students option forces the script to make new users with a given external id
to simulate users existing in prod, but don't in your vagrant.

Usage: import.php [--input=path/to/my/file.csv] [--nodes] [--folders] [--students] [--perform]


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        /* Check input file was specified
         */
        $filename = $this->getOpt(self::OPT_LONG_INPUT);

        if (null === $filename) {
            $this->getLogger()->error('no --input file specified.');
            exit;
        }

        $this->getLogger()->info('Script commencing.');

        /* Dependencies
         */
        $this->curriculumNodeMapper = \Schoolbox\Curriculum\Node\Mapper::getInstance();
        $this->userMapper = \Schoolbox\UserMapper::getInstance();
        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $this->folderCodeMapper = \Schoolbox\Resource\Folder\Code\Mapper::getInstance();
        $this->stateMapper = \Schoolbox\Learning\DueWork\StateMapper::getInstance();
        $this->errorMapper = \Schoolbox\Error\Mapper::getInstance();

        /** @var AssessmentDataSource $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
        $this->assessmentMapperProxy = $assessmentMapperProxy;

        /* Combined with the numeric data that we can chart, we can build a whitelist of
         * legal values (numerics + submission states) to process as either dots or ratings
         */
        $this->validData = array_merge(self::VALID_NUMERIC_DATA, $this->stateMapper->findAll()->indexBy('code')->keys());

        /* Optionally arbitrarily pre-make curriculum nodes if they don't already exist
         */
        if ($this->getOpt(self::OPT_MAKE_CNODES)) {
            $this->parseCurriculumNodes($lock, $filename);
        }

        /* Optionally arbitrarily pre-make folders if they don't already exist
         */
        if ($this->getOpt(self::OPT_MAKE_FOLDERS)) {
            $this->parseFolders($lock, $filename);
        }

        /* Optionally arbitrarily pre-make students if they don't already exist
         */
        if ($this->getOpt(self::OPT_MAKE_STUDENTS)) {
            $this->parseStudents($lock, $filename);
        }

        /* Optionally arbitrarily pre-make assessments+feedbacks
         * This is why you're here!
         */
        if ($this->getOpt(self::OPT_MAKE_ASSESSMENTS)) {
            /* Work Type Id is required for this to work
             */
            if ('' === trim((string)$this->getOpt(self::OPT_WORK_TYPE_ID))) {
                $this->getLogger()->error('No work type id specified.');
                exit;
            }

            /* Grade Group Id is required for this to work
             */
            if ('' === trim((string)$this->getOpt(self::OPT_GRADE_GROUP_ID))) {
                $this->getLogger()->error('No grade group id specified.');
                exit;
            }

            /* This person "created" all the feedbacks and assessments
             */
            $creator = $this->userMapper->findOneByColumn('username', \Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);

            $this->parseFeedbacks($lock, $filename, $creator);
        }

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Create curriculum nodes ahead of time, in case they don't already exist.
     *
     * Camille should already have assigned them properly in their prod environment
     * so this is basically for your vagrants
     *
     * @param LockInterface $lock
     * @param string $filename
     * @return void
     * @throws LockRefreshException
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function parseCurriculumNodes(LockInterface $lock, string $filename): void
    {
        /* Remember ones we've seen
         */
        $seen = [];

        /* Load a particular file
         */
        $fp = fopen($filename, 'r');

        /* Skip header row
         */
        $row = fgetcsv($fp, escape: '');

        /* Maintain a row counter
         */
        for ($i = 2; $row = fgetcsv($fp, escape: ''); ++$i) {
            $this->refreshLock($lock);

            /* Ignore column 0, that's the Academic Cycle id we don't care about
             * Ignore column 1, that's the Academic Year the row is about. Can assume everything is for 2023.
             * Ignore column 12, that's a modify datetime we don't care about
             */

            /* Ignore anything in column 2 that is about SEM1 - we don't care about those results.
             */
            if (!\in_array($row[2], self::ALLOWED_SEMESTERS, false)) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: not for semester 2, found %s. Skipping.',
                    $i,
                    $row[2]
                ));

                continue;
            }

            /* Strip out suffixes C2 and _2 from column 8
             */
            $curriculumCode = $this->parseCurriculumCode($row[8]);

            $this->getLogger()->debug(sprintf(
                'Row %s: parsed %s out of %s',
                $i,
                $curriculumCode,
                $row[8]
            ));

            /* Seen it? Skip it
             */
            if (isset($seen[$curriculumCode])) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: %s already exists. Skipping.',
                    $i,
                    $curriculumCode
                ));

                continue;
            }

            /* See if it already exists; if not, spawn it
             */
            $node = $this->curriculumNodeMapper->createModel()
                ->setCode($curriculumCode)
                ->setName($row[9])
                /* Randomly hardcoded for now
                 */
                ->setOrder(1)
                ->setDescription('')
                ->setEnabled(true)
                ->setUuid('');

            if ($this->perform()) {
                $this->curriculumNodeMapper->save($node);
            }

            /* Remember we saw this
             */
            $seen[$curriculumCode] = $row[9];

            $this->getLogger()->debug(sprintf(
                'Row %s: created new curriculum node %s "%s"',
                $i,
                $curriculumCode,
                $row[9]
            ));
        }

        /* Tell users what you did
         */
        foreach ($seen as $code => $name) {
            $this->getLogger()->info(sprintf(
                'Created curriculum node %s "%s"',
                $code,
                $name
            ));
        }

        fclose($fp);
    }


    /**
     * Create folders ahead of time, in case they don't already exist.
     *
     * Camille should already have assigned them properly in their prod environment
     * so this is basically for your vagrants
     *
     * @param LockInterface $lock
     * @param string $filename
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws LockRefreshException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function parseFolders(LockInterface $lock, string $filename): void
    {
        /* New folders spawn randomly at the root
         */
        $rootFolder = $this->folderMapper->findById(self::CONTAINER_FOLDER_ID);

        /* Remember ones we've seen
         */
        $seen = [];

        /* Load a particular file
         */
        $fp = fopen($filename, 'r');

        /* Skip header row
         */
        $row = fgetcsv($fp, escape: '');

        /* Maintain a row counter
         */
        for ($i = 2; $row = fgetcsv($fp, escape: ''); ++$i) {
            $this->refreshLock($lock);

            /* Ignore column 0, that's the Academic Cycle id we don't care about
             * Ignore column 1, that's the Academic Year the row is about. Can assume everything is for 2023.
             * Ignore column 12, that's a modify datetime we don't care about
             */

            /* Ignore anything in column 2 that is about SEM1 - we don't care about those results.
             */
            if (!\in_array($row[2], self::ALLOWED_SEMESTERS, false)) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: not for semester 2, found %s. Skipping.',
                    $i,
                    $row[2]
                ));

                continue;
            }

            $classCode = $row[13];
            $className = $row[7];

            /* Seen it? Skip it
             */
            if (isset($seen[$classCode])) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: %s already exists. Skipping.',
                    $i,
                    $classCode
                ));

                continue;
            }

            /* See if it already exists; if not, spawn it
             */
            try {
                $folder = $this->folderMapper->findByCode($classCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $folder = $this->folderMapper->createModel()
                    ->setName($className)
                    ->setParent($rootFolder)
                    ->setSequence(1);

                if ($this->perform()) {
                    $this->folderMapper->save($folder);
                }

                $this->getLogger()->debug(sprintf(
                    'Row %s: created new folder %s (%s)',
                    $i,
                    $className,
                    $classCode
                ));

                /* Make folder codes
                 */
                try {
                    $folderCode = $this->folderCodeMapper->findOneByColumn('code', $classCode);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $folderCode = $this->folderCodeMapper->createModel()
                        ->setCode($classCode)
                        ->setFolder($folder);

                    if ($this->perform()) {
                        $this->folderCodeMapper->save($folderCode);
                    }

                    $this->getLogger()->debug(sprintf(
                        'Row %s: created new folder code %s for folder %s',
                        $i,
                        $classCode,
                        $className,
                    ));
                }
            }

            /* Remember we saw this
             */
            $seen[$classCode] = $className;
        }

        /* Tell users what you did
         */
        foreach ($seen as $code => $name) {
            $this->getLogger()->info(sprintf(
                'Created folder %s (%s)',
                $name,
                $code
            ));
        }

        fclose($fp);
    }


    /**
     * Create students ahead of time, in case they don't already exist.
     *
     * @param LockInterface $lock
     * @param string $filename
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws LockRefreshException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function parseStudents(LockInterface $lock, string $filename): void
    {
        /* New folders spawn randomly at the root
         */
        $rootFolder = $this->folderMapper->createModel()->setId(IFolder::ROOT_ID);

        /* Local dependencies
         */
        $roleMapper = \Schoolbox\RoleMapper::getInstance();
        $campusMapper = \Schoolbox\CampusMapper::getInstance();

        /* Remember ones we've seen
         */
        $seen = [];

        /* Load a particular file
         */
        $fp = fopen($filename, 'r');

        /* Skip header row
         */
        $row = fgetcsv($fp, escape: '');

        /* Maintain a row counter
         */
        for ($i = 2; $row = fgetcsv($fp, escape: ''); ++$i) {
            $this->refreshLock($lock);

            /* Ignore column 0, that's the Academic Cycle id we don't care about
             * Ignore column 1, that's the Academic Year the row is about. Can assume everything is for 2023.
             * Ignore column 12, that's a modify datetime we don't care about
             */

            /* Ignore anything in column 2 that is about SEM1 - we don't care about those results.
             */
            if (!\in_array($row[2], self::ALLOWED_SEMESTERS, false)) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: not for semester 2, found %s. Skipping.',
                    $i,
                    $row[2]
                ));

                continue;
            }

            $externalId = $row[3];

            /* Seen it? Skip it
             */
            if (isset($seen[$externalId])) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: %s already exists. Skipping.',
                    $i,
                    $externalId
                ));

                continue;
            }

            /* See if it already exists; if not, spawn it
             */
            try {
                $user = $this->userMapper->findByExternalId($externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $roleId = 3; // "Senior Students" in sales data
                $campusId = 1; // "Secondary" campus in sales data

                $user = $this->userMapper->createModel()
                    ->setExternalId($externalId)
                    ->setUsername($externalId)
                    ->setFirstname($externalId)
                    ->setLastname($externalId)
                    ->setRole($roleMapper->findById($roleId))
                    ->setCampusCollection($campusMapper->createCollection()->add($campusMapper->findById($campusId)))
                    ->setLocalEmail(false)
                    ->setEportfolioEnable(false)
                    ->setUseInternalMail(false)
                    ->setEnabled(true);

                if ($this->perform()) {
                    $this->userMapper->save($user);
                }

                $this->getLogger()->debug(sprintf(
                    'Row %s: created new user %s',
                    $i,
                    $externalId
                ));
            }

            /* Remember we saw this
             */
            $seen[$externalId] = true;
        }

        /* Tell users what you did
         */
        foreach ($seen as $externalId => $val) {
            $this->getLogger()->info(sprintf(
                'Created user %s',
                $externalId
            ));
        }

        fclose($fp);
    }


    /**
     * Process feedbacks
     *
     * @param LockInterface $lock
     * @param string $filename
     * @param \Schoolbox\User $creator
     * @return void
     * @throws Exception
     * @throws Deleted
     * @throws LockRefreshException
     * @throws InvalidColumnException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     */
    private function parseFeedbacks(LockInterface $lock, string $filename, \Schoolbox\User $creator): void
    {
        /* Remember ones we've seen
         */
        $seen = [];

        /* Local dependencies
         */
        $submissionBoxMapper = SubmissionBoxMapper::getInstance();
        $feedbackStatement = $this->userMapper->getDatabase()->prepare(self::QUERY_FEEDBACK);

        /* Load a particular file
         */
        $fp = fopen($filename, 'r');

        /* Skip header row
         */
        $row = fgetcsv($fp, escape: '');

        /* Maintain a row counter
         */
        for ($i = 2; $row = fgetcsv($fp, escape: ''); ++$i) {
            $this->refreshLock($lock);

            /* Ignore column 0, that's the Academic Cycle id we don't care about
             * Ignore column 1, that's the Academic Year the row is about. Can assume everything is for 2023.
             * Ignore column 12, that's a modify datetime we don't care about
             */

            /* Ignore anything in column 2 that is about SEM1 - we don't care about those results.
             */
            if (!\in_array($row[2], self::ALLOWED_SEMESTERS, false)) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: not for semester 2, found %s. Skipping.',
                    $i,
                    $row[2]
                ));

                continue;
            }

            /* Ignore feedback values that are outside the 6 - 10.5 range from column 11
             */
            $humanReadableMark = trim($row[11]);

            /* Only accept numeric data. This is the full superset of data we COULD display,
             * since each report is supposed to be a "window of levels" proximal to the current
             * year level of a student.
             */
            if (!\in_array($humanReadableMark, $this->validData, false)) {
                $this->getLogger()->debug(sprintf(
                    'Row %s: invalid feedback value %s. Skipping.',
                    $i,
                    $humanReadableMark
                ));

                continue;
            }

            /* Process feedbacks
             *     - $creator (Alaress Admin) owns all assessments and feedbacks
             *     - Assume all feedbacks are for a Due Work
             *     - Title comes from column 9 (named after the curriculum node's name)
             *     - Work Type hardcoded to whatever is in their instance
             *     - Mark Type is Letter Grade
             *     - Empty description (no human was intended to read this)
             *     - Due Date arbitrarily chosen to be in Semester 2, 2023
             *     - Marks available to everyone I guess
             */

            /* Build it in their prod first
             */
            $workTypeId = $this->getOpt(self::OPT_WORK_TYPE_ID);
            $gradingScheme = $this->getOpt(self::OPT_GRADE_GROUP_ID);

            /* TODO maybe needs be non-zero - can't remember
             */
            $weight = '';

            /* Enforce feedbacks at midnight (no times specified)
             */
            $modified = sprintf('%s  00:00:00', trim($row[12]));
            $feedbackPublishedAt = (new \Alaress_Date_Format($modified, 'd/m/Y H:i:s'))->getDatabase();

            /* This incarnation of assessment belonged to this folder: $row[13]
             * Assessment Id in the csv was shared among Subject Codes, which I thought to be the same folder.
             * Anthony added $row[13] on Jun 12, exposing that assessment ids from the csv (while shared among
             * Subject Codes) should be unique per folder.
             */
            $folderCode = trim((string)$row[13]);

            /* Assume this exists, I guess, for now
             */
            $this->getLogger()->debug(sprintf(
                'Row %s: looking for folder code %s',
                $i,
                $folderCode
            ));

            try {
                $folder = $this->folderMapper->findByCode($folderCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Row %s: did not find folder code %s. Skipping',
                    $i,
                    $folderCode
                ));

                continue;
            }

            /* Figure out if we already made this assessment.
             * If we did, reuse it.
             * Otherwise, spawn it first.
             */
            $externalAssessmentId = $row[4];

            /* Used to recycle an assessment we already imported
             */
            $description = sprintf(
                self::IMPORT_IDENTIFIER,
                $externalAssessmentId
            );

            /* Avengers Assemble
             */
            if (!isset($seen[$folderCode][$externalAssessmentId])) {
                /* May have been made in separate import somewhere
                 * Just recycle it
                 */
                try {
                    $assessment = $this->assessmentMapperProxy->findOneByColumn('description', $description);

                    $seen[$folderCode][$externalAssessmentId] = $assessment;
                } catch (\Schoolbox\Mapper\Exception $e) {
                    try {
                        $curriculumNode = $this->curriculumNodeMapper->findOneByColumn('code', $this->parseCurriculumCode($row[8]));
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $this->getLogger()->warning(sprintf(
                            'Row %s: could not find curriculum code %s or original value %s. Skipping',
                            $i,
                            $this->parseCurriculumCode($row[8]),
                            $row[8]
                        ));

                        continue;
                    }

                    $assessmentData = [
                        'assessmentType' => AssessmentType::TYPE_DUE_WORK,
                        'context' => $folder,
                        'title' => $row[9],
                        'workType' => $workTypeId,
                        /* Secretly encode external id here
                         */
                        'description' => $description,
                        'creator' => $creator,
                        'weight' => $weight,
                        /* This is literally a csv in a one-element array
                         */
                        'curriculumCodes' => [(string)$curriculumNode->getId()],
                        /* Maybe make this optional between SEM2 and SEM1 dates
                         */
                        'publishDate' => self::ASSESSMENT_SEM2_DATE_PUBLISH,
                        'openDate' => self::ASSESSMENT_SEM2_DATE_OPEN,
                        'dueDate' => self::ASSESSMENT_SEM2_DATE_DUE,
                        'closeDate' => self::ASSESSMENT_SEM2_DATE_CLOSE,
                        'markType' => (string)MarkType::TYPE_LETTER,
                        'instructor-markType' => (string)MarkType::TYPE_LETTER,
                        'gradingScheme' => $gradingScheme,
                        'instructor-gradingScheme' => $gradingScheme,
                        'visibility' => SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT,
                    ];

                    /* Make it then save it
                     */
                    $assessmentFactory = new Factory($creator, $assessmentData, AssessmentType::TYPE_DUE_WORK, null, $this->errorMapper);
                    $assessment = $assessmentFactory->produce();

                    if ($this->perform()) {
                        $this->assessmentMapperProxy->save($assessment, $creator);
                        $seen[$folderCode][$externalAssessmentId] = $assessment;
                    }

                    $this->getLogger()->info(sprintf(
                        'Row %s: created new assessment "%s" (%s) in folder %s',
                        $i,
                        $assessment->getTitle(),
                        $assessment->getId(),
                        $folder->getTitle()
                    ));
                }
            }

            $this->getLogger()->debug(sprintf(
                'Row %s: preparing feedback with human readable mark %s',
                $i,
                $humanReadableMark
            ));

            /* We can only generate assessments in perform mode
             */
            if ($this->perform()) {
                /* Summon it from memory (yes I know...)
                 */
                $assessment = $seen[$folderCode][$externalAssessmentId];

                /* Make a feedback for a student
                 *
                 * listen...you could try to use the various mapperproxies, participants, plans
                 * and all this other andyland domain-level stuff, or you can just ram it straight
                 * into the bullseye
                 */
                $box = $submissionBoxMapper->findOneByColumn('assessment_id', $assessment->getId());
                $student = $this->userMapper->findByExternalId($row[3]);

                $params = [
                    'boxId' => $box->getId(),
                    'marker' => $creator->getId(),
                    'owner' => $student->getId(),
                    'reviewRoute' => 'instructor',
                    'mark' => $row[11],
                    'instructorMark' => $row[11],
                    'resubmissionRequired' => 0,
                    'createdAt' => \Alaress_Date_Format::now()->getDatabase(),
                    'publishedAt' => $feedbackPublishedAt,
                    'publishedBy' => $creator->getId(),
                    'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
                ];

                $feedbackStatement->execute($params);
            }
        }

        fclose($fp);
    }


    /**
     * Strip unwanted suffixes
     *
     * @param string $val
     * @return string
     */
    private function parseCurriculumCode(string $val): string
    {
        $curriculumCode = trim($val);

        $curriculumCode = preg_replace('/C2$/', '', $curriculumCode);

        return preg_replace('/_2$/', '', $curriculumCode);
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
