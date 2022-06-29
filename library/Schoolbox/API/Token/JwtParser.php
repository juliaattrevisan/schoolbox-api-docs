<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token;

use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\User;

/**
 * Helper class for identifying a user from a JWT.
 */
class JwtParser
{
    /**
     * Constructor
     *
     * @param Jwt     $tokenBuilder The token builder, used for decoding JWTs before extracting a user from them.
     *                              (This step is required for the parser to determine exactly what to do.)
     * @param IParser $parser       The token parser.
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private IParser $parser,
    ) {
    }

    /**
     * Determine a user from a JWT.
     *
     * @param string $jwt
     * @return User|null The user identified by the JWT, or null if no user was
     *                   able to be identified.
     */
    public function determineUserFromJwt(string $jwt): ?User
    {
        // Need to know what's in the JWT first,
        // before attempting to determine a user
        $claims = $this->tokenBuilder->decodeWithoutVerification($jwt);
        return $this->parser->determineUser($jwt, $claims);
    }
}
