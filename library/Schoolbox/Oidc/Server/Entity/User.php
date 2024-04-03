<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use Schoolbox\User as UserModel;

/**
 * OIDC user entity, implementing a set of standard claims.
 */
class User implements UserEntityInterface, ClaimSetInterface
{
    /**
     * Constructor
     *
     * @param UserModel $user The user who is being identified.
     */
    public function __construct(private UserModel $user)
    {
    }

    /**
     * Return the user's identifier, in this case their username.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->user->getUsername();
    }

    /**
     * Return the user's claims:
     * * profile:                      standard profile claims
     * * email:                        standard email claims
     * * https://schoolbox.education/: Schoolbox-specific claims
     *
     * @return array
     */
    public function getClaims(): array
    {
        return [
            // Profile claims
            'name'               => $this->user->getFullName(),
            'given_name'         => $this->user->getFirstname(),
            'family_name'        => $this->user->getLastname(),
            'nickname'           => $this->user->getGivenName(),
            'preferred_username' => $this->user->getUsername(),

            // Email claims: assume email is verified
            'email'          => $this->user->getEmail(),
            'email_verified' => true,

            // Schoolbox-specific claims:
            // must be prefixed with something identifying them as Schoolbox
            'https://schoolbox.education/instance_id' => INSTANCE_ID,
            'https://schoolbox.education/external_id' => $this->user->getExternalId(),
            'https://schoolbox.education/role_type'   => $this->user->getRole()->getType(),
        ];
    }
}
