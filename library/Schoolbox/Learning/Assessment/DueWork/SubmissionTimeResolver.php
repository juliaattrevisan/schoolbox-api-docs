<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;

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
