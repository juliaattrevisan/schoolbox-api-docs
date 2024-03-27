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
    private const SCOPE_OPENID = 'openid';

    /**
     * Scope for basic details about the identity.
     */
    private const SCOPE_BASIC = 'basic';

    /**
     * Scope for the identity's email address.
     */
    private const SCOPE_EMAIL = 'email';

    /**
     * Scopes available to this repository.
     */
    private const AVAILABLE_SCOPES = [
        self::SCOPE_OPENID,
        self::SCOPE_BASIC,
        self::SCOPE_EMAIL,
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
        if (array_key_exists($identifier, self::AVAILABLE_SCOPES) === false) {
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
        $userIdentifier = null
    ): array {
        // For now: assume all scopes are acceptable for all users.
        return $scopes;
    }
}
