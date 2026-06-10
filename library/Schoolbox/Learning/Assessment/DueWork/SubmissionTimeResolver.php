<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Resolves the submitted-at timestamp for teacher-managed bulk submissions.
 *
 * The Manage Submissions modal can post its hidden "submitted on time" checkbox
 * before an assessment is actually overdue. This resolver keeps the backend
 * authoritative: use the request time unless the due-date override is genuinely
 * available, and clone the selected timestamp so the assessment dates are not
 * mutated while saving multiple students.
 */
class SubmissionTimeResolver
{
    public function resolve(
        IAssessable $assessment,
        bool $postedPunctual,
        Alaress_Date_Format $submittedNow
    ): Alaress_Date_Format {
        if ($postedPunctual && ($assessment->isOverdue($submittedNow) || $assessment->isPastClose($submittedNow))) {
            $dueAt = $assessment->getDueAt();
            if ($dueAt instanceof Alaress_Date_Format) {
                return (clone $dueAt)->addSeconds(-1);
            }
        }

        return clone $submittedNow;
    }
}
