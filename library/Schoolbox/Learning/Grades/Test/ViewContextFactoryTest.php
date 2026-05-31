<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Grades\ViewContextFactory;
use Schoolbox\Learning\Grades\ViewMode;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests effective viewer selection for grades view modes.
 */
class ViewContextFactoryTest extends TestCase
{
    /**
     * Staff mode uses the current staff user and includes instructor-only marks.
     */
    public function testStaffModeUsesCurrentUserAndAllowsInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STAFF);

        self::assertSame(ViewMode::STAFF, $context->getMode());
        self::assertSame($currentUser, $context->getViewer());
        self::assertTrue($context->canViewInstructorMarks());
    }

    /**
     * Non-staff student requests default to student mode rather than staff preview mode.
     */
    public function testNonStaffRequesterDefaultsToStudentMode(): void
    {
        $currentUser = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $currentUser, null);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($currentUser, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    /**
     * Non-staff student requests cannot force staff preview mode.
     */
    public function testNonStaffRequesterCannotSelectStaffMode(): void
    {
        $currentUser = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $currentUser, ViewMode::STAFF);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($currentUser, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    /**
     * Student preview mode uses the viewed student and hides instructor-only marks.
     */
    public function testStudentModeUsesViewedStudentAndHidesInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STUDENT);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($student, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    /**
     * Parent preview mode uses a real loaded parent when one exists and hydrates that parent's children.
     */
    public function testParentModeUsesLoadedParentWhenAvailable(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);
        $parent = $this->user(3, Role::PARENT);
        $student->setParents((new GenericCollection(User::class))->setOffset($parent->getId(), $parent));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertSame($parent, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
        self::assertSame($student, $context->getViewer()->getChildren()->getOffset($student->getId()));
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    /**
     * Parent preview mode creates a synthetic parent when the student has no real parent.
     */
    public function testParentModeCreatesFallbackParentWhenNoRealParentExists(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT)->setParents(new GenericCollection(User::class));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertTrue($context->getViewer()->getRole()->isParent());
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    /**
     * Create a minimal user for view context tests.
     *
     * @param int $id User identifier to assign.
     * @param string $roleType Role type to assign.
     */
    private function user(int $id, string $roleType): User
    {
        return (new User())
            ->setId($id)
            ->setRole((new Role())->setType($roleType));
    }

    /**
     * Create an external user service stub for tests that do not need SIS hydration.
     */
    private function externalUser(): ExternalUser
    {
        return $this->createStub(ExternalUser::class);
    }
}
