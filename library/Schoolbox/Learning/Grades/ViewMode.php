<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use InvalidArgumentException;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Defines the supported server-side grade viewing modes.
 *
 * IMPORTANT:
 * Only staff in ViewMode::STAFF can view instructor marks and transition progress ticks to 'teacherAssessed' status.
 * Folder write+ access alone is not enough: non-staff users can be granted write+ access and therefore satisfy
 * Access::isInstructor(), but that should not grant them staff-only grades-report privileges.
 */
final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    /**
     * @param ExternalUser $externalUser
     */
    public function __construct(private ExternalUser $externalUser)
    {
    }

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
     * Derive and return the current grade view-mode context.
     *
     * @param User $currentUser Logged-in user.
     * @param User $viewee Student whose grades are being viewed.
     * @param null|string $requestViewMode Requested view mode value.
     * @return array{
     *     viewMode: string,
     *     effectiveViewer: User,
     *     canViewInstructorMarks: bool,
     *     progressTicksReadOnly: bool,
     *     commentsReadOnly: bool
     * }
     */
    public function getViewModeContext(User $currentUser, User $viewee, ?string $requestViewMode): array
    {
        if ($currentUser->getRole()->isStaff()) {
            $viewMode = self::fromRequestValue($requestViewMode) ?? self::STAFF;
            $effectiveViewer = $this->getEffectiveViewer($currentUser, $viewee, $viewMode);
            $canViewInstructorMarks = $viewMode === self::STAFF;
        } else {
            $viewMode = '';
            $effectiveViewer = $currentUser;
            $canViewInstructorMarks = false;
        }

        return [
            'viewMode'               => $viewMode,
            'effectiveViewer'        => $effectiveViewer,
            'canViewInstructorMarks' => $canViewInstructorMarks,
            'progressTicksReadOnly'  => self::progressTicksReadOnly($currentUser, $effectiveViewer),
            'commentsReadOnly'       => self::commentsReadOnly($currentUser, $effectiveViewer),
        ];
    }

    /**
     * Resolve the user whose visibility rules apply for the given staff-selected view mode.
     *
     * Only valid when $currentUser is a staff member. Non-staff requests do not select a
     * mode and should use $currentUser as the effective viewer directly.
     *
     * @param User $currentUser Logged-in user.
     * @param User $student Student whose grades are being viewed.
     * @param string $mode One of the ViewMode constants. Callers must pre-validate the
     *                     requested value via ViewMode::fromRequestValue().
     * @throws InvalidArgumentException If called for a non-staff user or given an unknown mode.
     */
    public function getEffectiveViewer(User $currentUser, User $student, string $mode): User
    {
        if (!$currentUser->getRole()->isStaff()) {
            throw new InvalidArgumentException(
                'getEffectiveViewer() should only be called for staff users.'
            );
        }

        switch ($mode) {
            case self::STAFF:
                return $currentUser;

            case self::STUDENT:
                return $student;

            case self::PARENT:
                $this->externalUser->loadParents([$student]);
                $parent = $student->getParents()?->first()
                    ?? new User()->setId(0)->setRole(new Role()->setType(Role::PARENT));

                // Parents returned by loadParents() are not returned with their children
                // hydrated, and the synthetic parent has none. Set the student as the child of the
                // parent so downstream visibility checks will work.
                $parent->setChildren(
                    new GenericCollection(User::class)->setOffset($student->getId(), $student)
                );

                return $parent;

            default:
                throw new InvalidArgumentException("Unexpected value '$mode' for \$mode.");
        }
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
     * @param string $viewMode Current grade-report view mode.
     * @param bool $effectiveViewerIsInstructor Whether the effective viewer has instructor access to the folder.
     * @return array{incomplete: string, selfAssessed: string, teacherAssessed: string}
     */
    public static function tickTransitions(string $viewMode, bool $effectiveViewerIsInstructor): array
    {
        $canUseInstructorTransitions = $viewMode === self::STAFF && $effectiveViewerIsInstructor;

        return [
            'incomplete'      => $canUseInstructorTransitions ? 'teacherAssessed' : 'selfAssessed',
            'selfAssessed'    => $canUseInstructorTransitions ? 'teacherAssessed' : 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];
    }
}
