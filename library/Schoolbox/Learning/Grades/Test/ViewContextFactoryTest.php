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

class ViewContextFactoryTest extends TestCase
{
    public function testStaffModeUsesCurrentUserAndAllowsInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STAFF);

        self::assertSame(ViewMode::STAFF, $context->getMode());
        self::assertSame($currentUser, $context->getViewer());
        self::assertTrue($context->canViewInstructorMarks());
    }

    public function testStudentModeUsesViewedStudentAndHidesInstructorMarks(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::STUDENT);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($student, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    public function testParentModeUsesLoadedParentWhenAvailable(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT);
        $parent = $this->user(3, Role::PARENT);
        $parent->setChildren((new GenericCollection(User::class))->setOffset($student->getId(), $student));
        $student->setParents((new GenericCollection(User::class))->setOffset($parent->getId(), $parent));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertSame($parent, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    public function testParentModeCreatesFallbackParentWhenNoRealParentExists(): void
    {
        $currentUser = $this->user(1, Role::STAFF);
        $student = $this->user(2, Role::STUDENT)->setParents(new GenericCollection(User::class));

        $context = (new ViewContextFactory($this->externalUser()))->create($currentUser, $student, ViewMode::PARENT);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertTrue($context->getViewer()->getRole()->isParent());
        self::assertTrue($context->getViewer()->isParentOf($student));
    }

    public function testStudentRequesterIsForcedToStudentMode(): void
    {
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($student, $student, ViewMode::STAFF);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($student, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    public function testParentRequesterIsForcedToParentModeUsingCurrentParent(): void
    {
        $student = $this->user(2, Role::STUDENT);
        $parent = $this->user(3, Role::PARENT);
        $parent->setChildren((new GenericCollection(User::class))->setOffset($student->getId(), $student));

        $context = (new ViewContextFactory($this->externalUser()))->create($parent, $student, ViewMode::STAFF);

        self::assertSame(ViewMode::PARENT, $context->getMode());
        self::assertSame($parent, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    public function testSuperuserCanUseRequestedViewMode(): void
    {
        $superuser = $this->user(1, Role::STAFF)->setSuperuser(true);
        $student = $this->user(2, Role::STUDENT);

        $context = (new ViewContextFactory($this->externalUser()))->create($superuser, $student, ViewMode::STUDENT);

        self::assertSame(ViewMode::STUDENT, $context->getMode());
        self::assertSame($student, $context->getViewer());
        self::assertFalse($context->canViewInstructorMarks());
    }

    private function user(int $id, string $roleType): User
    {
        return (new User())
            ->setId($id)
            ->setRole((new Role())->setType($roleType));
    }

    private function externalUser(): ExternalUser
    {
        return $this->createStub(ExternalUser::class);
    }
}
