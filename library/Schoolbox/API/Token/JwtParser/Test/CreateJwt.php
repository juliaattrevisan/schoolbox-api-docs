<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Firebase\JWT\JWT;

/**
 * Trait for creating a JWT for testing a parser's `accepts` method.
 *
 * The `accepts` method doesn't care about the header or signature of the JWT:
 * only its payload.
 */
trait CreateJwt
{
    /**
     * Create a mock JWT, with the provided payload.
     *
     * @param array $payload
     * @return string
     */
    protected function createJwt(array $payload): string
    {
        return sprintf('.%s.', JWT::urlsafeB64Encode(JWT::jsonEncode($payload)));
    }
}
