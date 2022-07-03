<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser;

use DomainException;
use Firebase\JWT as JwtLibrary;
use Firebase\JWT\Key;
use UnexpectedValueException;

/**
 * Base class for parsers.
 */
abstract class Base implements IParser
{
    /**
     * Parses a JWT, and verifies that the JWT has been signed appropriately and
     * not tampered with.
     *
     * @param string               $jwt         The JWT to parse.
     * @param Key|array<Key>|mixed $keyOrKeySet A key object (or set thereof) to verify the JWT against.
     * @param int                  $leeway      The amount of clock skew allowance when checking the JWT's time claims.
     *                                          Allows leeway for:
     *                                          * nbf (not before)
     *                                          * iat (issued at)
     *                                          * exp (expiry)
     * @return array
     */
    protected function decode(string $jwt, mixed $keyOrKeySet, int $leeway): array
    {
        // Set leeway for this decode only, set it back once done
        $previousLeeway         = JwtLibrary\JWT::$leeway;
        JwtLibrary\JWT::$leeway = $leeway;
        try {
            return (array)JwtLibrary\JWT::decode($jwt, $keyOrKeySet);
        } finally {
            JwtLibrary\JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * Parses a JWT without verifying that the JWT is valid.
     *
     * This is required so that a JWT's claims may be interrogated, in order to
     * decide whether this parser accepts it and may attempt to determine the
     * user which it identifies.
     *
     * @param string $jwt The JWT to parse.
     * @return array
     * @throws UnexpectedValueException JWT doesn't contain a header, payload
     *                                  and signature segment
     * @throws DomainException          JWT payload is invalid JSON
     */
    protected function decodeWithoutVerification(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('JWT must contain a header, payload and signature');
        }
        [, $payload,] = $parts;
        return (array)JwtLibrary\JWT::jsonDecode(JwtLibrary\JWT::urlsafeB64Decode($payload));
    }
}
