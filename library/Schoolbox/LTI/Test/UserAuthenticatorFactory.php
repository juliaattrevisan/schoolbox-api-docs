<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test;

use Schoolbox\User;

/**
 * Factory for the user authenticator for the test platform and tool.
 */
class UserAuthenticatorFactory
{
    /**
     * Build the user authenticator.
     *
     * @param User|null $currentUser
     * @return UserAuthenticator
     */
    public function build(?User $currentUser): UserAuthenticator
    {
        return new UserAuthenticator($currentUser->getId() ? $currentUser : null);
    }
}
