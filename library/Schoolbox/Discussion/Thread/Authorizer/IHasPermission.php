<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\User;

/**
 * Strategies for validating whether a thread:
 * * is associated with a particular type of item
 * * if associated, whether the current user has permission to act upon it
 */
interface IHasPermission
{
    /**
     * Does the given user have permission to perform the action, based on the
     * object that is associated with the thread?
     *
     * @param IThreadLocation  $object The object that is related to the thread
     * @param User|null $user
     * @return bool
     */
    public function hasPermission(IThreadLocation $object, ?User $user): bool;
}
