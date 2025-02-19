<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression\Attendance;

use League\Flysystem\FilesystemException;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Processor
{
    /**
     * Used to make logging a bit more bearable
     */
    private const WARNING = 'warning';
    private const INFO = 'info';


    /**
     * Used to find a relationship between a folder and a particular
     * assessment by its title.
     */
    private const QUERY_ASSESSMENT_TITLE = "
        SELECT 
            a.id AS assessment_id, 
            sb.id AS box_id
        FROM mv_assessment_relation mvar
        INNER JOIN assessment a ON a.id = mvar.assessment_id
        INNER JOIN submission_box sb ON sb.assessment_id = a.id
        INNER JOIN folder f ON mvar.folder_id = f.id
        WHERE mvar.folder_id = :folderId
        AND a.title = :title
        AND a.deleted_at IS NULL
        AND f.deleted_at IS NULL
    ";


    /**
     * Used to find a relationship between a folder and a particular
     * assessment by its worktype.
     */
    private const QUERY_ASSESSMENT_WORKTYPE = "
        SELECT 
            a.id AS assessment_id, 
            sb.id AS box_id
        FROM mv_assessment_relation mvar
        INNER JOIN assessment a ON a.id = mvar.assessment_id
        INNER JOIN submission_box sb ON sb.assessment_id = a.id
        INNER JOIN folder f ON mvar.folder_id = f.id
        WHERE mvar.folder_id = :folderId
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
     * @param MapperProxy $assessmentMapperProxy
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
        private MapperProxy $assessmentMapperProxy,
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
     * @throws Exception
     * @throws Deleted
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
                ), self::WARNING);

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
                ), self::WARNING);

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
                ), self::WARNING);

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

            /* Used for a feedback's created_at and updated_at timestamps
            *
             * Make them universally the same, to better keep track of things after an import
             * has occurred
             */
            $datetime = \Alaress_Date_Format::now()->getDatabase();

            /* Add a feedback for every assessment that might match the title/worktype combo
             * This is kind of maybe too loose?
             */
            while ($data = $this->assessmentQuery->fetch(\PDO::FETCH_ASSOC)) {
                $boxId = $data['box_id'] ?? null;
                $assessmentId = $data['assessment_id'] ?? null;

                /* For some reason this homepage doesn't have the assessment set up
                 */
                if (null === $boxId) {
                    $this->log(sprintf(
                        'Row %s: unknown submission_box for assessment in folder %s (%s)',
                        $i,
                        $folder->getTitle(),
                        $folderCode
                    ), self::WARNING);

                    continue;
                }

                /* Try to spawn an IAssessable instance
                 *
                 * Note that this is horribly non-performant since it requires the assessmentMapperProxy
                 * to do enormous amounts of heavy lifting: you could shortcut this by maybe spawning stubs
                 * containing only the Mark Scheme chosen by the assessment...
                 */
                $assessment = $this->assessmentMapperProxy->findById($assessmentId);

                /* Compute human-readable mark from assessment's mark scheme instead
                 */
                $normalisedMark = (float)trim((string)$row['Normalised Mark']);

                /* This is what the student would see
                 */
                $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark);

                /* This is what the instructor would see, due to SA-18452
                 */
                $instructorHumanReadableMark = $assessment->getInstructorMarkScheme()->getHumanReadableMark($normalisedMark);

                /* Actual marks and comments
                 */
                $comment = trim((string)$row['Comment']);

                /* Slam in a new submission_return
                 */
                if ($this->perform) {
                    $this->feedbackQuery->execute([
                        'boxId' => $boxId,
                        'marker' => $creator->getId(),
                        'owner' => $student->getId(),
                        'reviewRoute' => 'instructor',
                        'comment' => $comment,
                        'mark' => $humanReadableMark,
                        'instructorMark' => $instructorHumanReadableMark,
                        'normMark' => $normalisedMark,
                        'resubmissionRequired' => 0,
                        'createdAt' => $datetime,
                        'publishedAt' => $publishAt,
                        'publishedBy' => $creator->getId(),
                        'updatedAt' => $datetime
                    ]);
                }

                $this->log(sprintf(
                    'Row %s: added grade %s (instructor grade %s, normalised mark %s) for %s (%s) in assessment "%s" (%s) found in folder %s (%s)',
                    $i,
                    $humanReadableMark,
                    $instructorHumanReadableMark,
                    $normalisedMark,
                    $student->getFullName(),
                    $externalId,
                    $assessment->getTitle(),
                    $assessment->getId(),
                    $folder->getId(),
                    $folderCode
                ), self::INFO);
            }
        }

        return $this->outcome;
    }


    /**
     * Log outcomes to multiple places
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    private function log(string $message, string $type): void
    {
        $this->outcome[] = [
            'message' => $message,
            'type' => $type
        ];
    }
}
