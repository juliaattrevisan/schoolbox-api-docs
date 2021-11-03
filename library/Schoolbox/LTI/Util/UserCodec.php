<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Codec for Schoolbox user objects in an LTI context.
 *
 * An encoded user value may be used as:
 * * the login hint in an OIDC initiation request
 * * the "sub" claim in a JWT
 * * the value of the "userId" key in a score publish service request
 *
 * This value will need to be decoded back into a user object for usage within
 * Schoolbox.
 */
class UserCodec
{
    /**
     * User mapper: used for creating a user from an encoded value.
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
     * Encode the user into a string value.
     *
     * @param User $user
     * @return string
     */
    public function encode(User $user): string
    {
        return $user->getUsername();
    }

    /**
     * Decode the string back into a user.
     *
     * @param string $encoded
     * @return User|null
     */
    public function decode(string $encoded): ?User
    {
        return $this->userMapper->findByUsername($encoded);
    }
}
