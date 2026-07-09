<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\User;

use Schoolbox\Role;

final class QualificationUpdateEligibility
{
    public static function isAllowedForRoleType(?string $roleType): bool
    {
        return $roleType === Role::STUDENT;
    }
}
