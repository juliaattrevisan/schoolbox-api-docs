<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Repository;

use Alaress\Redis\Cache\IKeyGenerator;
use Alaress_Date_Format;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Oidc\Server\Entity\AuthCode as AuthCodeEntity;

/**
 * Auth code repository implementation: stores auth codes in a cache of some
 * sort.
 *
 * Auth codes will be exchanged for access tokens.
 */
class AuthCode implements AuthCodeRepositoryInterface
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
     * Get a new auth code.
     *
     * @return AuthCodeEntityInterface
     */
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity(base64_encode(random_bytes(80)));
    }

    /**
     * Persist this auth code.
     *
     * @param AuthCodeEntityInterface $authCodeEntity
     * @return void
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $identifier = $authCodeEntity->getIdentifier();

        // TODO figure out how to serialize an auth code gets serialized
        $this->cache->set(
            $this->keyGenerator->generateKey($identifier),
            $authCodeEntity->__toString(),
            $authCodeEntity->getExpiryDateTime()->diff(
                Alaress_Date_Format::now()->getDateTimeObject()
            ),
        );
    }

    /**
     * Revoke an auth code.
     *
     * @param string $codeId
     * @return void
     */
    public function revokeAuthCode($codeId): void
    {
        $this->cache->delete($this->keyGenerator->generateKey($codeId));
    }

    /**
     * Is this auth code revoked?
     *
     * @param string $codeId
     * @return bool
     */
    public function isAuthCodeRevoked($codeId): bool
    {
        return !$this->cache->has($this->keyGenerator->generateKey($codeId));
    }
}
