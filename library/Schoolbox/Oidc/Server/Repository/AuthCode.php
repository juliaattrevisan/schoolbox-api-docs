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
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
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
     * @param CacheInterface         $cache                The cache in which to put auth codes and nonce values.
     * @param IKeyGenerator          $authCodeKeyGenerator Generator for cache keys for the auth code.
     * @param IKeyGenerator          $nonceKeyGenerator    Generator for cache keys for the nonce value.
     * @param ServerRequestInterface $request              The current request: required for extracting the nonce value
     *                                                     for storing in the cache.
     */
    public function __construct(
        private CacheInterface $cache,
        private IKeyGenerator $authCodeKeyGenerator,
        private IKeyGenerator $nonceKeyGenerator,
        private ServerRequestInterface $request,
    ) {
    }

    /**
     * Get a new auth code.
     *
     * @return AuthCodeEntityInterface
     */
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    /**
     * Persist this auth code.
     *
     * Also store the nonce: if it already exists in the cache though, raise an
     * error.
     *
     * @param AuthCodeEntityInterface $authCodeEntity
     * @return void
     * @throws UniqueTokenIdentifierConstraintViolationException if either the auth code's identifier or the nonce in
     *                                                           the request have been reused: this suggests the auth
     *                                                           code request has been replayed.
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $identifier  = $authCodeEntity->getIdentifier();
        $authCodeKey = $this->authCodeKeyGenerator->generateKey($identifier);
        if ($this->cache->has($authCodeKey)) {
            throw new UniqueTokenIdentifierConstraintViolationException(
                'Auth code has already been used, and may not be reused',
                0,
                null,
                400,
            );
        }

        // TODO figure out how to serialize an auth code gets serialized:
        //      may need to put this in DB after all.
        //      Actually, does it even matter? It's never unserialized AFAICT
        $this->cache->set(
            $authCodeKey,
            $identifier,
            Alaress_Date_Format::now()->getDateTimeObject()->diff($authCodeEntity->getExpiryDateTime()),
        );

        $nonce = $this->request->getQueryParams()['nonce'] ?? null;
        if (isset($nonce)) {
            $nonceKey = $this->nonceKeyGenerator->generateKey($identifier);
            if ($this->cache->has($nonceKey)) {
                // raise error
                throw new UniqueTokenIdentifierConstraintViolationException(
                    'Nonce value has already been used, and may not be reused',
                    0,
                    null,
                    400,
                );
            }
            $this->cache->set($nonceKey, $nonce);
        }
    }

    /**
     * Revoke an auth code.
     *
     * @param string $codeId
     * @return void
     */
    public function revokeAuthCode($codeId): void
    {
        $this->cache->delete($this->authCodeKeyGenerator->generateKey($codeId));
    }

    /**
     * Is this auth code revoked?
     *
     * @param string $codeId
     * @return bool
     */
    public function isAuthCodeRevoked($codeId): bool
    {
        return !$this->cache->has($this->authCodeKeyGenerator->generateKey($codeId));
    }
}
