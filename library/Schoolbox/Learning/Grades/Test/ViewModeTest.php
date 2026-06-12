<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Grades\ViewMode;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests grades view mode request value handling.
 */
class ViewModeTest extends TestCase
{
    /**
     * ViewMode accepts each supported request value unchanged.
     *
     * @param string $input Request value to normalize.
     * @param string $expected Expected ViewMode constant.
     */
    #[DataProvider('validModeProvider')]
    public function testFromRequestValueAcceptsValidModes(string $input, string $expected): void
    {
        self::assertSame($expected, ViewMode::fromRequestValue($input));
    }

    /**
     * Invalid request values return null by default so callers can choose an appropriate fallback.
     */
    public function testFromRequestValueReturnsNullForInvalidValues(): void
    {
        self::assertNull(ViewMode::fromRequestValue(null));
        self::assertNull(ViewMode::fromRequestValue(''));
        self::assertNull(ViewMode::fromRequestValue('guardian'));
    }

    /**
     * Staff-selected student and parent preview modes render read-only progress ticks.
     */
    public function testStudentAndParentPreviewModesRenderReadOnlyProgressTicks(): void
    {
        $staff = $this->createUser(1, Role::STAFF);

        self::assertFalse(ViewMode::progressTicksReadOnly($staff, $staff));
        self::assertTrue(ViewMode::progressTicksReadOnly($staff, $this->createUser(2, Role::STUDENT)));
        self::assertTrue(ViewMode::progressTicksReadOnly($staff, $this->createUser(3, Role::PARENT)));
    }

    /**
     * Actual parent users see read-only progress ticks.
     */
    public function testParentViewerProgressTicksAreReadOnly(): void
    {
        $parent = $this->createUser(1, Role::PARENT);

        self::assertTrue(ViewMode::progressTicksReadOnly($parent, $parent));
    }

    /**
     * Comments are read-only only when staff are previewing as another effective viewer.
     */
    public function testCommentsAreReadOnlyOnlyWhenViewingAsAnotherUser(): void
    {
        $staff = $this->createUser(123, Role::STAFF);
        $parent = $this->createUser(456, Role::PARENT);

        self::assertFalse(ViewMode::commentsReadOnly($staff, $staff));
        self::assertFalse(ViewMode::commentsReadOnly($parent, $parent));
        self::assertTrue(ViewMode::commentsReadOnly($staff, $parent));
    }

    /**
     * Non-staff users with folder write access still use learner tick transitions in the grade report.
     */
    public function testTickTransitionsRequireStaffViewModeAndInstructorAccess(): void
    {
        $instructorTransitions = [
            'incomplete'      => 'teacherAssessed',
            'selfAssessed'    => 'teacherAssessed',
            'teacherAssessed' => 'incomplete',
        ];
        $learnerTransitions = [
            'incomplete'      => 'selfAssessed',
            'selfAssessed'    => 'incomplete',
            'teacherAssessed' => 'incomplete',
        ];

        self::assertSame($instructorTransitions, ViewMode::tickTransitions(ViewMode::STAFF, true));
        self::assertSame($learnerTransitions, ViewMode::tickTransitions('', true));
        self::assertSame($learnerTransitions, ViewMode::tickTransitions(ViewMode::STUDENT, true));
        self::assertSame($learnerTransitions, ViewMode::tickTransitions(ViewMode::PARENT, true));
        self::assertSame($learnerTransitions, ViewMode::tickTransitions(ViewMode::STAFF, false));
    }

    /**
     * Staff users default to staff view mode when the request value is absent or invalid.
     */
    public function testStaffViewModeContextDefaultsToStaffMode(): void
    {
        $staff = $this->createUser(1, Role::STAFF);
        $student = $this->createUser(2, Role::STUDENT);
        $viewMode = new ViewMode($this->createExternalUserStub());
        $expectedContext = [
            'viewMode'               => ViewMode::STAFF,
            'effectiveViewer'        => $staff,
            'canViewInstructorMarks' => true,
            'progressTicksReadOnly'  => false,
            'commentsReadOnly'       => false,
        ];

        self::assertSame($expectedContext, $viewMode->getViewModeContext($staff, $student, null));
        self::assertSame($expectedContext, $viewMode->getViewModeContext($staff, $student, 'guardian'));
    }

    /**
     * Non-staff users do not select a view mode and use themselves as the effective viewer.
     */
    public function testNonStaffViewModeContextUsesCurrentUser(): void
    {
        $student = $this->createUser(2, Role::STUDENT);
        $viewee = $this->createUser(3, Role::STUDENT);
        $viewMode = new ViewMode($this->createExternalUserStub());

        self::assertSame([
            'viewMode'               => '',
            'effectiveViewer'        => $student,
            'canViewInstructorMarks' => false,
            'progressTicksReadOnly'  => false,
            'commentsReadOnly'       => false,
        ], $viewMode->getViewModeContext($student, $viewee, ViewMode::STAFF));
    }

    /**
     * Student preview mode uses the viewed student as the effective viewer.
     */
    public function testStudentPreviewModeUsesViewedStudentAsEffectiveViewer(): void
    {
        $staff = $this->createUser(1, Role::STAFF);
        $student = $this->createUser(2, Role::STUDENT);
        $viewMode = new ViewMode($this->createExternalUserStub());

        self::assertSame([
            'viewMode'               => ViewMode::STUDENT,
            'effectiveViewer'        => $student,
            'canViewInstructorMarks' => false,
            'progressTicksReadOnly'  => true,
            'commentsReadOnly'       => true,
        ], $viewMode->getViewModeContext($staff, $student, ViewMode::STUDENT));
    }

    /**
     * Parent preview mode loads the viewed student's parents and hydrates the parent child relationship.
     */
    public function testParentPreviewModeUsesLoadedParentAsEffectiveViewer(): void
    {
        $staff = $this->createUser(1, Role::STAFF);
        $student = $this->createUser(2, Role::STUDENT);
        $parent = $this->createUser(3, Role::PARENT);
        $externalUser = $this->createExternalUserMock();
        $externalUser
            ->expects(self::once())
            ->method('loadParents')
            ->with([$student])
            ->willReturnCallback(function (array $students) use ($student, $parent): array {
                $student->setParents((new GenericCollection(User::class))->add($parent));
                return $students;
            });
        $viewMode = new ViewMode($externalUser);

        self::assertSame([
            'viewMode'               => ViewMode::PARENT,
            'effectiveViewer'        => $parent,
            'canViewInstructorMarks' => false,
            'progressTicksReadOnly'  => true,
            'commentsReadOnly'       => true,
        ], $viewMode->getViewModeContext($staff, $student, ViewMode::PARENT));
        self::assertSame($student, $parent->getChildren()?->first());
    }

    /**
     * Provide supported request values and their normalized modes.
     *
     * @return array<string, array{string, string}>
     */
    public static function validModeProvider(): array
    {
        return [
            'staff' => ['staff', ViewMode::STAFF],
            'student' => ['student', ViewMode::STUDENT],
            'parent' => ['parent', ViewMode::PARENT],
        ];
    }

    /**
     * Create a user with the requested role type.
     *
     * @param int $id User ID.
     * @param string $roleType Role type.
     */
    private function createUser(int $id, string $roleType): User
    {
        return (new User())->setId($id)->setRole((new Role())->setType($roleType));
    }

    /**
     * Create an ExternalUser stub without invoking its dependency-spawning constructor.
     */
    private function createExternalUserStub(): ExternalUser
    {
        return $this->createStub(ExternalUser::class);
    }

    /**
     * Create an ExternalUser mock without invoking its dependency-spawning constructor.
     *
     * @return ExternalUser&MockObject
     */
    private function createExternalUserMock(): ExternalUser&MockObject
    {
        return $this->getMockBuilder(ExternalUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadParents'])
            ->getMock();
    }
}
