<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Grades\ViewMode;

class ViewModeTest extends TestCase
{
    #[DataProvider('validModeProvider')]
    public function testFromRequestValueAcceptsValidModes(string $input, string $expected): void
    {
        self::assertSame($expected, ViewMode::fromRequestValue($input));
    }

    public function testFromRequestValueDefaultsInvalidValuesToStaff(): void
    {
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(null));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue(''));
        self::assertSame(ViewMode::STAFF, ViewMode::fromRequestValue('guardian'));
    }

    #[DataProvider('labelProvider')]
    public function testLabelDescribesMode(string $mode, string $expected): void
    {
        self::assertSame($expected, ViewMode::label($mode));
    }

    public static function validModeProvider(): array
    {
        return [
            'staff' => ['staff', ViewMode::STAFF],
            'student' => ['student', ViewMode::STUDENT],
            'parent' => ['parent', ViewMode::PARENT],
        ];
    }

    public static function labelProvider(): array
    {
        return [
            'staff' => [ViewMode::STAFF, 'staff'],
            'student' => [ViewMode::STUDENT, 'student'],
            'parent' => [ViewMode::PARENT, 'parent'],
            'invalid fallback' => ['guardian', 'staff'],
        ];
    }
}
