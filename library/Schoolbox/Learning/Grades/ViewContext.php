<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\User;

/**
 * Carries the selected grades view mode and the user whose visibility rules should apply.
 */
final class ViewContext
{
    /**
     * @param string $mode One of the ViewMode constants.
     */
    public function __construct(
        private readonly string $mode,
        private readonly User $viewer,
        private readonly bool $canViewInstructorMarks,
    ) {
    }

    /**
     * Return the selected grades view mode.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Return the effective viewer for visibility and mark calculations.
     */
    public function getViewer(): User
    {
        return $this->viewer;
    }

    /**
     * Return whether instructor-only marks should be included in the rendered view.
     */
    public function canViewInstructorMarks(): bool
    {
        return $this->canViewInstructorMarks;
    }

    /**
     * Return a human-readable label for the selected view mode.
     */
    public function getLabel(): string
    {
        return $this->mode;
    }

    /**
     * Return the query string used to preserve the selected view mode across navigation.
     */
    public function getQueryString(): string
    {
        return 'viewMode=' . rawurlencode($this->mode);
    }

    /**
     * Return the query suffix used to preserve the selected view mode across navigation.
     */
    public function getQuerySuffix(): string
    {
        return '?' . $this->getQueryString();
    }
}
