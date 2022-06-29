<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT as JwtLibrary;
use Schoolbox\Application\Exception;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Interface for JWT parsers.
 */
interface IParser
{
    /**
     * Does this parser accept this set of claims?
     *
     * @param array $claims The JWT's decoded claims.
     *                      Note these claims have been decoded without
     *                      verifying the JWT: that happens in
     *                      `$this->determineUser`.
     * @return bool
     */
    public function accepts(array $claims): bool;

    /**
     * Determine the user identified by the JWT.
     *
     * Presumes `$this->accepts($claims)` has returned true.
     *
     * @param string $jwt    The encoded JWT: this method is expected to verify
     *                       the JWT as well.
     * @param array  $claims The JWT's decoded claims: passed in addition to the
     *                       JWT as the parser may need to know where the JWT
     *                       is from in order to verify it.
     * @return User|null The user identified by the JWT, or null if no user was
     *                   identified.
     *                   (TODO should always throw if not found?)
     * @throws JwtLibrary\BeforeValidException      if the JWT isn't yet valid
     * @throws JwtLibrary\ExpiredException          if the JWT is expired
     * @throws JwtLibrary\SignatureInvalidException if the JWT's signature is invalid
     * @throws UnexpectedValueException             if something else went wrong when decoding the JWT
     * @throws Exception\NotFound                   if the JWT is valid, but identifies a user which does not exist
     * @throws Exception\AccountDisabled            if the JWT is valid, but identifies a user whose account is disabled
     */
    public function determineUser(string $jwt, array $claims): ?User;
}
