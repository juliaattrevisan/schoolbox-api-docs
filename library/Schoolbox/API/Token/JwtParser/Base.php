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
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Base class for parsers.
 */
abstract class Base implements IParser
{
    /**
     * Cached results from `$this->decodeWithoutVerification`.
     *
     * @var array<string, array>
     */
    private array $decodeWithoutVerificationResults;

    /**
     * Decodes a JWT, and verifies that the JWT has been signed appropriately
     * and not tampered with.
     *
     * @param string               $jwt         The JWT to parse.
     * @param Key|array<Key>|mixed $keyOrKeySet A key object (or set thereof) to verify the JWT against.
     * @param int                  $leeway      The amount of clock skew allowance when checking the JWT's time claims.
     *                                          Allows leeway for:
     *                                          * nbf (not before)
     *                                          * iat (issued at)
     *                                          * exp (expiry)
     * @return array The claims within the JWT.
     * @throws Exception\Rejected JWT cannot be decoded for some reason
     *                            (e.g. the key is not valid for the given JWT,
     *                            or the signature is invalid)
     */
    protected function decode(string $jwt, mixed $keyOrKeySet, int $leeway): array
    {
        // Set leeway for this decode only, set it back once done
        $previousLeeway         = JwtLibrary\JWT::$leeway;
        JwtLibrary\JWT::$leeway = $leeway;
        try {
            return (array)JwtLibrary\JWT::decode($jwt, $keyOrKeySet);
        } catch (
            InvalidArgumentException
            | UnexpectedValueException
            | JwtLibrary\SignatureInvalidException
            | JwtLibrary\BeforeValidException
            | JwtLibrary\ExpiredException $e
        ) {
            throw new Exception\Rejected($e->getMessage(), $e->getCode(), $e);
        } finally {
            JwtLibrary\JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * Decodes a JWT without verifying that the JWT is valid.
     *
     * This is required so that a JWT's claims may be interrogated, in order to
     * decide whether this parser accepts it and may attempt to determine the
     * user which it identifies.
     *
     * @param string $jwt The JWT to parse.
     * @return array The claims within the JWT.
     * @throws Exception\Unparseable JWT doesn't contain a header, payload and
     *                               signature segment, or is not parseable for
     *                               any other reason
     *                               (e.g. a segment is invalid JSON).
     */
    protected function decodeWithoutVerification(string $jwt): array
    {
        if (!isset($this->decodeWithoutVerificationResults[$jwt])) {
            $parts = explode('.', $jwt);
            if (count($parts) !== 3) {
                throw new Exception\Unparseable('JWT must contain a header, payload and signature');
            }
            [, $payload,] = $parts;
            try {
                $this->decodeWithoutVerificationResults[$jwt]
                    = (array)JwtLibrary\JWT::jsonDecode(JwtLibrary\JWT::urlsafeB64Decode($payload));
            } catch (DomainException $e) {
                throw new Exception\Unparseable('JWT could not be parsed: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        return $this->decodeWithoutVerificationResults[$jwt];
    }
}
