<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Codec\Json\Base64;
use Alaress\Redis\Cache\ItemPool;
use Alaress\Redis\Cache\KeyGenerator;
use Alaress\Web\Controller\Base;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use DateInterval;
use League\OAuth2\Server;
use Schoolbox\Config\ConfigMapper;

/**
 * Controller for OpenID Connect identity provision.
 *
 * This implements Schoolbox as an identity provider (e.g. a server) for an
 * OpenID relying party.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RelyingParty\Mapper $mapper The mapper used for fetching the relying party which is authenticating.
     */
    public function __construct(private RelyingParty\Mapper $mapper)
    {
    }

    /**
     * Render an authorization page for an OIDC authorization code flow.
     *
     * This displays a form which queries whether the user authorizes the
     * relying party to access the data it requests.
     *
     * @api GET /oidc/authorize
     * @since 24.0
     *
     * @return void
     */
    public function getAuthorization(): void
    {
        $cache = new SimpleCacheBridge(
            new ItemPool(
                $this->getDi()->get('redis.cache'),
                new Base64(),
                new KeyGenerator('oauth', INSTANCE_ID),
            )
        );

        $clientRepository       = new Repository\Client($this->mapper);
        $scopeRepository        = new Repository\Scope();
        $accessTokenRepository  = new Repository\AccessToken($cache, new KeyGenerator('accessToken'));
        $authCodeRepository     = new Repository\AuthCode($cache, new KeyGenerator('authCode'));
        $refreshTokenRepository = new Repository\RefreshToken($cache, new KeyGenerator('refreshToken'));

        // generate using base64_encode(random_bytes(32))
        $encryptionKey = 'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen';
        $authServer    = new Server\AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $this->getConfigLoader()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
            $encryptionKey,
        );

        // Auth code grant:
        // auth codes expire after 10 minutes, refresh tokens after 1 month
        $authCodeGrant = new Server\Grant\AuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new DateInterval('PT10M'),
        );
        $authCodeGrant->setRefreshTokenTTL(new DateInterval('P1M'));
        $authServer->enableGrantType($authCodeGrant);

        // Client credentials grant: access tokens expire after 1 hour
        $authServer->enableGrantType(
            new Server\Grant\ClientCredentialsGrant(),
            new DateInterval('PT1H'),
        );

        $authRequest = $authServer->validateAuthorizationRequest($this->request);
        $authRequest->setUser(new Entity\User($this->getUser()));

        // Display confirm auth page:
        // this should be bypassed if the user has already given permission
        // for this client.
        $this->response = $this->responseFromTemplateUnthemed('@oidcServer/authorization.twig', []);
    }

    /**
     * @see getAuthorization
     */
    public function postAuthorization(): void
    {
        $this->getAuthorization();
    }
}
