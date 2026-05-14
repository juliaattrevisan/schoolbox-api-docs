<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback;

use Schoolbox\Learning\Assessment\IAssessable;

class Visibility
{
    public static function getMarkVisibilityDescription(string $markVisibility): string
    {
        return IAssessable::MARK_VISIBILITY_STRINGS[$markVisibility] ?? '';
    }

    /**
     * Return an array of available mark visibilities for assessments.
     * @param bool $markVisibilityAllowStudents
     * @param bool $markVisibilityAllowParents
     * @return array
     */
    public static function getAvailableVisibilities(
        bool $markVisibilityAllowStudents,
        bool $markVisibilityAllowParents
    ): array {
        $availableVisibilities = [
            IAssessable::MARK_VISIBILITY_PRIVATE,
        ];
        if ($markVisibilityAllowStudents) {
            $availableVisibilities[] = IAssessable::MARK_VISIBILITY_STUDENT;
        }
        if ($markVisibilityAllowParents) {
            $availableVisibilities[] = IAssessable::MARK_VISIBILITY_PARENT;
        }
        if ($markVisibilityAllowStudents && $markVisibilityAllowParents) {
            $availableVisibilities[] = IAssessable::MARK_VISIBILITY_PARENT_STUDENT;
        }
        return $availableVisibilities;
    }
}
