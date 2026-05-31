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
     * Invalid request values fall back to staff mode for staff-controlled preview selection.
     */
    public function testFromRequestValueDefaultsInvalidValuesToStaff(): void
    {
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(null));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(''));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue('guardian'));
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
