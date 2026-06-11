<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Grades\ViewMode;

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
        self::assertFalse(ViewMode::progressTicksReadOnly('', false));
        self::assertFalse(ViewMode::progressTicksReadOnly(ViewMode::STAFF, false));
        self::assertTrue(ViewMode::progressTicksReadOnly(ViewMode::STUDENT, false));
        self::assertTrue(ViewMode::progressTicksReadOnly(ViewMode::PARENT, false));
    }

    /**
     * Actual parent users see read-only progress ticks.
     */
    public function testParentViewerProgressTicksAreReadOnly(): void
    {
        self::assertTrue(ViewMode::progressTicksReadOnly('', true));
        self::assertTrue(ViewMode::progressTicksReadOnly(ViewMode::STAFF, true));
    }

    /**
     * Comments are read-only only when staff are previewing as another effective viewer.
     */
    public function testCommentsAreReadOnlyOnlyWhenViewingAsAnotherUser(): void
    {
        self::assertFalse(ViewMode::commentsReadOnly(123, 123));
        self::assertFalse(ViewMode::commentsReadOnly(456, 456));
        self::assertTrue(ViewMode::commentsReadOnly(123, 456));
    }

    /**
     * Instructor tick transitions follow the effective viewer's folder access in interactive modes.
     */
    public function testTickTransitionsUseEffectiveViewerAccess(): void
    {
        self::assertSame([
            'incomplete'      => 'teacherAssessed',
            'selfAssessed'    => 'teacherAssessed',
            'teacherAssessed' => 'incomplete',
        ], ViewMode::tickTransitions(true));

        self::assertSame([
            'incomplete'      => 'selfAssessed',
            'selfAssessed'    => 'incomplete',
            'teacherAssessed' => 'incomplete',
        ], ViewMode::tickTransitions(false));
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
}
