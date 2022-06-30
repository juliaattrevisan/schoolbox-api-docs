<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token;

use Firebase\JWT as JwtLibrary;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Application\Exception;
use Schoolbox\User;
use UnexpectedValueException;

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
     * Does this parser accept this JWT?
     *
     * @param string $jwt
     * @return bool
     */
    public function accepts(string $jwt): bool
    {
        $claims = $this->tokenBuilder->decodeWithoutVerification($jwt);
        return $this->parser->accepts($claims);
    }

    /**
     * Determine a user from a JWT.
     *
     * @param string $jwt
     * @return User The user identified by the JWT
     * @throws JwtLibrary\BeforeValidException      if the JWT isn't yet valid
     * @throws JwtLibrary\ExpiredException          if the JWT is expired
     * @throws JwtLibrary\SignatureInvalidException if the JWT's signature is invalid
     * @throws UnexpectedValueException             if something else went wrong when decoding the JWT
     * @throws Exception\NotFound                   if the JWT is valid, but identifies a user which does not exist
     */
    public function determineUser(string $jwt): User
    {
        // Need to know what's in the JWT first,
        // before attempting to determine a user.
        $claims = $this->tokenBuilder->decodeWithoutVerification($jwt);
        return $this->parser->determineUser($jwt, $claims);
    }
}
