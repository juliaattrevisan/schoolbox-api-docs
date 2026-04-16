<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy;

use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Reporting implements IStrategy
{
    /**
     * A column whose presence decides we'll use this strat
     */
    public const string DISCRIMINATING_COLUMN = 'Human Readable Mark';


    /**
     * The column headings unique to the Due Work import template for feedbacks
     */
    private const array EXPECTED_COLUMNS = [
        'Class Code',
        'Student External ID',
        'Human Readable Mark',
        'Comment',
        'Publish Date'
    ];


    /**
     * A reporting-specific query to find assessments.
     *
     * Used to find a relationship between a folder and a particular assessment by its (assessment title, worktype) combo
     */
    private const string QUERY_ASSESSMENT_TITLE_WORKTYPE = "
        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            r.id AS reporting_id
        FROM mv_assessment_relation mvar
        INNER JOIN assessment a ON a.id = mvar.assessment_id
        INNER JOIN reporting r ON r.assessment_id = a.id
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
     * @param Mapper $reportingMapper
     * @param StateMapper $stateMapper
     * @param IFeedbackDataSource $feedbackDataSource
     */
    public function __construct(
        private bool $perform,
        private \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy,
        private \Schoolbox\L10n\RosettaStone $rosettaStone,
        private string $assessmentTitle,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private StateMapper $stateMapper,
        private IFeedbackDataSource $feedbackDataSource,
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
     * @param mixed $value A human readable mark, if present; data type could be anything from a csv
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
        $reportingId = $assessmentData['reporting_id'] ?? null;
        $assessmentId = $assessmentData['assessment_id'] ?? null;

        /* For some reason this homepage doesn't have the assessment set up
         */
        if (null === $reportingId) {
            $this->log(sprintf(
                'Row %s: unknown reporting_id for %s "%s" in folder "%s" (%s)',
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
        /** @var \Schoolbox\Learning\Assessment\Reporting\Model $assessment */
        $assessment = $this->assessmentMapperProxy->findById($assessmentId);

        /* Find the Reporting sub object, as annoying as that is
         */
        try {
            $reporting = $this->reportingMapper->findOneByColumn('assessment_id', $assessment->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* For some reason this couldn't be found: maybe multiplayer schoolbox where someone deleted it while you
             * were actively using it
             */
            $this->errorCount++;

            return;
        }

        /* Slam in a new reporting feedback; we reuse this $learner further down for logging
         */
        $learner = $this->userMapper->findById($learner['id']);

        /* Spawn a new feedback and assign properties
         */
        $feedback = new \Schoolbox\Learning\Assessment\Reporting\Feedback\Model()
            ->setAssessable($assessment)
            ->setReporting($reporting)
            ->setReviewee($learner)
            ->setComment(null)
            ->setReviewer($creator);

        /* Feedback Comments are optional based on the Reporting toggle
         */
        if ($assessment->getFeedbackCommentsEnable()) {
            $feedback->setComment(trim((string)$comment));
        }

        /** Get mark schemes
         * - Instructor mark scheme is used to normalise what was inputted by the user
         * - Learner mark scheme is used when displaying the mark back to learners/parents
         */
        $instructorScheme = $assessment->getInstructorMarkScheme();
        $learnerScheme = $assessment->getMarkScheme();

        $normalisedMark = null;
        $learnerHumanReadableMark = null;

        /* This human readable mark is given by the instructor, from which we derive the normalised mark
         */
        $instructorHumanReadableMark = trim((string)$value);

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            $normalisedMark = $instructorScheme->getNormalisedMark($instructorHumanReadableMark);

            /* Find out what the learner's version would have been and save that too
             * Arguably you could skip this derivation, only doing it if $instructor scheme was different to $learner scheme
             */
            $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $instructorHumanReadableMark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 */
                $code = $this->stateMapper->findOneByColumn('display_as', $instructorHumanReadableMark);

                $normalisedMark = $code->getNormaliseAs();

                /* There isn't a learner-specific Submission State: it's the same for both parties.
                 */
                $learnerHumanReadableMark = $instructorHumanReadableMark;
            }
        }

        $feedback
            ->setNormalisedMark($normalisedMark)
            ->setHumanReadableMark($learnerHumanReadableMark)
            ->setHumanReadableInstructorMark($instructorHumanReadableMark);

        /* This is always presumed to be published
         */
        $feedback
            ->setPublishedBy($creator->getId())
            ->setPublishedAt($adf);

        if ($this->perform) {
            $this->feedbackDataSource->save($feedback);
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
            $learnerHumanReadableMark,
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
