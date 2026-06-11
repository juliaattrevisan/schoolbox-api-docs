<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

/**
 * Defines the supported server-side grade viewing modes.
 */
final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    /**
     * Return a supported view mode for a request value, defaulting to staff mode for invalid values.
     *
     * @param null|string $value Requested view mode value.
     */
    public static function fromRequestValue(?string $value): string
    {
        return in_array($value, self::values(), true) ? $value : self::STAFF;
    }

    /**
     * Return all supported view mode request values in display order.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return [self::STAFF, self::STUDENT, self::PARENT];
    }

    /**
     * Staff-selected student and parent preview modes must not mutate assessment state.
     *
     * @param string $viewMode Requested or resolved grade view mode.
     * @param bool   $effectiveViewerIsParent Whether the effective viewer has a parent role.
     * @return bool True when the grade report should render as read-only.
     */
    public static function isReadOnlyMode(string $viewMode, bool $effectiveViewerIsParent): bool
    {
        return $effectiveViewerIsParent || in_array($viewMode, [self::STUDENT, self::PARENT], true);
    }

    /**
     * Return frontend progress-tick transitions for interactive grade views.
     * Progress-tick transitions tell the frontend progress-tick widget what status to move an assessment to when the
     * user clicks the tick.  E.g. When the status is 'incomplete', when the user clicks the tick, the next status
     * depends on whether the user is an instructor.  If instructor, 'teacherAssessed'. If student, 'selfAssessed'.
     *
     * @param bool $effectiveViewerIsInstructor Whether the effective viewer has instructor access to the folder.
     * @return array{incomplete: string, selfAssessed: string, teacherAssessed: string}
     */
    public static function tickTransitions(bool $effectiveViewerIsInstructor): array
    {
        return [
            'incomplete'      => $effectiveViewerIsInstructor ? 'teacherAssessed' : 'selfAssessed',
            'selfAssessed'    => $effectiveViewerIsInstructor ? 'teacherAssessed' : 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];
    }
}
