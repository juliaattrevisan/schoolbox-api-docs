<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy\Feedback;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;

interface IStrategy
{
    /**
     * Used to make logging a bit more bearable
     */
    public const string WARNING = 'warning';
    public const string INFO = 'info';

    /**
     * Process one row from the csv
     *
     * @param int $i A row counter so we can tell people about the line we're processing in the csv (ie. where errors occur in the csv)
     * @param array $folder The folder in which the assessment lives
     * @param \Schoolbox\User $learner The student who is receiving the feedback
     * @param IAssessable $assessment A due work or reporting domain instance
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
    ): void;


    /**
     * The column in the csv that defines this strat
     *
     * @return string
     */
    public function getDiscriminatorColumn(): string;


    /**
     * Returns a list of error messages encountered during processing rows
     *
     * @return array
     */
    public function getMessages(): array;


    /**
     * Returns the number of errors we found during processing rows
     *
     * @return int
     */
    public function getErrorCount(): int;


    /**
     * Returns a list of convenient links to status screens
     *
     * @return array
     */
    public function getLinks(): array;
}
