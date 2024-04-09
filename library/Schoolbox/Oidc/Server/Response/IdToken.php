<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Response;

use Alaress\Redis\Cache\IKeyGenerator;
use Lcobucci\JWT\Builder;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\IdTokenResponse;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\API\Token\Jwt;

/**
 * Customised `\OpenIDConnectServer\IdTokenResponse` to what an OpenID Connect
 * ID token should actually support.
 *
 * Overrides `getBuilder` to add the following ID token claims:
 * * "kid" (key ID) contains the key ID in the JSON web key set which the client
 *    uses to decrypt the token
 * * "nonce" contains whatever nonce was passed in the original auth code
 *    request: in order to retrieve this, the nonce must be stored in the cache
 *    alongside the auth code.
 */
class IdToken extends IdTokenResponse
{
    /**
     * This response needs to decrypt the original auth code.
     */
    use CryptTrait;

    /**
     * Constructor
     *
     * @param IdentityProviderInterface $identityProvider  Used to determine the identity (i.e. Schoolbox user)
     *                                                     associated with the access token.
     * @param ClaimExtractor            $claimExtractor    Used to extract claims out of the identity.
     * @param ServerRequestInterface    $request           The current request: required for extracting the auth code
     *                                                     and thus its nonce.
     * @param string                    $privateKey        The key which decrypts the auth code passed in the request:
     *                                                     required for extracting the nonce out of the key.
     * @param string                    $keyId             The key ID the client will use to decrypt the token:
     * @param CacheInterface            $cache             The cache which stores the nonce values for auth codes.
     * @param IKeyGenerator             $nonceKeyGenerator Generator for cache keys for the nonce value.
     */
    public function __construct(
        IdentityProviderInterface $identityProvider,
        ClaimExtractor $claimExtractor,
        private ServerRequestInterface $request,
        string $privateKey,
        private string $keyId,
        private CacheInterface $cache,
        private IKeyGenerator $nonceKeyGenerator,
    ) {
        parent::__construct($identityProvider, $claimExtractor);
        $this->encryptionKey = $privateKey;
    }

    /**
     * Adds required claims to the builder:
     * * "kid" for the key ID used to decrypt the token
     * * "nonce" for the nonce passed in the auth code request
     *
     * @param AccessTokenEntityInterface $accessToken
     * @param UserEntityInterface        $userEntity
     * @return Builder
     */
    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity): Builder
    {
        $builder = parent::getBuilder($accessToken, $userEntity)
            ->withHeader('kid', $this->keyId);

        // Need to extract auth code out of request, and find its nonce.
        // The `code` query string or POST value will contain the auth code:
        // need to now decrypt the code.
        $authCode = $this->request->getParsedBody()['code'] ?? null;
        if (isset($authCode)) {
            $decryptedCode = json_decode($this->decrypt($authCode), true);
            $authCodeId    = $decryptedCode['auth_code_id'];

            // Get the nonce out of the cache,
            // and add it to the ID token if it is there
            $nonceKey = $this->nonceKeyGenerator->generateKey($authCodeId);
            $nonce    = $this->cache->get($nonceKey);
            if (isset($nonce)) {
                $builder = $builder->withClaim('nonce', $nonce);
            }
        }

        return $builder;
    }
}
