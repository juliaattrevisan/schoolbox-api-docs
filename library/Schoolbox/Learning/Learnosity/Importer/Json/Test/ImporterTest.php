<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Json\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Learnosity\Importer\Json\Importer;

class ImporterTest extends TestCase
{
    /**
     * Tests current string mark visibility values pass through unchanged.
     */
    public function testResolveMarkVisibilityPassesThroughCurrentStringValues(): void
    {
        $this->assertSame(IAssessable::MARK_VISIBILITY_PRIVATE, Importer::resolveMarkVisibility(IAssessable::MARK_VISIBILITY_PRIVATE));
        $this->assertSame(IAssessable::MARK_VISIBILITY_STUDENT, Importer::resolveMarkVisibility(IAssessable::MARK_VISIBILITY_STUDENT));
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT, Importer::resolveMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT));
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, Importer::resolveMarkVisibility(IAssessable::MARK_VISIBILITY_PARENT_STUDENT));
    }

    /**
     * Tests legacy integer zero maps to staff-only visibility.
     */
    public function testResolveMarkVisibilityMapsLegacyIntegerZeroToPrivate(): void
    {
        $this->assertSame(IAssessable::MARK_VISIBILITY_PRIVATE, Importer::resolveMarkVisibility(0));
    }

    /**
     * Tests legacy integer one maps to staff-and-student visibility.
     */
    public function testResolveMarkVisibilityMapsLegacyIntegerOneToStudent(): void
    {
        $this->assertSame(IAssessable::MARK_VISIBILITY_STUDENT, Importer::resolveMarkVisibility(1));
    }

    /**
     * Tests legacy integer two maps to staff-student-and-parent visibility.
     */
    public function testResolveMarkVisibilityMapsLegacyIntegerTwoToParentStudent(): void
    {
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, Importer::resolveMarkVisibility(2));
    }
}
