<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy;

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
     * @param string $externalId External identifier of $learner
     * @param array $learner The student who is receiving the feedback
     * @param array $assessmentData A row from the $assessmentQuery result set
     * @param \Alaress_Date_Format $adf The derived publish date of the feedback
     * @param mixed $value A human readable mark, if present; data type could be anything from a csv
     * @param mixed $comment A comment, if present; data type could be anything from a csv
     * @param User $creator The presumed author of the feedback (specified in the ui)
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
    ): void;


    /**
     * The column in the csv that defines this strat
     *
     * @return string
     */
    public function getDiscriminatorColumn(): string;


    /**
     * Returns a list of expected columns unique to the due work import template
     *
     * @return array|string[]
     */
    public function getExpectedColumns(): array;


    /**
     * The query used to find assessments quickly
     *
     * @return string
     */
    public function getAssessmentQuery(): string;


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
