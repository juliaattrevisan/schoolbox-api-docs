<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport;

use League\Flysystem\FilesystemException;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\L10n\RosettaStone;
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
     * Assume a default time of 1:11pm
     */
    private const PUBLISH_TIME = '13:11:11';


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
     * @var \PDOStatement
     */
    private \PDOStatement $assessmentQuery;


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
     * Used to craft a returned work model so that its corresponding mapper
     * can create Participant and Participant Plan records for feedbacks
     *
     * @var SubmissionBoxMapper
     */
    private SubmissionBoxMapper $submissionBoxMapper;


    /**
     * Used to create Participant and Participant Plan records for a feedback
     *
     * @var ReturnedWorkMapper
     */
    private ReturnedWorkMapper $returnedWorkMapper;


    /**
     * Constructor
     *
     * @param MapperProxy $assessmentMapperProxy
     * @param FilesystemProxy $filesystemProxy
     * @param UserMapper $userMapper
     * @param Mapper $folderMapper
     * @param RosettaStone $rosettaStone
     * @param string $dateLocal
     * @param string $assessmentTitle
     * @param \Schoolbox\Learning\Assessment\WorkType\Model $workType
     * @param bool $perform
     */
    public function __construct(
        private MapperProxy $assessmentMapperProxy,
        private FilesystemProxy $filesystemProxy,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private RosettaStone $rosettaStone,
        private string $dateLocal,
        private string $assessmentTitle,
        private \Schoolbox\Learning\Assessment\WorkType\Model $workType,
        private bool $perform
    ) {
        /* We're going to reuse this a lot
         */
        $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT_TITLE_WORKTYPE);

        $this->submissionBoxMapper = SubmissionBoxMapper::getInstance();
        $this->returnedWorkMapper = ReturnedWorkMapper::getInstance();
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

        /* A shortcut to status screens
         */
        $links = [];

        /* Load a particular file
         */
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $reader->setExpectedColumns([
            'Class Code',
            'Student External ID',
            'Normalised Mark',
            'Comment',
            'Publish Date'
        ]);

        /* Maintain a row counter
         */
        $i = 0;

        /* The current datetime is used for publish_at if it was omitted
         */
        $now = \Alaress_Date_Format::now();

        foreach ($reader as $row) {
            /* Remember the row number
             */
            $i++;

            /* Compute publish date from csv
             * and also make it the same as created_at and updated_at to better keep track
             * of things after an import has occurred
             */
            $datetime = trim((string)$row['Publish Date']);

            if ('' === $datetime) {
                $datetime = $now->getDatabase();
            } else {
                /* Check the format of the date: try both database and local short
                 */
                try {
                    $datetime = sprintf('%s %s', (new \Alaress_Date_Format($datetime, $this->dateLocal))->getDatabaseDate(), self::PUBLISH_TIME);
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    $this->log(sprintf("Row %s: Invalid date format. Make sure it's correct and try again.", $i), self::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Process feedbacks
             */
            $folderCode = trim((string)$row['Class Code']);

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
                        "Row %s: Can't find Class Code. Make sure it's correct and try again.",
                        $i
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
                        'Row %s: student %s not found.',
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
                    'Row %s: multiple %s found for %s "%s" in folder "%s" (%s) using Work Type "%s" (%s).
                    Please ensure %s are uniquely identifiable before continuing.',
                    $i,
                    $this->rosettaStone->translate('activity', true),
                    $this->rosettaStone->translate('activity', false),
                    $this->rosettaStone->translate('activity', true),
                    $this->assessmentTitle,
                    $folder['title'],
                    $folder['id'],
                    $this->workType->getName(),
                    $this->workType->getId()
                ), self::WARNING);

                $errors++;

                continue;
            }

            /* No assessments found
             */
            if (0 === \count($candidateAssessments)) {
                $this->log(sprintf(
                    'Row %s: no %s found for "%s" in folder "%s" (%s) using Work Type "%s" (%s).',
                    $i,
                    $this->rosettaStone->translate('activity', true),
                    $this->assessmentTitle,
                    $folder['title'],
                    $folder['id'],
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
                        'Row %s: unknown submission_box for %s "%s" in folder "%s" (%s)',
                        $i,
                        $this->rosettaStone->translate('activity', false),
                        $this->assessmentTitle,
                        $folder['title'],
                        $folder['id']
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
                 * It must be between 0 and 100, inclusive
                 */
                $normalisedMark = (float)trim((string)$row['Normalised Mark']);

                if ($normalisedMark < 0 || $normalisedMark > 100) {
                    $this->log(sprintf(
                        'Row %s: normalised mark must be between 0 and 100 for %s "%s"',
                        $i,
                        $this->rosettaStone->translate('activity', false),
                        $this->assessmentTitle,
                    ), self::WARNING);

                    $errors++;

                    continue;
                }

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
                $learner = $this->userMapper->findById($learner['id']);

                if ($this->perform) {
                    /* TODO inefficient/non-conventional, I know
                     *  Solution had a time constraint.
                     */
                    $box = $this->submissionBoxMapper->findById($boxId);
                    $adf = new \Alaress_Date_Format($datetime, \Alaress_Date_Format::DATABASE_FORMAT);

                    $returnedWork = $this->returnedWorkMapper->createModel()
                        ->setSubmissionBox($box)
                        ->setMarker($creator)
                        ->setOwner($learner)
                        ->setReviewRoute('instructor')
                        ->setComment($comment)
                        ->setHumanReadableMark($humanReadableMark)
                        ->setHumanReadableInstructorMark($instructorHumanReadableMark)
                        ->setNormalizedMark($normalisedMark)
                        ->setResubmissionRequired(false)
                        ->setPublishedBy($creator->getId())
                        ->setCreatedAt($adf)
                        ->setPublishedAt($adf)
                        ->setUpdatedAt($adf);

                    $this->returnedWorkMapper->save($returnedWork);
                }

                /* Some convenient shortcuts
                 * Note that $folder['title'] also contains the class code, so we can reasonably presume
                 * that this is unique.
                 */
                $links[$folder['title']] = [
                    'id' => $assessmentId,
                    'title' => $assessment->getTitle(),
                ];

                $this->log(sprintf(
                    'Row %s: added grade %s (instructor grade %s, normalised mark %s) for %s (%s) in %s "%s" (%s) found in folder "%s" (%s)',
                    $i,
                    $humanReadableMark,
                    $instructorHumanReadableMark,
                    $normalisedMark,
                    $learner->getFullName(),
                    $externalId,
                    $this->rosettaStone->translate('activity', false),
                    $assessment->getTitle(),
                    $assessment->getId(),
                    $folder['title'],
                    $folder['id']
                ), self::INFO);
            }
        }

        return [
            'messages' => $this->messages,
            'errors' => $errors,
            'links' => $links
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
