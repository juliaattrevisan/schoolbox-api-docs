<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use Alaress\Redis\Cache\IKeyGenerator;
use Alaress_Date_Format;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Oidc\Server\Entity\RefreshToken as RefreshTokenEntity;

/**
 * Repository for refresh tokens: stores refresh tokens in a cache of some sort.
 */
class RefreshToken implements RefreshTokenRepositoryInterface
{
    /**
     * Constructor
     *
     * @param CacheInterface $cache        The cache in which to put refresh tokens.
     * @param IKeyGenerator  $keyGenerator Generator for keys.
     */
    public function __construct(private CacheInterface $cache, private IKeyGenerator $keyGenerator)
    {
    }

    /**
     * Create a new refresh token.
     *
     * @return RefreshTokenEntityInterface
     */
    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    /**
     * Persist a refresh token.
     *
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     * @return void
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $identifier = $refreshTokenEntity->getIdentifier();

        // TODO figure out how to serialize a refresh token:
        //      may need to put this in DB after all.
        //      As with auth code, I'm not sure it even matters?
        //      It's never unserialized AFAICT
        $this->cache->set(
            $this->keyGenerator->generateKey($identifier),
            $identifier,
            Alaress_Date_Format::now()->getDateTimeObject()->diff($refreshTokenEntity->getExpiryDateTime()),
        );
    }

    /**
     * Revoke a refresh token.
     *
     * @param string $tokenId
     * @return void
     */
    public function revokeRefreshToken($tokenId): void
    {
        $this->cache->delete($this->keyGenerator->generateKey($tokenId));
    }

    /**
     * Is the refresh token revoked?
     *
     * @param string $tokenId
     * @return bool
     */
    public function isRefreshTokenRevoked($tokenId): bool
    {
        return !$this->cache->has($this->keyGenerator->generateKey($tokenId));
    }
}
