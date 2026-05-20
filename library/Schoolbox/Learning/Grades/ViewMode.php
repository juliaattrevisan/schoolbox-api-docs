<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    public static function fromRequestValue(?string $value): string
    {
        return in_array($value, self::values(), true) ? $value : self::STAFF;
    }

    public static function label(string $mode): string
    {
        return match (self::fromRequestValue($mode)) {
            self::STUDENT => 'student',
            self::PARENT => 'parent',
            default => 'staff',
        };
    }

    public static function values(): array
    {
        return [self::STAFF, self::STUDENT, self::PARENT];
    }
}
