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
use Schoolbox\Resource\Storage\IStorage;
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
     * assessment by its (assessment title, worktype) combo
     */
    private const QUERY_ASSESSMENT_TITLE_WORKTYPE = "
        SELECT 
            a.id AS assessment_id, 
            a.title AS assessment_title,
            sb.id AS box_id
        FROM mv_assessment_relation mvar
        INNER JOIN assessment a ON a.id = mvar.assessment_id
        INNER JOIN submission_box sb ON sb.assessment_id = a.id
        INNER JOIN folder f ON mvar.folder_id = f.id
        WHERE mvar.folder_id = :folderId
        AND a.work_type_id = :workTypeId
        AND a.title = :assessmentTitle
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
     * A set of (user id, fullname) tuples to save some mem
     *
     * @var array
     */
    private array $learnerLookup = [];


    /**
     * A set of (folder code, folder id, folder title) tuples to save some mem
     *
     * @var array
     */
    private array $folderLookup = [];


    /**
     * A list of log messages that we can report to users
     *
     * @var array
     */
    private array $messages = [];


    /**
     * Constructor
     *
     * @param MapperProxy $assessmentMapperProxy
     * @param FilesystemProxy $filesystemProxy
     * @param UserMapper $userMapper
     * @param Mapper $folderMapper
     * @param string $assessmentTitle
     * @param \Schoolbox\Learning\Assessment\WorkType\Model $workType
     * @param \Alaress_Date_Format $publishAt
     * @param string|null $folderCode
     * @param bool $perform
     */
    public function __construct(
        private MapperProxy $assessmentMapperProxy,
        private FilesystemProxy $filesystemProxy,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private string $assessmentTitle,
        private \Schoolbox\Learning\Assessment\WorkType\Model $workType,
        private \Alaress_Date_Format $publishAt,
        private ?string $folderCode,
        private bool $perform
    ) {
        /* We're going to reuse this a lot
         */
        $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT_TITLE_WORKTYPE);
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
        IStorage $file,
        \Schoolbox\User $creator,
    ): array {
        /* A list of warnings/errors/scenarios that schools must fix before we can continue
         */
        $errors = 0;

        /* Load a particular file
         */
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $reader->setExpectedColumns([
            'Assessment Title',
            'Class Code',
            'Student External ID',
            'Normalised Mark',
            'Comment'
        ]);

        /* Compute publish date just once
         * and also make it the same as created_at and updated_at to better keep track
         * of things after an import has occurred
         */
        $datetime = $this->publishAt->getDatabase();

        /* Maintain a row counter
         */
        $i = 0;

        foreach ($reader as $row) {
            /* Remember the row number
             */
            $i++;

            /* Process feedbacks
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

                $errors++;

                continue;
            }

            /* Assume this exists, I guess, for now
             */
            if (!isset($this->folderLookup[$folderCode])) {
                try {
                    $folder = $this->folderMapper->findByCode($folderCode);

                    $this->folderLookup[$folderCode] = [
                        'id' => $folder->getId(),
                        'title' => $folder->getTitle()
                    ];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->log(sprintf(
                        'Row %s: did not find folder code %s. Skipping',
                        $i,
                        $folderCode
                    ), self::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Yank it from a local cache
             */
            $folder = $this->folderLookup[$folderCode];

            /* Avengers Assemble
             */

            /* First see if this student exists
             */
            $externalId = trim((string)$row['Student External ID']);

            if (!isset($this->learnerLookup[$externalId])) {
                try {
                    $learner = $this->userMapper->findByExternalId($externalId);

                    $this->learnerLookup[$externalId] = [
                        'id' => $learner->getId(),
                        'fullname' => $learner->getFullname(),
                    ];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->log(sprintf(
                        'Row %s: student %s not found; skipping',
                        $i,
                        $externalId
                    ), self::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Yank it from a local cache
             */
            $learner = $this->learnerLookup[$externalId];

            /* Then find the assessment's corresponding submission box id
             */
            $this->assessmentQuery->execute([
                'assessmentTitle' => $this->assessmentTitle,
                'workTypeId' => $this->workType->getId(),
                'folderId' => $folder['id']
            ]);

            /* There's only meant to be one assessment that matches the (title, worktype, folder) tuple.
             * Multiple assessments that match means we can't determine where feedback is meant to go.
             * We need the schools to resolve the conflicting assessments.
             *
             * There can be only be one, highlander.
             */
            $candidateAssessments = $this->assessmentQuery->fetchAll(\PDO::FETCH_ASSOC);

            if (\count($candidateAssessments) > 1) {
                $this->log(sprintf(
                    'Row %s: multiple assessments found for assessment "%s" in folder "%s" using worktype "%s" (%s). 
                    Please ensure assessments are uniquely identifiable before continuing.',
                    $i,
                    $this->assessmentTitle,
                    $folder['title'],
                    $this->workType->getName(),
                    $this->workType->getId()
                ), self::WARNING);

                $errors++;

                continue;
            }

            foreach ($candidateAssessments as $data) {
                $boxId = $data['box_id'] ?? null;
                $assessmentId = $data['assessment_id'] ?? null;

                /* For some reason this homepage doesn't have the assessment set up
                 */
                if (null === $boxId) {
                    $this->log(sprintf(
                        'Row %s: unknown submission_box for assessment "%s" in folder %s',
                        $i,
                        $this->assessmentTitle,
                        $folder['title'],
                    ), self::WARNING);

                    $errors++;

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
                        'owner' => $learner['id'],
                        'reviewRoute' => 'instructor',
                        'comment' => $comment,
                        'mark' => $humanReadableMark,
                        'instructorMark' => $instructorHumanReadableMark,
                        'normMark' => $normalisedMark,
                        'resubmissionRequired' => 0,
                        'createdAt' => $datetime,
                        'publishedAt' => $datetime,
                        'publishedBy' => $creator->getId(),
                        'updatedAt' => $datetime
                    ]);
                }

                $this->log(sprintf(
                    'Row %s: added grade %s (instructor grade %s, normalised mark %s) for %s (%s) in assessment "%s" (%s) found in folder %s',
                    $i,
                    $humanReadableMark,
                    $instructorHumanReadableMark,
                    $normalisedMark,
                    $learner['fullname'],
                    $externalId,
                    $assessment->getTitle(),
                    $assessment->getId(),
                    $folder['id'],
                ), self::INFO);
            }
        }

        return [
            'messages' => $this->messages,
            'errors' => $errors
        ];
    }


    /**
     * Log messages to multiple places
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    private function log(string $message, string $type): void
    {
        $this->messages[] = [
            'message' => $message,
            'type' => $type,
        ];
    }
}
