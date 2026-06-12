<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

/**
 * Supported server-side grade viewing modes.
 */
enum ViewModeValue: string
{
    case STAFF = 'staff';
    case STUDENT = 'student';
    case PARENT = 'parent';
}
