<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Session;

use Schoolbox\User;

/**
 * Consistent reusable logic for testing whether the current session is an impersonation
 */
class ImpersonationCheck
{
    /**
     * Session keys for storing the initial and previous users in the
     * impersonation chain.
     */
    public const SESSION_KEY_INITIAL_USER = 'initialUser';
    public const SESSION_KEY_PREVIOUS_USER = 'previousUser';

    /**
     * Returns whether the current session is an impersonation of another user.
     *
     * Merely switching to a linked user is not considered impersonation.
     *
     * @param User      $currentUser
     * @param User|null $previous
     * @return bool
     */
    public function isImpersonated(User $currentUser, ?User $previous): bool
    {
        if (!$previous) {
            return false;
        }

        // We need to distinguish between switching to a linked vs non-linked user
        foreach ($previous->getLinkedUsers() as $linkedUser) {
            if ((int)$linkedUser->getId() === (int)$currentUser->getId()) {
                // User has merely switched to a linked account, this does not constitute 'impersonation'
                return false;
            }
        }

        // A previous user exists, and current user is not linked to from them. Current user is being impersonated.
        return true;
    }
}
