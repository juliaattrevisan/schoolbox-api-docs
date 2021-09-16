<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test;

use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResult;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResultInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use Schoolbox\User;

/**
 * User authenticator for the test platform.
 *
 * Simply authenticates whatever user is passed in the constructor: if no user
 * was passed, authentication fails.
 */
class UserAuthenticator implements UserAuthenticatorInterface
{
    /**
     * The user to authenticate.
     */
    private ?User $currentUser;

    /**
     * Constructor
     *
     * @param User|null $currentUser
     */
    public function __construct(?User $currentUser)
    {
        $this->currentUser = $currentUser;
    }

    /**
     * Authenticates whichever user was passed in the constructor.
     *
     * @param RegistrationInterface $registration
     * @param string                $loginHint
     * @return UserAuthenticationResultInterface
     */
    public function authenticate(RegistrationInterface $registration, string $loginHint): UserAuthenticationResultInterface
    {
        if ($this->currentUser) {
            return new UserAuthenticationResult(
                true,
                new UserIdentity(
                    (string)$this->currentUser->getId(),
                    $this->currentUser->getFullName(),
                    $this->currentUser->getEmail()
                )
            );
        }
        return new UserAuthenticationResult(false);
    }
}
