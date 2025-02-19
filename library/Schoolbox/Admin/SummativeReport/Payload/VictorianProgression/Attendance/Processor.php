<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression\Attendance;

use League\Flysystem\FilesystemException;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Processor
{
    /**
     * Used to find a relationship between a folder and a particular
     * assessment by its title.
     */
    private const QUERY_ASSESSMENT_TITLE = "
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
     * Used to find a relationship between a folder and a particular
     * assessment by its worktype.
     */
    private const QUERY_ASSESSMENT_WORKTYPE = "
        SELECT sb.id AS box_id
        FROM v_assessment_relation var
        INNER JOIN assessment a ON a.id = var.assessment_id
        INNER JOIN submission_box sb ON sb.assessment_id = a.id
        INNER JOIN folder f ON var.folder_id = f.id
        WHERE var.folder_id = :folderId
        AND a.work_type_id = :workTypeId
        AND a.deleted_at IS NULL
        AND f.deleted_at IS NULL
    ";


    /**
     * Feedback creation query
     */
    private const QUERY_FEEDBACK = "
        INSERT INTO submission_return
        (box_id, marker, owner, review_route, comment, mark, instructor_mark, norm_mark, resubmission_required, created_at, published_at, published_by, updated_at)
        VALUES
        (:boxId, :marker, :owner, :reviewRoute, :comment, :mark, :instructorMark, :normMark, :resubmissionRequired, :createdAt, :publishedAt, :publishedBy, :updatedAt)
    ";


    /**
     * @var \PDOStatement
     */
    private \PDOStatement $assessmentQuery;


    /**
     * @var \PDOStatement
     */
    private \PDOStatement $feedbackQuery;


    /**
     * A list of log messages that we can report to users
     *
     * @var array
     */
    private array $outcome = [];


    /**
     * Constructor
     *
     * @param FilesystemProxy $filesystemProxy
     * @param UserMapper $userMapper
     * @param Mapper $folderMapper
     * @param string|null $assessmentTitle
     * @param int|null $workTypeId
     * @param \Alaress_Date_Format $publishAt
     * @param string|null $folderCode
     * @param bool $perform
     */
    public function __construct(
        private FilesystemProxy $filesystemProxy,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private ?string $assessmentTitle,
        private ?int $workTypeId,
        private \Alaress_Date_Format $publishAt,
        private ?string $folderCode,
        private bool $perform
    ) {
        /* We're going to reuse this a lot
         */
        if (null !== $this->assessmentTitle) {
            $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT_TITLE);
        } elseif (null !== $this->workTypeId) {
            $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT_WORKTYPE);
        } else {
            /* This should never happen via the future ui (validation prevents it(
             * and cli encounters are a misuse.
             */
            throw new \RuntimeException('Specify one of assessment title or work type to identify assessments.');
        }

        $this->feedbackQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_FEEDBACK);
    }


    /**
     * Process feedbacks
     *
     * @param Model $file
     * @param User $creator
     * @return array
     * @throws FilesystemException
     */
    public function parseFeedbacks(
        \Schoolbox\Resource\Storage\Model $file,
        \Schoolbox\User $creator,
    ): array {
        /* Load a particular file
         */
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $reader->setExpectedColumns([
            'Class Code',
            'Student External ID',
            'Normalised Mark',
            'Comment'
        ]);

        /* Compute publish date just once
         */
        $publishAt = $this->publishAt->getDatabase();

        /* Maintain a row counter
         */
        $i = 0;

        foreach ($reader as $row) {
            /* Remember the row number
             */
            $i++;

            /* Process feedbacks
             *     - $creator (Alaress Admin) owns all assessments and feedbacks
             *     - Assume all feedbacks are for a Due Work
             *     - Assessment title is presumed "Attendance Semester 1"
             *     - Due Date arbitrarily chosen to be in Semester 1, 2024
             */

            /* "Attendance Semester 1" should be an assessment in this folder
             * If not, skip it
             */
            $folderCode = trim((string)$row['Class Code']);

            /* If we specified a folder code, decide if this matches
             * If not, skip it
             */
            if (null !== $this->folderCode && $folderCode !== $this->folderCode) {
                $this->log(sprintf(
                    'Row %s: folder code %s does not match requested %s. Skipping',
                    $i,
                    $folderCode,
                    $this->folderCode
                ));

                continue;
            }

            /* Assume this exists, I guess, for now
             */
            try {
                $folder = $this->folderMapper->findByCode($folderCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log(sprintf(
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
            $externalId = trim((string)$row['Student External ID']);

            try {
                $student = $this->userMapper->findByExternalId($externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log(sprintf(
                    'Row %s: student %s not found; skipping',
                    $i,
                    $externalId
                ));

                continue;
            }

            /* Then find the assessment's corresponding submission box id
             */
            if ($this->assessmentTitle) {
                $this->assessmentQuery->execute([
                    'title' => $this->assessmentTitle,
                    'folderId' => $folder->getId()
                ]);
            } elseif ($this->workTypeId) {
                $this->assessmentQuery->execute([
                    'workTypeId' => $this->workTypeId,
                    'folderId' => $folder->getId()
                ]);
            }

            /* Presume only one tuple exists
             */
            $boxId = $this->assessmentQuery->fetch(\PDO::FETCH_ASSOC)['box_id'] ?? null;

            /* For some reason this homepage doesn't have the assessment set up
             */
            if (null === $boxId) {
                $this->log(sprintf(
                    'Row %s: unknown submission_box for assessment in folder %s (%s)',
                    $i,
                    $folder->getTitle(),
                    $folderCode
                ));

                continue;
            }

            /* Actual marks and comments
             */
            $comment = trim((string)$row['Comment']);

            /* TODO Compute human-readable mark from assessment's mark scheme instead
             */
            $csvMark = trim((string)$row['Normalised Mark']);
            $humanReadableMark = $csvMark . '%';
            $normalisedMark = (float)$csvMark;

            /* Slam in a new submission_return
             */
            if ($this->perform) {
                $datetime = \Alaress_Date_Format::now()->getDatabase();

                $this->feedbackQuery->execute([
                    'boxId' => $boxId,
                    'marker' => $creator->getId(),
                    'owner' => $student->getId(),
                    'reviewRoute' => 'instructor',
                    'comment' => $comment,
                    'mark' => $humanReadableMark,
                    'instructorMark' => $humanReadableMark,
                    'normMark' => $normalisedMark,
                    'resubmissionRequired' => 0,
                    'createdAt' => $datetime,
                    'publishedAt' => $publishAt,
                    'publishedBy' => $creator->getId(),
                    'updatedAt' => $datetime
                ]);
            }

            $this->log(sprintf(
                'Row %s: added attendance score %s (%s) for student %s in folder %s (%s)',
                $i,
                $humanReadableMark,
                $normalisedMark,
                $externalId,
                $folder->getId(),
                $folderCode
            ));
        }

        return $this->outcome;
    }


    /**
     * Log outcomes to multiple places
     *
     * @param string $message
     * @return void
     */
    private function log(string $message): void
    {
        $this->outcome[microtime()] = $message;
    }
}
