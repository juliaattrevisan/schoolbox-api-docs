<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy\Feedback;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;

class DueWork implements IStrategy
{
    /**
     * A column whose presence decides we'll use this strat
     */
    public const string DISCRIMINATING_COLUMN = 'box_id';


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
     * @param RosettaStone $rosettaStone
     * @param string $assessmentTitle
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param ReturnedWorkMapper $returnedWorkMapper
     */
    public function __construct(
        private bool $perform,
        private \Schoolbox\L10n\RosettaStone $rosettaStone,
        private string $assessmentTitle,
        private \Schoolbox\Assessment\Component\SubmissionBoxMapper $submissionBoxMapper,
        private \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper $returnedWorkMapper,
    ) {
    }


    /**
     * Process one row from the csv
     *
     * @param int $i A row counter so we can tell people about the line we're processing in the csv (ie. where errors occur in the csv)
     * @param array $folder The folder in which the assessment lives
     * @param \Schoolbox\User $learner The student who is receiving the feedback
     * @param IAssessable $assessment A due work domain instance
     * @param array $assessmentData A row from the $assessmentQuery result set
     * @param \Alaress_Date_Format $adf The derived publish date of the feedback
     * @param null|float $normalisedMark A numeric value out of 100
     * @param null|string $learnerHumanReadableMark Corresponds to a mark type/submission state that learners/families see
     * @param null|string $instructorHumanReadableMark Corresponds to a mark type/submission state that staff see
     * @param string $comment A comment, if present; data type could be anything from a csv
     * @param User $creator The presumed author of the feedback (specified in the ui)
     * @return void
     * @throws Exception
     * @throws Deleted
     */
    public function processRow(
        int $i,
        array $folder,
        \Schoolbox\User $learner,
        IAssessable $assessment,
        array $assessmentData,
        \Alaress_Date_Format $adf,
        ?float $normalisedMark,
        ?string $learnerHumanReadableMark,
        ?string $instructorHumanReadableMark,
        string $comment,
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

        /* Slam in a new submission_return
         */
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
                ->setComment(trim($comment))
                ->setHumanReadableMark($learnerHumanReadableMark)
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
            'Row %s: added grade %s (instructor grade "%s", normalised mark %s) for %s (%s) in %s "%s" (%s) found in folder "%s" (%s)',
            $i,
            $learnerHumanReadableMark,
            $instructorHumanReadableMark,
            $normalisedMark ?? 'null',
            $learner->getFullName(),
            $learner->getExternalId(),
            $this->rosettaStone->translate('activity', false),
            $assessment->getTitle(),
            $assessment->getId(),
            $folder['title'],
            $folder['id']
        ), self::INFO);
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
     * The column in the csv that defines this strat
     *
     * @return string
     */
    public function getDiscriminatorColumn(): string
    {
        return self::DISCRIMINATING_COLUMN;
    }


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
