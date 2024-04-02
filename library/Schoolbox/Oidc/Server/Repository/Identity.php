<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use Schoolbox\Oidc\Server\Entity\User as UserEntity;
use Schoolbox\UserMapper;

/**
 * Repository for user identities.
 */
class Identity implements IdentityProviderInterface
{
    /**
     * Constructor
     *
     * @param UserMapper $userMapper Required for retrieving a user's identity.
     */
    public function __construct(private UserMapper $userMapper)
    {
    }

    /**
     * Retrieves an entity for this user, given their identifier.
     *
     * @param string $identifier
     * @return UserEntity
     */
    public function getUserEntityByIdentifier($identifier): UserEntity
    {
        return new UserEntity($this->userMapper->findByUsername($identifier));
    }
}
