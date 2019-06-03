<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth;

use Okta\JwtVerifier\JwtVerifier;
use Schoolbox\Application\Exception as SchoolboxException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class for parsing a Schoolbox user out of a JWT token.
 *
 * @todo convert to a PSR-15 compatible middleware
 */
class TokenParser
{
    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Verifier for JWT tokens.
     *
     * @var JwtVerifier
     */
    private $jwtVerifier;


    /**
     * Constructor
     *
     * @param UserMapper  $userMapper
     * @param JwtVerifier $jwtVerifier
     */
    public function __construct(UserMapper $userMapper, JwtVerifier $jwtVerifier)
    {
        $this->userMapper  = $userMapper;
        $this->jwtVerifier = $jwtVerifier;
    }


    /**
     * Finds the user who matches the token, or null if no user found.
     * This doesn't actually authenticate the user: it merely retrieves a user
     * who is then passed to the authentication function.
     *
     * @param string $token
     * @return User
     * @throws SchoolboxException\NotFound If no user was found
     */
    public function findUserFromToken(string $token): User
    {
        // Parse the token and extract a user from it
        $decodedToken = $this->jwtVerifier->verify($token);
        $claims       = $decodedToken->getClaims();

        // A username seems to be in the "username" key?
        if (isset($claims['username'])) {
            // Verify the user:
            // * exists
            // * is enabled
            $user = $this->userMapper->findByUsername($claims['username']);
            if (!$user) {
                throw new SchoolboxException\NotFound('The token\'s "username" property must match a user\'s username');
            }
            if (!$user->getEnabled()) {
                throw new SchoolboxException\AccountDisabled('The user\'s account is disabled');
            }
            return $user;
        }

        // No username found in token: can't use it
        throw new SchoolboxException\NotFound('The token must contain a "username" property');
    }


    /**
     * Finds the user from a token in PHP's server vars.
     *
     * The token must be in the "Authorization" HTTP header, and be in the form
     * "Bearer: $token".
     *
     * @param array  $serverVars The contents of the `$_SERVER` super-global.
     *
     * @return User
     * @throws SchoolboxException\Exception       if the "Authorization" header isn't type "Bearer"
     * @throws SchoolboxException\NotFound        if the decoded token doesn't contain a "sub" (subject) key
     * @throws SchoolboxException\NotFound        if the decoded token's "sub" key doesn't match a username
     * @throws SchoolboxException\AccountDisabled if the user matched by the "sub" key isn't enabled
     */
    public function findUserFromServerVars(array $serverVars): User
    {
        // Find the auth header in server vars. If it's not there: do nothing
        $authHeader = $serverVars['HTTP_AUTHORIZATION'] ?? null;
        if (!isset($authHeader)) {
            return null;
        }

        if (preg_match('/^Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            [, $token] = $matches;
            return $this->findUserFromToken($token);
        }

        // Header was malformed: can't use?
        throw new SchoolboxException\Exception('Only the "Bearer" authorization method is supported');
    }
}
