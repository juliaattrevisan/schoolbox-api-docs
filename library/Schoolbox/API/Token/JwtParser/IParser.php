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
     *                                   (Should have been caught by `accepts`, but could be raised here.)
     * @throws Exception\Rejected        if the JWT is properly formed, but is rejected before evaluating the user which
     *                                   it identifies.
     * @throws Exception\InvalidIdentity if the JWT is accepted, but does not identify a valid user.
     */
    public function determineUser(string $jwt): User;
}
