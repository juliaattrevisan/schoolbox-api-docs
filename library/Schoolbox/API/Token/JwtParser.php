<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use Okta\JwtVerifier\Adaptors\FirebasePhpJwt;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Auth\JwtVerifierFactory\Configured;
use Schoolbox\Auth\TokenParser;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class JwtParser
 */
class JwtParser
{
    /**
     * Default session length, in seconds: used as the default leeway for the
     * JWT verifier.
     */
    private const SESSION_LENGTH_DEFAULT = 1800;

    private Jwt $tokenBuilder;
    private UserMapper $userMapper;
    private User\Token\Mapper $tokenMapper;
    private IConfigLoader $config;

    public function __construct(
        Jwt $tokenBuilder,
        UserMapper $userMapper,
        User\Token\Mapper $tokenMapper,
        IConfigLoader $config
    ) {
        $this->tokenBuilder = $tokenBuilder;
        $this->userMapper = $userMapper;
        $this->tokenMapper = $tokenMapper;
        $this->config = $config;
    }

    public function determineUserFromJwt(string $jwt): ?User
    {
        // Need to know what's in the JWT first,
        // before attempting to determine a user
        $claims = $this->tokenBuilder->decodeWithoutVerification($jwt);

        // Is the issuer this instance?
        if ($this->tokenBuilder->issuedBySchoolbox($claims)) {
            return $this->determineUserFromInstanceIssuedJwt($jwt);
        }

        // Was this issued by the oAuth provider?
        // (need to ensure that the issuer sort-of matches)
        if ($claims[MessagePayloadInterface::CLAIM_ISS] === $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER)) {
            return $this->determineUserFromOauthProviderIssuedJwt($jwt);
        }

        // Was this issued by another Schoolbox instance?

        return null;
    }

    /**
     * Determine the issuer from a JWT issued by this instance.
     *
     * @param string $jwt
     * @return User
     * @throws SchoolboxNotFoundException if no user was found
     * @throws ExpiredException           if the JWT has a JWT ID, and the
     *                                    matching token is expired
     * @throws SignatureInvalidException  if the JWT has a JWT ID, and there is
     *                                    no matching token
     */
    private function determineUserFromInstanceIssuedJwt(string $jwt): User
    {
        $claims = $this->tokenBuilder->decode($jwt);
        $user   = $this->userMapper->findByUsername($claims[MessagePayloadInterface::CLAIM_SUB]);
        if ($user && isset($claims[MessagePayloadInterface::CLAIM_JTI])) {
            // if issued by schoolbox and jti is provided, validate the jti
            try {
                /** @var User\Token\Model $token */
                $token = $this->tokenMapper->findOneByColumnList(
                    [
                        'user_id' => $user->getId(),
                        'jwt_id'  => $claims[MessagePayloadInterface::CLAIM_JTI],
                    ]
                );
                if ($token->getExpiry() !== null && !$token->getExpiry()->isNull() && $token->getExpiry()->isPast()) {
                    throw new ExpiredException();
                }
            } catch (MapperException $e) {
                // could not find a token with user id and jwt id
                throw new SignatureInvalidException($e->getMessage(), 0, $e);
            }
        }
        if ($user) {
            return $user;
        }
        throw new SchoolboxNotFoundException();
    }

    /**
     * Determine the issuer from a JWT issued by this instance's oAuth provider.
     *
     * @param string $jwt
     * @return User
     * @throws SchoolboxNotFoundException if no user was found
     */
    private function determineUserFromOauthProviderIssuedJwt(string $jwt): User
    {
        $sessionLength = (int)$this->config->get('session_length');
        if ($sessionLength <= 0) {
            $sessionLength = self::SESSION_LENGTH_DEFAULT;
        }

        $jwtVerifierFactory = new Configured(
            $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER_TYPE),
            new FirebasePhpJwt(null, $sessionLength)
        );

        $tokenParser = new TokenParser(
            $this->userMapper,
            $jwtVerifierFactory->build(
                $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER),
                $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID),
                $sessionLength,
            ),
            explode(',', $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS)),
        );

        return $tokenParser->findUserFromToken($jwt);
    }


}
