<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

/**
 * For a given IFeedback instance, figure out what its corresponding data model mapper happens to be, along with the
 * database table that this mapper affects. This is used to accommodate the pseudo-polymorphic database schema that
 * uses (feedback id, feedback table) to address multiple tables such as reporting_feedback, submission_return, etc.
 * It's really inconvenient to do constant instanceof checks just to figure out which schema to interrogate.
 *
 * This enum centralises such derivations
 */
enum FeedbackMeta: string
{
    case DUE_WORK = 'Schoolbox\Learning\Assessment\DueWork\Feedback\Model';
    case REPORTING = 'Schoolbox\Learning\Assessment\Reporting\Feedback\Model';

    /**
     * Returns some mapper info corresponding to the given IFeedback instance
     *
     * @return array
     */
    public function translate(): array
    {
        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return match ($this) {
            FeedbackMeta::DUE_WORK => [
                'mapper' => \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper::getInstance(),
                'feedbackTable' => \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper::getInstance()->getTable()
            ],
            FeedbackMeta::REPORTING => [
                'mapper' => \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::getInstance(),
                'feedbackTable' => \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::getInstance()->getTable()
            ]
        };
    }
}
