<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT as JwtLibrary;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use Psr\Http\Client\ClientInterface;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Parser for configured oAuth provider-issued JWTs.
 */
class OauthProviderIssued implements IParser
{
    /**
     * Default session length, in seconds: used as the default leeway for the
     * JWT verifier.
     */
    private const SESSION_LENGTH_DEFAULT = 1800;

    /**
     * Constructor
     *
     * @param UserMapper      $userMapper Mapper for retrieving users once the user in the JWT is identified.
     * @param IConfigLoader   $config     Standard configuration store.
     * @param ClientInterface $httpClient HTTP client: required for retrieving JWKS from oAuth provider
     */
    public function __construct(
        private UserMapper $userMapper,
        private IConfigLoader $config,
        private ClientInterface $httpClient,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function accepts(array $claims): bool
    {
        $jwtIssuer   = $claims[MessagePayloadInterface::CLAIM_ISS] ?? null;
        $oauthIssuer = $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER);
        return isset($jwtIssuer, $oauthIssuer)
            && trim($jwtIssuer, '/') === trim($oauthIssuer, '/');
    }

    /**
     * @inheritdoc
     */
    public function determineUser(string $jwt, array $claims): User
    {
        // Determine JWKS for this provider
        $oauthIdp        = $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER);
        $candidateClaims = explode(',', $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS));

        // TODO allow these to be configurable
        $oidcConfigUri = $oauthIdp . '/.well-known/openid-configuration';
        $algs          = ['RS256'];

        $oidcConfigRequest = $this->httpClient->get($oidcConfigUri);
        $oidcConfig        = json_decode($oidcConfigRequest->getBody()->getContents(), true);
        $jwksRequest       = $this->httpClient->get($oidcConfig['jwks_uri']);
        $jwks              = json_decode($jwksRequest->getBody()->getContents(), true);

        // Verify the JWT
        $sessionLength  = (int)$this->config->get('session_length');
        if ($sessionLength <= 0) {
            $sessionLength = self::SESSION_LENGTH_DEFAULT;
        }
        $originalLeeway = JwtLibrary\JWT::$leeway;
        JwtLibrary\JWT::$leeway = $sessionLength;
        try {
            $decodedToken = JwtLibrary\JWT::decode(
                $jwt,
                JwtLibrary\JWK::parseKeySet($jwks),
                $algs,
            );
        } finally {
            JwtLibrary\JWT::$leeway = $originalLeeway;
        }

        // Validate audience
        $validAudience = false;
        $audiences     = (array)$decodedToken->aud;
        $oauthClientId = $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID);
        foreach ($audiences as $audience) {
            if ($audience === $oauthClientId) {
                $validAudience = true;
                break;
            }
        }
        if (!$validAudience) {
            throw new JwtLibrary\SignatureInvalidException('JWT audience does not match configured client ID');
        }

        // The identification in the token seems to be in one of two claims:
        // * "preferred_username" matches a Schoolbox username
        // * "https://cloudwork.id/claims/sis_id" matches an external ID
        // We'll just use the preferred username until I hear otherwise.
        $messages = [];
        foreach ($candidateClaims as $candidateClaim) {
            $candidateClaim = trim($candidateClaim);
            if (isset($decodedToken->$candidateClaim)) {
                $user = $this->userMapper->findByUsername($decodedToken->$candidateClaim);
                if ($user) {
                    return $user;
                }
                $failureMessage = sprintf(
                    'The token contained a "%s" claim, but its value did not match a Schoolbox user',
                    $candidateClaim
                );
            } else {
                $failureMessage = sprintf(
                    'The token did not contain a "%s" claim',
                    $candidateClaim
                );
            }
            $messages[] = $failureMessage;
        }

        // Candidate claims found no user: can't use it
        throw new SchoolboxNotFoundException(
            sprintf('No claim in the candidates matched a user (%s)', implode('; ', $messages))
        );
    }
}
