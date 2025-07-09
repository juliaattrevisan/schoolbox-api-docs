<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Codec\Json\Base64;
use Alaress\Module\Module as BaseModule;
use Alaress\Redis\Cache\ItemPool;
use Alaress\Redis\Cache\KeyGenerator;
use Alaress\Twig\Loader\IChainLoader;
use DateInterval;
use League\OAuth2\Server;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;
use Psr\Http\Message\ServerRequestInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Oidc\CacheHelper;
use Schoolbox\Oidc\Server\RelyingParty\Mapper;
use Schoolbox\Oidc\Server\Repository\Scope;
use Schoolbox\Url\Factory;
use Schoolbox\UserMapper;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Module for OpenID Connect identity provision.
 */
class Module extends BaseModule
{
    protected $basename = 'oidcServer';

    /**
     * Register routes for:
     *
     * * authorizing access to a relying party to a Schoolbox identity
     * * exchanging an authorization code or client/secret pair for an access
     *   token
     */
    protected function registerRoutes()
    {
        // Routes:
        // authorization page: user either allows or denies RP access to SB data
        $loggedIn = new IsLoggedIn();
        $this->register(self::METHOD_GET, '/oidc/authorize', 'getAuthorization', $loggedIn);
        $this->register(self::METHOD_POST, '/oidc/authorize', 'postAuthorization', $loggedIn);

        // Allow/deny authorization request links:

        // access token: reply to RP with an access token
        $this->register(self::METHOD_GET, '/oidc/token', 'getAccessTokenRequest');
        $this->register(self::METHOD_POST, '/oidc/token', 'postAccessTokenRequest');

        // well-known URLs: JWKS, configuration
        $this->register(self::METHOD_GET, '/.well-known/openid-configuration', 'getOpenIdConfiguration');
        $this->register(self::METHOD_GET, '/.well-known/jwks.json', 'getJsonWebKeySet');
        $this->register(self::METHOD_GET, '/oidc/userinfo', 'getUserinfo');
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Controller
    {
        // Set up auth server and all of its dependencies
        $relyingPartyMapper = $this->getMapperFactory()->get(Mapper::class);
        $userMapper         = $this->getMapperFactory()->get(UserMapper::class);

        // Cache server-related data in storage:
        // auth codes, access tokens, etc. should be somewhat persistent
        $cache = new Psr16Cache(
            new RedisAdapter(
                $this->injector->get('redis.storage'),
                CacheHelper::BASE_SERVER_KEY . '.' . INSTANCE_ID,
            )
        );

        // Get stuff out of the request
        /** @var ServerRequestInterface $request */
        $request = $this->injector->get('httpRequest');

        // Set up various repositories
        $clientRepository        = new Repository\Client($relyingPartyMapper);
        $scopeRepository         = new Repository\Scope();

        $authTokenKeyGenerator   = new KeyGenerator(CacheHelper::AUTH_CODE_KEY);
        $nonceKeyGenerator       = new KeyGenerator(CacheHelper::NONCE_KEY);
        $authCodeRepository      = new Repository\AuthCode($cache, $authTokenKeyGenerator, $nonceKeyGenerator, $request);
        $accessTokenKeyGenerator = new KeyGenerator(CacheHelper::ACCESS_TOKEN_KEY);
        $accessTokenRepository   = new Repository\AccessToken($cache, $accessTokenKeyGenerator);
        $refreshTokenRepository  = new Repository\RefreshToken($cache, new KeyGenerator(CacheHelper::REFRESH_TOKEN_KEY));
        $idTokenRepository       = new Repository\Identity($userMapper, (new Factory($this->getConfig()))->buildFullyQualified());

        // Claim extractor: include Schoolbox's custom claims
        $claimExtractor = new ClaimExtractor(
            [
                new ClaimSetEntity(
                    Scope::SCOPE_SCHOOLBOX,
                    [
                        Scope::SCOPE_SCHOOLBOX . '/id',
                        Scope::SCOPE_SCHOOLBOX . '/instance_id',
                        Scope::SCOPE_SCHOOLBOX . '/external_id',
                        Scope::SCOPE_SCHOOLBOX . '/role_type',
                    ]
                ),
            ]
        );

        // The actual auth server
        $authServer = new Server\AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $this->getConfig()->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
            $this->getConfig()->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
            new Response\IdToken(
                $idTokenRepository,
                $claimExtractor,
                $request,
                $this->getConfig()->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
                Jwt::KEY_ID,
                $cache,
                $nonceKeyGenerator,
            ),
        );

        // Auth code grant:
        // auth codes expire after 10 minutes, refresh tokens after 1 month
        $authCodeGrant = new Grant\AuthCode(
            $authCodeRepository,
            $refreshTokenRepository,
            new DateInterval('PT10M'),
        );
        $authCodeGrant->setRefreshTokenTTL(new DateInterval('P1M'));
        $authServer->enableGrantType($authCodeGrant);

        // Refresh code grant: expires after 1 month
        $refreshCodeGrant = new Server\Grant\RefreshTokenGrant($refreshTokenRepository);
        $refreshCodeGrant->setRefreshTokenTTL(new DateInterval('P1M'));
        $authServer->enableGrantType($refreshCodeGrant);

        // Client credentials grant: access tokens expire after 1 hour
        $authServer->enableGrantType(
            new Server\Grant\ClientCredentialsGrant(),
            new DateInterval('PT1H'),
        );

        // Implicit grant: apparently deprecated, but is required according to:
        // https://openid.net/specs/openid-connect-discovery-1_0.html#ProviderMetadata
        // Nonetheless, access tokens expire after 1 hour
        $implicitGrant = new Grant\Implicit(new DateInterval('PT1H'), '?');
        $authServer->enableGrantType($implicitGrant);

        return new Controller(
            $authServer,
            $this->injector->get('lti.keyChains'),
            $this->injector->get('user.jwtParser'),
            $claimExtractor,
            $this->getMapperFactory()->get(RelyingParty\Mapper::class),
            $this->getMapperFactory()->get(RelyingParty\Grant\Mapper::class),
            $this->getMapperFactory()->get(RelyingParty\Grant\Scope\Mapper::class),
        );
    }
}
