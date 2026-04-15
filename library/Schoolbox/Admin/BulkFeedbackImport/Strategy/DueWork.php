<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\UserMapper;

class DueWork implements IStrategy
{
    /**
     * A column whose presence decides we'll use this strat
     */
    public const string DISCRIMINATING_COLUMN = 'Normalised Mark';


    /**
     * The column headings unique to the Due Work import template for feedbacks
     */
    private const array EXPECTED_COLUMNS = [
        'Class Code',
        'Student External ID',
        'Normalised Mark',
        'Comment',
        'Publish Date'
    ];


    /**
     * A due work-specific query to find assessments. It directly references submission boxes due to a time constraint
     * in the implementation; I haven't modernised it or addressed its shortcomings
     *
     * Used to find a relationship between a folder and a particular assessment by its (assessment title, worktype) combo
     */
    private const string QUERY_ASSESSMENT_TITLE_WORKTYPE = "
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
     * A list of log messages that we can report to users
     *
     * @var array
     */
    private array $messages = [];


    /**
     * A shortcut to status screens
     *
     * @var array
     */
    private array $links = [];


    /**
     * Number of errors encountered during processing
     *
     * @var int
     */
    private int $errorCount = 0;


    /**
     * Constructor.
     *
     * @param bool $perform
     * @param MapperProxy $assessmentMapperProxy
     * @param RosettaStone $rosettaStone
     * @param string $assessmentTitle
     * @param UserMapper $userMapper
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param ReturnedWorkMapper $returnedWorkMapper
     */
    public function __construct(
        private bool $perform,
        private \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy,
        private \Schoolbox\L10n\RosettaStone $rosettaStone,
        private string $assessmentTitle,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Assessment\Component\SubmissionBoxMapper $submissionBoxMapper,
        private \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper $returnedWorkMapper,
    ) {
    }


    /**
     * Process one row from the csv
     *
     * @param int $i
     * @param array $folder
     * @param string $externalId External identifier of $learner
     * @param array $learner
     * @param array $assessmentData
     * @param \Alaress_Date_Format $adf The derived publish date of the feedback
     * @param mixed $value A normalised mark, if present; data type could be anything from a csv
     * @param mixed $comment A comment, if present; data type could be anything from a csv
     * @param User $creator
     * @return void
     * @throws Exception
     * @throws Deleted
     */
    public function processRow(
        int $i,
        array $folder,
        string $externalId,
        array $learner,
        array $assessmentData,
        \Alaress_Date_Format $adf,
        mixed $value,
        mixed $comment,
        \Schoolbox\User $creator
    ): void {
        $boxId = $assessmentData['box_id'] ?? null;
        $assessmentId = $assessmentData['assessment_id'] ?? null;

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

            /* Returns after an increment of errors
             */
            $this->errorCount++;

            return;
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
        $normalisedMark = (float)trim((string)$value);

        if ($normalisedMark < 0 || $normalisedMark > 100) {
            $this->log(sprintf(
                'Row %s: normalised mark must be between 0 and 100 for %s "%s"',
                $i,
                $this->rosettaStone->translate('activity', false),
                $this->assessmentTitle,
            ), self::WARNING);

            /* Returns after an increment of errors
             */
            $this->errorCount++;

            return;
        }

        /* This is what the student would see
         */
        $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark);

        /* This is what the instructor would see, due to SA-18452
         */
        $instructorHumanReadableMark = $assessment->getInstructorMarkScheme()->getHumanReadableMark($normalisedMark);

        /* Actual marks and comments
         */
        $comment = trim((string)$comment);

        /* Slam in a new submission_return
         */
        $learner = $this->userMapper->findById($learner['id']);

        if ($this->perform) {
            /* TODO inefficient/non-conventional, I know
             *  Solution had a time constraint.
             */
            $box = $this->submissionBoxMapper->findById($boxId);

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
        $this->links[$folder['title']] = [
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


    /**
     * The column in the csv that defines this strat
     *
     * @return string
     */
    public function getDiscriminatorColumn(): string
    {
        return self::DISCRIMINATING_COLUMN;
    }


    /**
     * Returns a list of expected columns unique to the due work import template
     *
     * @return array|string[]
     */
    public function getExpectedColumns(): array
    {
        return self::EXPECTED_COLUMNS;
    }


    /**
     * The query used to find assessments quickly
     *
     * @return string
     */
    public function getAssessmentQuery(): string
    {
        return self::QUERY_ASSESSMENT_TITLE_WORKTYPE;
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

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }


    /**
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }


    /**
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }
}
