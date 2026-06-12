<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\User;

/**
 * Resolved context for a grade view-mode request.
 */
final readonly class ViewModeContext
{
    /**
     * @param string $viewMode Current grade-report view mode.
     * @param User $effectiveViewer User whose visibility rules apply.
     * @param bool $canViewInstructorMarks Whether instructor marks should be visible.
     * @param bool $progressTicksReadOnly Whether progress ticks should render as read-only.
     * @param bool $commentsReadOnly Whether comments should render as read-only.
     */
    public function __construct(
        public string $viewMode,
        public User $effectiveViewer,
        public bool $canViewInstructorMarks,
        public bool $progressTicksReadOnly,
        public bool $commentsReadOnly
    ) {
    }
}
