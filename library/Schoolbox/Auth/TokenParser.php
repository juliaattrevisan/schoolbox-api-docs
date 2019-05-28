<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth;

use Firebase\JWT\JWT;
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
     * Codec for JWT tokens.
     *
     * @var JWT
     */
    private $jwtCodec;


    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param JWT        $jwtCodec
     */
    public function __construct(UserMapper $userMapper, JWT $jwtCodec)
    {
        $this->userMapper = $userMapper;
        $this->jwtCodec   = $jwtCodec;
    }


    /**
     * Finds the user who matches the token, or null if no user found.
     * This doesn't actually authenticate the user: it merely retrieves a user
     * who is then passed to the authentication function.
     *
     * @param array  $serverVars The contents of the `$_SERVER` super-global.
     *                           The token should be in the "Authorization" HTTP
     *                           header, and be in the form "Bearer: $token"
     * @param string $key        The key used to decode the token.
     * @return User|null
     * @throws SchoolboxException\Exception       if the "Authorization" header isn't type "Bearer"
     * @throws SchoolboxException\NotFound        if the decoded token doesn't contain a "sub" (subject) key
     * @throws SchoolboxException\NotFound        if the decoded token's "sub" key doesn't match a username
     * @throws SchoolboxException\AccountDisabled if the user matched by the "sub" key isn't enabled
     */
    public function findUser(array $serverVars, string $key): ?User
    {
        // Find the auth header in server vars. If it's not there: do nothing
        $authHeader = $serverVars['HTTP_AUTHORIZATION'] ?? null;
        if (!isset($authHeader)) {
            return null;
        }

        if (preg_match('/^Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            [, $token] = $matches;
            // Parse the token and extract a user from it
            // TODO determine which algorithms our source supports
            $decodedToken = $this->jwtCodec::decode($token, $key, ['HS256']);

            // A username _should_ be in the "sub" (subject) key
            // Alternately: it might contain a token, or something?
            // Whatever it is, it must uniquely identify a user.
            if (property_exists($decodedToken, 'sub')) {
                // Verify the user:
                // * exists
                // * is enabled
                $user = $this->userMapper->findByUsername($decodedToken->sub);
                if (!$user) {
                    throw new SchoolboxException\NotFound('The token\'s "sub" property must match a user\'s username');
                }
                if (!$user->getEnabled()) {
                    throw new SchoolboxException\AccountDisabled('The user\'s account is disabled');
                }
                return $user;
            }

            // No "sub" found in token: can't use it
            throw new SchoolboxException\NotFound('The token must contain a "sub" property');
        }

        // Header was malformed: can't use?
        throw new SchoolboxException\Exception('Only the "Bearer" authorization method is supported');
    }
}
