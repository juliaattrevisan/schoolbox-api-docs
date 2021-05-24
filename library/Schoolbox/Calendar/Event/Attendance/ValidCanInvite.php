<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Schoolbox\User;

/**
 * Class ValidCanInvite
 *
 * Decide whether $user is allowed to invite other users
 *
 * @package Schoolbox\Calendar\Event\Attendance
 */
class ValidCanInvite
{

    /**
     * Decide whether $user is allowed to invite other users
     *
     * Superusers can always add attendees
     *
     * Otherwise, you have to be a staff member,
     * or if you're a student you must also have the accompanying config var enabled
     *
     * Parents and Guests can never add attendees
     *
     * @param User $user
     * @param bool $configVar
     * @return bool
     */
    public function isSatisfiedBy(User $user, bool $configVar): bool
    {
        return $user->isSuperuser() || $user->getRole()->isStaff() || ($user->getRole()->isStudent() && $configVar);
    }
}
