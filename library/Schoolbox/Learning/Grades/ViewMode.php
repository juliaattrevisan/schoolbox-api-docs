<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Schoolbox\User;

/**
 * Defines the supported server-side grade viewing modes.
 */
final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    /**
     * Return a supported view mode for a request value.
     * The default value is null so that callers can decide on an appropriate default depending on user type.
     *
     * @param null|string $value Requested view mode value.
     */
    public static function fromRequestValue(?string $value): ?string
    {
        return in_array($value, self::values(), true) ? $value : null;
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
     * Progress ticks are read-only for parents and when staff preview the page as a different effective viewer.
     *
     * @param User $currentUser Logged-in user.
     * @param User $effectiveViewer User whose visibility rules apply.
     * @return bool True when progress ticks should render as read-only.
     */
    public static function progressTicksReadOnly(User $currentUser, User $effectiveViewer): bool
    {
        return $effectiveViewer->getRole()->isParent() || $currentUser->getId() !== $effectiveViewer->getId();
    }

    /**
     * Comments are read-only when staff preview the page as a different effective viewer.
     *
     * @param User $currentUser Logged-in user.
     * @param User $effectiveViewer User whose visibility rules apply.
     * @return bool True when comments should render as read-only.
     */
    public static function commentsReadOnly(User $currentUser, User $effectiveViewer): bool
    {
        return $currentUser->getId() !== $effectiveViewer->getId();
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
