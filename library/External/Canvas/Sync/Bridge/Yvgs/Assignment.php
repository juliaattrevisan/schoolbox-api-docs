<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Yvgs;

use Schoolbox\Assessment\Component\SubmissionBox;

class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{
    /**
     * @inheritDoc
     */
    public function getMarkVisibility(bool $muted): int
    {
        // If muted, students cannot see grades left on assignment in Canvas
        if ($muted) {
            return SubmissionBox::MARK_VISIBILITY_STAFF_ONLY;
        }
        return SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT;
    }
}
