<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Feedback\Visibility;
use Schoolbox\Learning\Assessment\IAssessable;

class VisibilityTest extends TestCase
{
    public function testOnlyPrivateWhenBothFalse(): void
    {
        $result = Visibility::getAvailableVisibilities(false, false);
        $this->assertSame([IAssessable::MARK_VISIBILITY_PRIVATE], $result);
    }

    public function testStudentOptionWhenAllowStudents(): void
    {
        $result = Visibility::getAvailableVisibilities(true, false);
        $this->assertContains(IAssessable::MARK_VISIBILITY_STUDENT, $result);
        $this->assertNotContains(IAssessable::MARK_VISIBILITY_PARENT, $result);
        $this->assertNotContains(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, $result);
    }

    public function testParentOptionWhenAllowParents(): void
    {
        $result = Visibility::getAvailableVisibilities(false, true);
        $this->assertContains(IAssessable::MARK_VISIBILITY_PARENT, $result);
        $this->assertNotContains(IAssessable::MARK_VISIBILITY_STUDENT, $result);
        $this->assertNotContains(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, $result);
    }

    public function testAllFourOptionsWhenBothTrue(): void
    {
        $result = Visibility::getAvailableVisibilities(true, true);
        $this->assertContains(IAssessable::MARK_VISIBILITY_PRIVATE, $result);
        $this->assertContains(IAssessable::MARK_VISIBILITY_STUDENT, $result);
        $this->assertContains(IAssessable::MARK_VISIBILITY_PARENT, $result);
        $this->assertContains(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, $result);
        $this->assertCount(4, $result);
    }

    public function testGetMarkVisibilityDescription(): void
    {
        $this->assertSame(
            'Staff Only',
            Visibility::getMarkVisibilityDescription(IAssessable::MARK_VISIBILITY_PRIVATE)
        );
        $this->assertSame(
            'Staff & Student',
            Visibility::getMarkVisibilityDescription(IAssessable::MARK_VISIBILITY_STUDENT)
        );
        $this->assertSame(
            'Staff & Parent',
            Visibility::getMarkVisibilityDescription(IAssessable::MARK_VISIBILITY_PARENT)
        );
        $this->assertSame(
            'Staff, Student & Parent',
            Visibility::getMarkVisibilityDescription(IAssessable::MARK_VISIBILITY_PARENT_STUDENT)
        );
        $this->assertSame('', Visibility::getMarkVisibilityDescription('unexpected'));
    }
}
