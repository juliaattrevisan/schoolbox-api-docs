<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Auth;

use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResult;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResultInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use Schoolbox\UserMapper;

/**
 * User authenticator for the Schoolbox platform.
 *
 * Currently only authenticates whatever user is passed in the login hint: if
 * no user was passed, authentication fails.
 */
class UserAuthenticator implements UserAuthenticatorInterface
{
    /**
     * The user mapper, used to find the authenticated user.
     */
    private UserMapper $userMapper;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Authenticates whichever user matches `$loginHint`.
     *
     * @param RegistrationInterface $registration
     * @param string                $loginHint
     * @return UserAuthenticationResultInterface
     */
    public function authenticate(RegistrationInterface $registration, string $loginHint): UserAuthenticationResultInterface
    {
        if ($loginHint === '') {
            return new UserAuthenticationResult(false);
        }

        $user = $this->userMapper->findByUsername($loginHint);
        if ($user) {
            return new UserAuthenticationResult(
                true,
                new UserIdentity(
                    $user->getUsername(),
                    $user->getFullName(),
                    $user->getEmail()
                )
            );
        }

        return new UserAuthenticationResult(false);
    }
}
