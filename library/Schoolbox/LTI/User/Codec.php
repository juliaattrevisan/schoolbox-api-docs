<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\User;

use Alaress\Codec\ICodec;
use Schoolbox\User;
use Schoolbox\UserMapper;
use TypeError;

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
 *
 * TODO consider changing the format (encoded: FQDN, decoded: ID)
 *      this seems to be the standard for other types of claim
 */
class Codec implements ICodec
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
     * @param User|null $rawValue
     * @return string|null
     */
    public function encode($rawValue)
    {
        if (!isset($rawValue)) {
            return null;
        }
        if ($rawValue instanceof User) {
            return $rawValue->getUsername();
        }

        // The typehint implies this will never happen, but logically it can
        throw new TypeError(sprintf('$rawValue must be an instance of %s', User::class));
    }

    /**
     * Decode the string back into a user.
     *
     * @param string|null $encodedValue
     * @return User|null
     */
    public function decode($encodedValue)
    {
        return isset($encodedValue) ? $this->userMapper->findByUsername($encodedValue) : null;
    }
}
