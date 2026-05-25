<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

/**
 * Defines the supported server-side grade viewing modes.
 */
final class ViewMode
{
    public const string STAFF = 'staff';
    public const string STUDENT = 'student';
    public const string PARENT = 'parent';

    /**
     * Return a supported view mode for a request value, defaulting to staff mode for invalid values.
     */
    public static function fromRequestValue(?string $value): string
    {
        return in_array($value, self::values(), true) ? $value : self::STAFF;
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
}
