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

use Alaress\Lock\LockRefreshException;
use Schoobox\User;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Factory;
use Schoolbox\Mapper\Exception;
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
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * column 2 values that we care about
     */
    private const ALLOWED_SEMESTERS = ['SEM2', '2023'];


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INPUT,
            self::OPT_MAKE_CNODES
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

The --nodes option forces the script to parse curriculum nodes and make them,
if they don't already exist. Used to simulate a production-ish setup in your vagrants.

Usage: import.php [--input=path/to/my/file.csv] [--nodes] [--perform]


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

        /** @var AssessmentDataSource $assessmentMapperProxy */
        $this->assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

        /* This person "created" all the feedbacks and assessments
         */
        $creator = $this->userMapper->findOneByColumn('username', \Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);

        /* Optionally arbitrarily make curriculum nodes if they don't already exist
         */
        if ($this->getOpt(self::OPT_MAKE_CNODES)) {
            $this->parseCurriculumNodes($lock, $filename);
        }

        /* Slap in assessment feedbacks
         */
        $this->parseFeedbacks($lock, $filename, $creator);

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
        $row = fgetcsv($fp);

        /* Maintain a row counter
         */
        for ($i = 2;  $row = fgetcsv($fp); ++$i) {
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

            $this->curriculumNodeMapper->save($node);

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
     * Process feedbacks
     *
     * @param LockInterface $lock
     * @param string $filename
     * @param \Schoolbox\User $creator
     * @return void
     * @throws LockRefreshException
     * @throws \UnexpectedValueException
     */
    private function parseFeedbacks(LockInterface $lock, string $filename, \Schoolbox\User $creator): void
    {
        /* Load a particular file
         */
        $fp = fopen($filename, 'r');

        /* Skip header row
         */
        $row = fgetcsv($fp);

        /* Maintain a row counter
         */
        for ($i = 2;  $row = fgetcsv($fp); ++$i) {
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

            /* TODO build it in their prod first
             */
            $workTypeId = -1;
            $gradingScheme = -1;

            /* TODO maybe needs be non-zero - can't remember
             */
            $weight = 0;

            /* TODO pick an actual date
             */
            $publishDate = '2024-01-01 01:01:00';
            $openDate = '2024-01-01 01:01:01';
            $dueDate = '2024-01-01 01:01:02';
            $closeDate = '2024-01-01 01:01:03';

            /* Avengers Assemble
             */
            $assessmentData = [
                'assessmentType' => AssessmentType::TYPE_DUE_WORK,
                'title' => $row[9],
                'workType' => $workTypeId,
                'description' => '',
                'creator' => $creator,
                'weight' => $weight,
                'curriculumCodes' => $this->parseCurriculumCode($row[8]),
                'publishDate' => $publishDate,
                'openDate' => $openDate,
                'dueDate' => $dueDate,
                'closeDate' => $closeDate,
                'markType' => MarkType::TYPE_LETTER,
                'gradingScheme' => $gradingScheme,
                'visibility' => SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT
            ];

            /* Make it then save it
             */
            $assessmentFactory = new Factory($creator, $assessmentData, AssessmentType::TYPE_DUE_WORK, null);
            $assessment = $assessmentFactory->produce();

            $this->assessmentMapperProxy->save($assessment, $creator);

            $this->getLogger()->info(sprintf(
                'Row %s: created new assessment "%s" (%s) in folder %s',
                $assessment->getId(),
                $assessment->getTitle(),
                $folder->getTitle()
            ));
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
