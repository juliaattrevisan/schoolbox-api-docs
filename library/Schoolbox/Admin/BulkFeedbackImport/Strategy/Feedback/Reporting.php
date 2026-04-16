<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy\Feedback;

use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;

class Reporting implements IStrategy
{
    /**
     * A column whose presence decides we'll use this strat
     */
    public const string DISCRIMINATING_COLUMN = 'reporting_id';


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
     * @param Mapper $reportingMapper
     * @param IFeedbackDataSource $feedbackDataSource
     */
    public function __construct(
        private bool $perform,
        private \Schoolbox\L10n\RosettaStone $rosettaStone,
        private string $assessmentTitle,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private IFeedbackDataSource $feedbackDataSource,
    ) {
    }


    /**
     * Process one row from the csv
     *
     * @param int $i A row counter so we can tell people about the line we're processing in the csv (ie. where errors occur in the csv)
     * @param array $folder The folder in which the assessment lives
     * @param \Schoolbox\User $learner The student who is receiving the feedback
     * @param IAssessable $assessment A reporting domain instance
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

        /* Find the Reporting sub object, as annoying as that is
         */
        try {
            $reporting = $this->reportingMapper->findOneByColumn('assessment_id', $assessmentId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* For some reason this couldn't be found: maybe multiplayer schoolbox where someone deleted it while you
             * were actively using it
             */
            $this->errorCount++;

            return;
        }

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
            $feedback->setComment(trim($comment));
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
