<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Firebase\JWT as JwtLibrary;
use Schoolbox\Application\Exception as SchoolboxException;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Interface for JWT parsers.
 */
interface IParser
{
    /**
     * Does this parser accept this JWT?
     *
     * In order to determine this, the parser is expected to decode the JWT's
     * claims without verifying it: this means that the JWT must be signed but
     * not encrypted.
     *
     * @param string $jwt The JWT to verify.
     * @return bool
     * @throws Exception\Unparseable if the JWT is malformed and cannot be
     *                               parsed.
     */
    public function accepts(string $jwt): bool;

    /**
     * Determine the user identified by the JWT.
     *
     * Presumes `$this->accepts($jwt)` has returned true.
     *
     * @param string $jwt The JWT to use to identify a user.
     * @return User The user identified by the JWT.
     * @throws Exception\Unparseable     if the JWT is malformed and cannot be parsed.
     * @throws Exception\Rejected        if the JWT is properly formed, but is rejected before evaluating the user which
     *                                   it identifies.
     * @throws Exception\InvalidIdentity if the JWT is accepted, but does not identify a valid user.
     * @throws JwtLibrary\BeforeValidException      if the JWT isn't yet valid
     * @throws JwtLibrary\ExpiredException          if the JWT is expired
     * @throws JwtLibrary\SignatureInvalidException if the JWT's signature is invalid
     * @throws UnexpectedValueException             if something else went wrong when decoding the JWT
     * @throws SchoolboxException\NotFound          if the JWT is valid, but identifies a user which does not exist
     */
    public function determineUser(string $jwt): User;
}
