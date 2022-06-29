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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Help\InstanceRegistry\Mapper as InstanceRegistryMapper;
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
     * @param Jwt                    $tokenBuilder           The token builder, used for decoding JWTs.
     * @param UserMapper             $userMapper             Mapper for retrieving users once the user in the JWT is
     *                                                       identified.
     * @param User\Token\Mapper      $tokenMapper            Mapper for retrieving user tokens.
     * @param InstanceRegistryMapper $instanceRegistryMapper Mapper for retrieving an instance's JWT public key.
     * @param IConfigLoader          $config                 Standard configuration store.
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private UserMapper $userMapper,
        private User\Token\Mapper $tokenMapper,
        private InstanceRegistryMapper $instanceRegistryMapper,
        private IConfigLoader $config,
    ) {
    }

    /**
     * Determine a user from a JWT.
     *
     * @param string $jwt
     * @return User
     */
    public function determineUserFromJwt(string $jwt): User
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
     * @throws UnexpectedValueException   if JWT is invalid for whatever reason
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
     * @throws UnexpectedValueException   if JWT is invalid for whatever reason
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

        $jwtVerifier = $jwtVerifierFactory->build(
            $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER),
            $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID),
            $sessionLength,
        );

        $candidateClaims = explode(',', $this->config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS));

        // Parse the token and extract a user from it
        // This line is squelched due to an unfixed warning in 3rd party method
        // `JwtVerifier::validateClientId`: the eventual solution is to remove
        // `okta/jwt-verifier` completely, but for now just ignore the warning.
        $decodedToken = @$jwtVerifier->verify($jwt);
        $claims       = $decodedToken->getClaims();

        // The identification in the token seems to be in one of two claims:
        // * "preferred_username" matches a Schoolbox username
        // * "https://cloudwork.id/claims/sis_id" matches an external ID
        // We'll just use the preferred username until I hear otherwise.
        $messages = [];
        foreach ($candidateClaims as $candidateClaim) {
            $candidateClaim = trim($candidateClaim);
            if (isset($claims[$candidateClaim])) {
                $user = $this->userMapper->findByUsername($claims[$candidateClaim]);
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

    /**
     * Determine a user from a JWT issued by some other instance.
     *
     * @param string $jwt
     * @param string $instanceId
     * @return User
     * @throws SchoolboxNotFoundException if no user was found
     * @throws UnexpectedValueException   if JWT is invalid for whatever reason
     * @throws UnexpectedValueException   if instance `$instanceId` isn't
     *                                    registered
     * @throws UnexpectedValueException   if JWT has a null or empty
     *                                    `self::CLAIM_EMAIL` claim
     */
    private function determineUserFromOtherInstanceIssuedJwt(string $jwt, string $instanceId): User
    {
        // Look up the instance in the registry, and get its public key so that
        // the JWT can be verified
        try {
            $instanceDetails = $this->instanceRegistryMapper->findByInstanceId($instanceId);
        } catch (MapperException $e) {
            throw new UnexpectedValueException(sprintf('Instance "%s" not registered', $instanceId), 0, $e);
        }

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
        return $this->userMapper->findByUsername($email) ?? throw new SchoolboxNotFoundException();
    }
}
