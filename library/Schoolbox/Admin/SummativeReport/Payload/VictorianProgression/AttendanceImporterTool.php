<?php

/**
 * CSV Importer for Penola's Semester 1, 2024 Attendance data
 *
 * Penola have an Accellerus export that contains feedbacks for particular assessments that
 * circumstantially represent a student's attendance in a class. This imports the feedback
 * into the db.
 *
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

use Alaress\Lock\LockRefreshException;
use Schoolbox\Application\CliTool;
use Schoolbox\User;
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
class AttendanceImporterTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'Attendance_PCC_Sem1_2024';


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
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INPUT,
        ];
    }


    /**
     * This particular assessment needs feedbacks
     */
    private const ASSESSMENT_TITLE = 'Attendance Semester 1';


    /**
     * Arbitrarily choose actual dates
     */
    private const ASSESSMENT_SEM1_DATE_PUBLISH = '4/05/2023 12:01am';


    /**
     * Used to find a relationship between a folder and a particular
     * assessment by its title.
     */
    private const QUERY_ASSESSMENT = "
        SELECT sb.id AS box_id
        FROM v_assessment_relation var
        INNER JOIN assessment a ON a.id = var.assessment_id
        INNER JOIN submission_box sb ON sb.assessment_id = a.id
        INNER JOIN folder f ON var.folder_id = f.id
        WHERE var.folder_id = :folderId
        AND a.title = :title
        AND a.deleted_at IS NULL
        AND f.deleted_at IS NULL
    ";


    /**
     * Feedback creation query
     */
    private const QUERY_FEEDBACK = "
        INSERT INTO submission_return
        (box_id, marker, owner, review_route, comment, mark, norm_mark, resubmission_required, created_at, published_at, published_by, updated_at)
        VALUES
        (:boxId, :marker, :owner, :reviewRoute, :comment, :mark, :normMark, :resubmissionRequired, :createdAt, :publishedAt, :publishedBy, :updatedAt)
    ";


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \PDOStatement
     */
    private \PDOStatement $assessmentQuery;


    /**
     * @var \PDOStatement
     */
    private \PDOStatement $feedbackQuery;


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

CSV Importer for Penola's Semester 1, 2024 Attendance data

Penola have an Accellerus export that contains feedbacks for particular assessments that
circumstantially represent a student's attendance in a class. This imports the feedback
into the db.

Usage: attendanceImport.php --input=path/to/my/file.csv [--perform]


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
        $this->userMapper = \Schoolbox\UserMapper::getInstance();
        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();

        /* This person "created" all the feedbacks and assessments
         */
        $creator = $this->userMapper->findOneByColumn('username', \Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);

        /* We're going to reuse this a lot
         */
        $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT);
        $this->feedbackQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_FEEDBACK);

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
     * Process feedbacks
     *
     * @param LockInterface $lock
     * @param string        $filename
     * @param User          $creator
     * @return void
     * @throws LockRefreshException
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
        for ($i = 2; $row = fgetcsv($fp); ++$i) {
            $this->refreshLock($lock);

            /* Process feedbacks
             *     - $creator (Alaress Admin) owns all assessments and feedbacks
             *     - Assume all feedbacks are for a Due Work
             *     - Assessment title is presumed "Attendance Semester 1"
             *     - Due Date arbitrarily chosen to be in Semester 1, 2024
             */

            /* "Attendance Semester 1" should be an assessment in this folder
             * If not, skip it
             */
            $folderCode = trim((string)$row[3]);

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

            /* Avengers Assemble
             */

            /* First see if this student exists
             */
            $externalId = trim((string)$row[4]);

            try {
                $student = $this->userMapper->findByExternalId($externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Row %s: student %s not found; skipping',
                    $i,
                    $externalId
                ));

                continue;
            }

            /* Then find the assessment's corresponding submission box id
             */
            $this->assessmentQuery->execute([
                'title' => self::ASSESSMENT_TITLE,
                'folderId' => $folder->getId()
            ]);

            /* Presume only one tuple exists
             */
            $boxId = $this->assessmentQuery->fetch(\PDO::FETCH_ASSOC)['box_id'] ?? null;

            /* For some reason this homepage doesn't have the assessment set up
             */
            if (null === $boxId) {
                $this->getLogger()->warning(sprintf(
                    'Row %s: unknown submission_box for assessment in folder %s (%s)',
                    $i,
                    $folder->getTitle(),
                    $folderCode
                ));

                continue;
            }

            /* Actual marks and comments
             */
            $comment = trim((string)$row[6]);

            $csvMark = trim((string)$row[5]);
            $humanReadableMark = $csvMark . '%';
            $normalisedMark = (float)$csvMark;

            /* Slam in a new submission_return
             */
            $this->getLogger()->debug(sprintf(
                'Row %s: preparing attendance score %s (%s) for student %s in folder %s',
                $i,
                $humanReadableMark,
                $normalisedMark,
                $externalId,
                $folderCode
            ));

            if ($this->perform()) {
                $this->feedbackQuery->execute([
                    'boxId' => $boxId,
                    'marker' => $creator->getId(),
                    'owner' => $student->getId(),
                    'reviewRoute' => 'instructor',
                    'comment' => $comment,
                    'mark' => $humanReadableMark,
                    'normMark' => $normalisedMark,
                    'resubmissionRequired' => 0,
                    'createdAt' => \Alaress_Date_Format::now()->getDatabase(),
                    'publishedAt' => self::ASSESSMENT_SEM1_DATE_PUBLISH,
                    'publishedBy' => $creator->getId(),
                    'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
                ]);
            }

            $this->getLogger()->info(sprintf(
                'Row %s: added attendance score %s (%s) for student %s in folder %s (%s)',
                $i,
                $humanReadableMark,
                $normalisedMark,
                $externalId,
                $folder->getId(),
                $folderCode
            ));
        }

        fclose($fp);
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
