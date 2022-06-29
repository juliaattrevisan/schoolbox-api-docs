<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use Schoolbox\User;

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
     *                      verifying the JWT: that
     *                      happens in `$this->determineUser`.
     *
     * @return bool
     */
    public function accepts(array $claims): bool;

    /**
     * Determine the user identified by the JWT.
     *
     * @param string $jwt    The encoded JWT: this method is expected to verify
     *                       the JWT as well.
     * @param array  $claims The JWT's decoded claims: passed in addition to the
     *                       JWT as the parser may need to know where the JWT
     *                       is from in order to verify it.
     * @return User|null
     */
    public function determineUser(string $jwt, array $claims): ?User;
}
