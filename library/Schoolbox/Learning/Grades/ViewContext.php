<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\User;

final class ViewContext
{
    public function __construct(
        private readonly string $mode,
        private readonly User $viewer,
        private readonly bool $canViewInstructorMarks,
    ) {
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getViewer(): User
    {
        return $this->viewer;
    }

    public function canViewInstructorMarks(): bool
    {
        return $this->canViewInstructorMarks;
    }

    public function getLabel(): string
    {
        return ViewMode::label($this->mode);
    }

    public function getQueryString(): string
    {
        return 'viewMode=' . rawurlencode($this->mode);
    }

    public function getQuerySuffix(): string
    {
        return '?' . $this->getQueryString();
    }
}
