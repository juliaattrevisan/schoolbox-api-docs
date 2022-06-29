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
use UnexpectedValueException;

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

    /**
     * Key for the email JWT claim. This is a public claim, so it doesn't need
     * a prefix.
     */
    private const CLAIM_EMAIL = 'email';

    /**
     * Key for the instance ID custom JWT claim.
     */
    private const CLAIM_INSTANCE_ID = Jwt::CUSTOM_CLAIM_NAMESPACE . 'instance_id';

    /**
     * Constructor
     *
     * @param Jwt               $tokenBuilder The token builder, used for decoding JWTs.
     * @param UserMapper        $userMapper   Mapper for retrieving users once a user has been identified.
     * @param User\Token\Mapper $tokenMapper  Mapper for retrieving user tokens.
     * @param IConfigLoader     $config       Configuration
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private UserMapper $userMapper,
        private User\Token\Mapper $tokenMapper,
        private IConfigLoader $config,
    ) {
    }

    /**
     * Determine a user from a JWT.
     *
     * @param string $jwt
     * @return User|null
     */
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
        $instanceId = $claims[self::CLAIM_INSTANCE_ID] ?? null;
        if (!isset($instanceId)) {
            throw new UnexpectedValueException(sprintf('JWT must contain an "%s" claim', self::CLAIM_INSTANCE_ID));
        }
        return $this->determineUserFromOtherInstanceIssuedJwt($jwt, $instanceId);
    }

    /**
     * Determine a user from a JWT issued by this instance.
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
     * Determine a user from a JWT issued by this instance's oAuth provider.
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

    /**
     * Determine a user from a JWT issued by some other instance.
     *
     * @param string      $jwt
     * @param string|null $instanceId
     * @return User
     */
    private function determineUserFromOtherInstanceIssuedJwt(string $jwt, ?string $instanceId): User
    {
        // Now we know the instance:
        // Look it up in the registry table, and get the public key stored there
        // so that the JWT can be verified
        $instanceDetails = $this->instanceRegistryMapper->findByInstanceId((string)$instanceId);

        // Verify the payload
        $verifiedPayload = $this->tokenBuilder->decode($jwt, $instanceDetails->getJwtPublicKey());

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT
        $email = $verifiedPayload[self::CLAIM_EMAIL] ?? null;
        if (!isset($email)) {
            throw new UnexpectedValueException(sprintf('JWT must contain an "%s" claim', self::CLAIM_EMAIL));
        }

        // The email must be valid too, or at least not blank
        $email = (string)$email;
        if (trim($email) === '') {
            throw new UnexpectedValueException(sprintf('JWT "%s" claim must not be empty', self::CLAIM_EMAIL));
        }
        // Return the user identified by this session instead
        return $this->userMapper->findByUsername($email);
    }
}
