<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT as JwtLibrary;
use GuzzleHttp\Client;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Oidc\CacheHelper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;

/**
 * Parser for configured oAuth provider-issued JWTs.
 */
class OauthProviderIssued extends Base
{
    /**
     * Default session length, in seconds: used as the default leeway for the
     * JWT verifier.
     */
    private const SESSION_LENGTH_DEFAULT = 1800;

    /**
     * The signing algorithm to assume that the external provider uses, if it is
     * not provided in the key.
     */
    private const DEFAULT_SIGNING_ALGORITHM = 'RS256';

    /**
     * Constructor
     *
     * @param UserMapper     $userMapper Mapper for retrieving users once the user in the JWT is identified.
     * @param IConfigLoader  $config     Standard configuration store.
     * @param Client         $httpClient HTTP client: required for retrieving JWKS from oAuth provider
     * @param CacheInterface $oidcCache  Required for caching discovery response and keys
     */
    public function __construct(
        UserMapper $userMapper,
        IConfigLoader $config,
        private Client $httpClient,
        private CacheInterface $oidcCache,
    ) {
        parent::__construct($userMapper, $config);
    }

    /**
     * @inheritdoc
     */
    public function accepts(#[SensitiveParameter] string $jwt): bool
    {
        // This JWT could be from either an oAuth provider, or from an LTI tool:
        // Check the token's audience to determine if it is actually from the
        // oAuth provider.
        $claims = $this->decodeWithoutVerification($jwt);
        if (!$this->claimAudienceMatchesConfiguredClientId($claims)) {
            return false;
        }

        // If no issuer is provided: always return true
        // TODO remove this in 23.0: issuer must be provided, and match JWT
        $oauthIssuer = trim((string)($this->config->get(ConfigMapper::NAME_OAUTH_ISSUER) ?? ''));
        if ($oauthIssuer === '') {
            return true;
        }

        // If the issuer is provided: it must match the issuer in the JWT
        $jwtIssuer = trim((string)($claims[self::CLAIM_ISS] ?? ''));
        return $jwtIssuer === $oauthIssuer;
    }

    /**
     * @inheritdoc
     */
    public function determineUser(#[SensitiveParameter] string $jwt): User
    {
        // Determine JWKS for this provider
        $oauthIdp        = $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER);
        $candidateClaims = explode(',', $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS));

        $cache = new CacheHelper($this->httpClient, $this->oidcCache);
        $jwks  = $cache->retrieveJsonWebKeySet(new Uri($oauthIdp));

        // Verify the JWT
        $sessionLength = (int)$this->config->get('session_length');
        if ($sessionLength <= 0) {
            $sessionLength = self::SESSION_LENGTH_DEFAULT;
        }
        $claims = $this->decode(
            $jwt,
            JwtLibrary\JWK::parseKeySet($jwks, self::DEFAULT_SIGNING_ALGORITHM),
            $sessionLength,
        );

        if (!$this->claimAudienceMatchesConfiguredClientId($claims)) {
            throw new Exception\Rejected(
                sprintf('JWT "%s" claim does not match configured client ID', self::CLAIM_AUD)
            );
        }

        // The identification in the token seems to be in one of two claims:
        // * "preferred_username" matches a Schoolbox username
        // * "https://cloudwork.id/claims/sis_id" matches an external ID
        // We'll just use the preferred username until I hear otherwise.
        $messages = [];
        foreach ($candidateClaims as $candidateClaim) {
            $candidateClaim = trim($candidateClaim);
            if (isset($claims[$candidateClaim])) {
                try {
                    return $this->findUser($claims[$candidateClaim], $candidateClaim);
                } catch (Exception\InvalidIdentity $e) {
                    $failureMessage = $e->getMessage();
                }
            } else {
                $failureMessage = sprintf(
                    'JWT "%s" claim is empty',
                    $candidateClaim
                );
            }
            $messages[] = $failureMessage;
        }

        // Candidate claims found no user: can't use it
        throw new Exception\InvalidIdentity(
            sprintf('JWT contains no claim matching a user (%s)', implode('; ', $messages))
        );
    }

    /**
     * Does this token's audience match the oAuth client ID?
     *
     * @param array $claims The claims from the token.
     * @return bool
     */
    private function claimAudienceMatchesConfiguredClientId(array $claims): bool
    {
        $audiences     = (array)($claims[self::CLAIM_AUD] ?? []);
        $oauthClientId = $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
        return in_array($oauthClientId, $audiences, true);
    }
}
