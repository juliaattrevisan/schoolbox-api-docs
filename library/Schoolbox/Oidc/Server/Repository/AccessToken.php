<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use Alaress\Redis\Cache\IKeyGenerator;
use Alaress_Date_Format;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Oidc\Server\Entity\AccessToken as AccessTokenEntity;

/**
 * Access token repository implementation: stores access tokens in a cache of
 * some sort.
 */
class AccessToken implements AccessTokenRepositoryInterface
{
    /**
     * Constructor
     *
     * @param CacheInterface $cache        The cache in which to put access tokens.
     * @param IKeyGenerator  $keyGenerator Generator for keys.
     */
    public function __construct(private CacheInterface $cache, private IKeyGenerator $keyGenerator)
    {
    }

    /**
     * Get a new token.
     *
     * @param ClientEntityInterface $clientEntity
     * @param array                 $scopes
     * @param int|null|string       $userIdentifier
     * @return AccessTokenEntityInterface
     */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null,
    ): AccessTokenEntityInterface {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        if (isset($userIdentifier)) {
            $accessToken->setUserIdentifier($userIdentifier);
        }
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        return $accessToken;
    }

    /**
     * Persist this token.
     *
     * @param AccessTokenEntityInterface $accessTokenEntity
     * @return void
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        // TODO figure out how to serialize an access token:
        //      may need to put this in DB after all.
        //      As with auth code, I'm not sure it even matters?
        //      It's never unserialized AFAICT
        $identifier = $accessTokenEntity->getIdentifier();
        $this->cache->set(
            $this->keyGenerator->generateKey($identifier),
            $identifier,
            Alaress_Date_Format::now()->getDateTimeObject()->diff($accessTokenEntity->getExpiryDateTime()),
        );
    }

    /**
     * Revoke this token.
     *
     * @param string $tokenId
     * @return void
     */
    public function revokeAccessToken($tokenId): void
    {
        $this->cache->delete($this->keyGenerator->generateKey($tokenId));
    }

    /**
     * is this token revoked?
     *
     * @param string $tokenId
     * @return bool
     */
    public function isAccessTokenRevoked($tokenId): bool
    {
        return !$this->cache->has($this->keyGenerator->generateKey($tokenId));
    }
}
