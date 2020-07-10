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
     * You have to be a staff member, of ir you're a student you must also have the accompanying
     * config var enabled
     *
     * @param User $user
     * @param bool $configVar
     * @return bool
     */
    public function isSatisfiedBy(User $user, bool $configVar): bool
    {
        return $user->getRole()->isStaff() || ($user->getRole()->isStudent() && $configVar);
    }
}
