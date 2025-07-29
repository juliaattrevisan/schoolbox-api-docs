<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Schoolbox\Oidc\Server\Entity\Scope as ScopeEntity;

/**
 * Repository for scopes this oAuth implementation supports.
 */
class Scope implements ScopeRepositoryInterface
{
    /**
     * Scope for OpenID Connect support.
     */
    public const SCOPE_OPENID = 'openid';

    /**
     * Scope for profile details about the identity.
     */
    public const SCOPE_PROFILE = 'profile';

    /**
     * Scope for the identity's email address.
     */
    public const SCOPE_EMAIL = 'email';

    /**
     * Scope for Schoolbox details: instance ID, role type and external ID.
     */
    public const SCOPE_SCHOOLBOX = 'https://schoolbox.education';

    /**
     * Scopes available to this repository.
     */
    public const AVAILABLE_SCOPES = [
        self::SCOPE_OPENID,
        self::SCOPE_PROFILE,
        self::SCOPE_EMAIL,
        self::SCOPE_SCHOOLBOX,
    ];

    /**
     * If this scope is allowed by this repository, return an entity for that
     * scope; otherwise return null.
     *
     * @param string $identifier The requested scope, as a string.
     * @return ScopeEntityInterface|null
     */
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        if (!in_array($identifier, self::AVAILABLE_SCOPES)) {
            return null;
        }

        return new ScopeEntity($identifier);
    }

    /**
     * Filter `$scopes` to the scopes allowed to this grant type and client.
     *
     * @param array<ScopeEntityInterface> $scopes         The set of requested scopes.
     * @param string                      $grantType      The grant type which is requesting the scope.
     * @param ClientEntityInterface       $clientEntity   The client requesting the scope.
     * @param string|null                 $userIdentifier The user identifier requesting the scope, if any.
     * @return array<ScopeEntityInterface>
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        // For now: assume all scopes are acceptable for all users.
        return $scopes;
    }
}
