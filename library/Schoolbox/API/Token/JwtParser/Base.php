<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use DomainException;
use Firebase\JWT as JwtLibrary;
use InvalidArgumentException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Base class for parsers.
 */
abstract class Base implements IParser
{
    /**
     * Cached results from `$this->decodeWithoutVerification`.
     *
     * @var array<string, array>
     */
    private array $decodeWithoutVerificationResults;
    /**
     * Cached results from `$this->decodeHeaders`.
     *
     * @var array <string, array>
     */
    private array $decodeHeadersResults;

    /**
     * @see IParser::bypassIpTracking()
     */
    public bool $bypassIpTracking = false;

    /**
     * Constructor
     *
     * @param UserMapper    $userMapper Mapper for retrieving users once the user in the JWT is identified.
     * @param IConfigLoader $config     Standard configuration store.
     */
    public function __construct(
        protected UserMapper $userMapper,
        protected IConfigLoader $config,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function decode(#[SensitiveParameter] string $jwt, mixed $keyOrKeySet, int $leeway): array
    {
        // Set leeway for this decode only, set it back once done
        $previousLeeway         = JwtLibrary\JWT::$leeway;
        JwtLibrary\JWT::$leeway = $leeway;
        try {
            return (array)JwtLibrary\JWT::decode($jwt, $keyOrKeySet);
        } catch (
            InvalidArgumentException
            | UnexpectedValueException
            | JwtLibrary\SignatureInvalidException
            | JwtLibrary\BeforeValidException
            | JwtLibrary\ExpiredException $e
        ) {
            throw new Exception\Rejected($e->getMessage(), $e->getCode(), $e);
        } finally {
            JwtLibrary\JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * Decodes a JWT without verifying that the JWT is valid.
     *
     * This is required so that a JWT's claims may be interrogated, in order to
     * decide whether this parser accepts it and may attempt to determine the
     * user which it identifies.
     *
     * @param string $jwt The JWT to parse.
     * @return array The claims within the JWT.
     * @throws Exception\Unparseable JWT doesn't contain a header, payload and
     *                               signature segment, or is not parseable for
     *                               any other reason
     *                               (e.g. a segment is invalid JSON).
     */
    protected function decodeWithoutVerification(#[SensitiveParameter] string $jwt): array
    {
        if (!isset($this->decodeWithoutVerificationResults[$jwt])) {
            $parts = explode('.', $jwt);
            if (count($parts) !== 3) {
                throw new Exception\Unparseable('JWT must contain a header, payload and signature');
            }
            [, $payload,] = $parts;
            try {
                $this->decodeWithoutVerificationResults[$jwt]
                    = (array)JwtLibrary\JWT::jsonDecode(JwtLibrary\JWT::urlsafeB64Decode($payload));
            } catch (DomainException $e) {
                throw new Exception\Unparseable('JWT could not be parsed: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        return $this->decodeWithoutVerificationResults[$jwt];
    }

    /**
     * Decodes a JWT's header without verifying that the JWT is valid.
     *
     * @param string $jwt The JWT to parse.
     * @return array The header within the JWT.
     * @throws Exception\Unparseable JWT doesn't contain a header, payload and
     *                               signature segment, or is not parseable for
     *                               any other reason
     *                               (e.g. a segment is invalid JSON).
     */
    protected function decodeHeaders(#[SensitiveParameter] string $jwt): array
    {
        if (!isset($this->decodeHeadersResults[$jwt])) {
            $parts = explode('.', $jwt);
            if (count($parts) !== 3) {
                throw new Exception\Unparseable('JWT must contain a header, payload and signature');
            }
            [$header,] = $parts;
            try {
                $this->decodeHeadersResults[$jwt]
                    = (array)JwtLibrary\JWT::jsonDecode(JwtLibrary\JWT::urlsafeB64Decode($header));
            } catch (DomainException $e) {
                throw new Exception\Unparseable('JWT could not be parsed: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        return $this->decodeHeadersResults[$jwt];
    }

    /**
     * @inheritDoc
     */
    public function bypassIpTracking(): bool
    {
        return $this->bypassIpTracking;
    }

    /**
     * Finds the user identified by `$username`:
     * * by standard username lookup
     * * if that fails, and `config.auth_email_enabled` is enabled, looks up a
     *   user whose email address matches `$username`
     *
     * @param string      $username    The username of the user to find
     * @param string|null $sourceClaim The claim from which `$username` was
     *                                 derived, if any: used for logging
     * @return User
     * @throws Exception\InvalidIdentity if no user is found, or the user found is deactivated
     */
    protected function findUser(string $username, ?string $sourceClaim): User
    {
        $user = $this->userMapper->findByUsername($username);
        if (!$user && $this->config->get(ConfigMapper::NAME_AUTH_EMAIL_ENABLED)) {
            $user = $this->userMapper->findByColumnList(['alt_email' => $username], ['deleted' => false])->first();
        }

        if (!$user) {
            $message = isset($sourceClaim)
                ? sprintf('JWT "%s" claim does not match a user', $sourceClaim)
                : sprintf('No user with username "%s" found', $username);
            throw new Exception\InvalidIdentity($message);
        }
        if (!$user->getEnabled()) {
            $message = isset($sourceClaim)
                ? sprintf('JWT "%s" claim matches a deactivated user', $sourceClaim)
                : sprintf('User with username "%s" is deactivated', $username);
            throw new Exception\InvalidIdentity($message);
        }

        return $user;
    }
}
